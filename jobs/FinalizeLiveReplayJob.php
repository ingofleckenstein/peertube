<?php

namespace community\videolibrary\jobs;

use humhub\modules\queue\ActiveJob;
use community\videolibrary\components\AdminAlert;
use community\videolibrary\components\LiveError;
use community\videolibrary\components\PeerTubeClient;
use community\videolibrary\components\PublicLiveService;
use community\videolibrary\components\UserPlaylistService;
use community\videolibrary\components\VideoPasswordVault;
use community\videolibrary\models\LiveSession;
use community\videolibrary\models\Media;
use Yii;

class FinalizeLiveReplayJob extends ActiveJob
{
    public int $sessionId;
    public int $attempt = 0;
    public int $generation = 0;

    public function run(): void
    {
        $session = LiveSession::findOne($this->sessionId);
        // "completed" without media_id is a session created by the first live
        // version. Import it as a legacy replay as well.
        if (!$session || (int)$session->poll_generation !== $this->generation || $session->media_id || in_array($session->status, ['failed','cancelled'], true)) return;
        // Atomically claim this generation. If several old jobs wake up at the
        // same time, only one may contact PeerTube or convert the content.
        if (LiveSession::updateAllCounters(['poll_generation'=>1], ['id'=>$this->sessionId,'poll_generation'=>$this->generation]) !== 1) return;
        $this->generation++;
        try {
            $client = $session->is_public ? PublicLiveService::client() : new PeerTubeClient();
            $response = $client->getLiveSessions((string)$session->peertube_uuid);
            $peerTubeSession = $this->findSession($response['data'] ?? [], $session);
            if (!$peerTubeSession) {
                $this->retry($session, 'preparing');
                return;
            }
            $startDate = !empty($peerTubeSession['startDate']) ? date('Y-m-d H:i:s', strtotime((string)$peerTubeSession['startDate'])) : date('Y-m-d H:i:s');
            if (empty($peerTubeSession['endDate'])) {
                LiveSession::updateAll(['status'=>'live','started_at'=>$session->started_at ?: $startDate,'last_error'=>null,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$session->id]);
                $this->retry($session, 'live', 0);
                return;
            }
            $endDate = date('Y-m-d H:i:s', strtotime((string)$peerTubeSession['endDate']));
            LiveSession::updateAll(['status'=>'ending','started_at'=>$session->started_at ?: $startDate,'ended_at'=>$endDate,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$session->id]);
            $replay = $peerTubeSession['replayVideo'] ?? null;
            if (!is_array($replay) || empty($replay['uuid'])) { $this->retry($session, 'ending'); return; }
            $uuid = (string)($replay['uuid'] ?? '');
            if ($uuid === '') throw new \RuntimeException('PeerTube lieferte keine UUID für die Live-Aufzeichnung.');
            $source = $session->source;
            $password = $session->is_public ? '' : $source->getVideoPassword();
            if ($session->is_public) {
                $client->updatePublic($uuid, (string)$session->title, (string)$session->description);
            } else {
                $client->update($uuid, (string)$session->title, (string)$session->description, $password);
            }
            $video = $client->verifyVideo($uuid, (string)$session->title, (string)$session->description);
            $this->convert($session, $video, $password);
            try {
                $session->refresh();
                if ($session->is_public) {
                    PublicLiveService::syncReplay($session);
                } elseif ($session->media_id && ($media = Media::findOne((int) $session->media_id))) {
                    UserPlaylistService::add($media);
                }
            } catch (\Throwable $playlistException) {
                // The recording is safely finished. A playlist failure must not
                // cause a second import or make a public replay private again.
                Yii::error($playlistException, 'peertube');
                AdminAlert::raise('PeerTube-Playlist konnte nicht synchronisiert werden: Session ' . $session->id, $playlistException);
            }
        } catch (\Throwable $exception) {
            Yii::error($exception, 'peertube');
            if (LiveError::isPeerTubeNotFound($exception)) {
                $session->updateAttributes([
                    'status' => 'failed',
                    'last_error' => 'Die PeerTube-Livequelle wurde nicht gefunden. Beim nächsten Start wird eine neue Quelle angelegt.',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                AdminAlert::raise('PeerTube-Livequelle fehlt: Session ' . $session->id, $exception);
                return;
            }
            if ($this->attempt < 20) { $this->retry($session, (string)$session->status, $this->attempt + 1); return; }
            $session->updateAttributes(['status'=>'failed','last_error'=>mb_substr($exception->getMessage(),0,1000),'updated_at'=>date('Y-m-d H:i:s')]);
            AdminAlert::raise('Live-Aufzeichnung konnte nicht in die Mediathek übernommen werden: Session '.$session->id, $exception);
        }
    }

    private function findSession(array $sessions, LiveSession $session): ?array
    {
        // A scheduled event can be announced weeks beforehand. Its creation
        // date must not make an old, already finished public event look like
        // the replay of this later event.
        $anchor = $session->started_at ?: ($session->start_datetime ?: $session->created_at);
        $threshold = strtotime((string) $anchor) - 300;
        usort($sessions, static function(array $a, array $b): int {
            return strtotime((string)($b['startDate'] ?? '')) <=> strtotime((string)($a['startDate'] ?? ''));
        });
        // An open session is PeerTube's authoritative indication that an RTMP
        // sender is connected. Do not reject it using HumHub/PHP local time:
        // PeerTube returns UTC timestamps and the two hosts may use different
        // system time zones.
        foreach ($sessions as $item) {
            if (!empty($item['startDate']) && empty($item['endDate'])) return $item;
        }
        foreach ($sessions as $item) {
            if (!empty($item['startDate']) && strtotime((string)$item['startDate']) < $threshold) continue;
            return $item;
        }
        return null;
    }

    private function retry(LiveSession $session, string $status, ?int $nextAttempt = null): void
    {
        $isLive = $status === 'live';
        $nextAttempt ??= $isLive ? 0 : $this->attempt + 1;
        if (!$isLive && $nextAttempt > 240) throw new \RuntimeException('PeerTube hat innerhalb von 20 Minuten kein Signal oder keine fertige Aufzeichnung bereitgestellt.');
        $updated = LiveSession::updateAllCounters(['poll_generation'=>1], ['id'=>$this->sessionId,'poll_generation'=>$this->generation]);
        if ($updated !== 1) return;
        $nextGeneration=$this->generation+1;
        LiveSession::updateAll(['status'=>$status,'updated_at'=>date('Y-m-d H:i:s')],['id'=>$this->sessionId]);
        $delay = $status === 'ending' ? 10 : 5;
        Yii::$app->queue->delay($delay)->push(new self(['sessionId'=>$this->sessionId,'attempt'=>$nextAttempt,'generation'=>$nextGeneration]));
    }

    private function convert(LiveSession $session, array $video, string $password): void
    {
        $uuid=(string)($video['uuid'] ?? '');
        $existing=Media::findOne(['peertube_uuid'=>$uuid]);
        if ($existing) { $session->updateAttributes(['media_id'=>$existing->id,'replay_uuid'=>$uuid,'status'=>'completed','updated_at'=>date('Y-m-d H:i:s')]); return; }
        $container=$session->content->container;
        $tx=Yii::$app->db->beginTransaction();
        try {
            $thumb=(string)($video['thumbnailPath'] ?? $video['previewPath'] ?? '');
            $media=new Media($container,[
                'space_id'=>$session->space_id,'user_id'=>$session->user_id,'peertube_id'=>(int)($video['id'] ?? 0),
                'peertube_uuid'=>$uuid,'title'=>$session->title,'description'=>$session->description,
                'content_warnings'=>'[]','password_encrypted'=>VideoPasswordVault::encrypt($password),'publish_to_stream'=>1,
                'thumbnail_url'=>$thumb !== '' ? rtrim((string)Yii::$app->getModule('peertube')->settings->get('baseUrl',''),'/').'/'.ltrim($thumb,'/') : null,
                'media_type'=>'video','created_at'=>$session->created_at,'updated_at'=>date('Y-m-d H:i:s'),
                'sync_status'=>'synced','topics'=>'[]','folder_id'=>null,
            ]);
            $media->setPublishToStream(true);
            // Queue workers have no logged-in identity. Preserve authorship
            // explicitly from the original live content before HumHub saves
            // the temporary Content row.
            $media->content->created_by = (int)$session->user_id;
            $media->content->updated_by = (int)$session->user_id;
            if (!$media->save()) throw new \RuntimeException('Der Mediathek-Eintrag der Live-Aufzeichnung konnte nicht gespeichert werden.');
            $temporaryContentId=(int)$media->content->id;
            $originalContent=$session->content;
            // Remove the automatically created duplicate Content row first;
            // otherwise HumHub's unique object mapping rejects the reassignment.
            Yii::$app->db->createCommand()->delete('{{%content}}',['id'=>$temporaryContentId])->execute();
            if ($originalContent->updateAttributes(['object_model'=>Media::class,'object_id'=>(int)$media->id]) < 1) {
                throw new \RuntimeException('Der bestehende Livebeitrag konnte nicht mit der Aufzeichnung verbunden werden.');
            }
            $session->updateAttributes(['media_id'=>$media->id,'replay_uuid'=>$uuid,'status'=>'completed','ended_at'=>$session->ended_at ?: date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            $tx->commit();
        } catch (\Throwable $exception) { $tx->rollBack(); throw $exception; }
    }
}
