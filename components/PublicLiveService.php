<?php

namespace community\videolibrary\components;

use community\videolibrary\models\LiveForm;
use community\videolibrary\models\LiveSession;
use community\videolibrary\models\LiveSource;
use community\videolibrary\models\PublicLiveCategory;
use community\videolibrary\models\SettingsForm;
use Yii;

/** Coordinates the single, permanently waiting public PeerTube source. */
final class PublicLiveService
{
    public const SOURCE_USER_ID = 0;
    private static bool $replacedSource = false;

    public static function wasSourceReplaced(): bool
    {
        return self::$replacedSource;
    }

    public static function client(): PeerTubeClient
    {
        return new PeerTubeClient(SettingsForm::publicLiveProfile());
    }

    public static function source(): LiveSource
    {
        $source = LiveSource::findOne(['user_id' => self::SOURCE_USER_ID]);
        $client = self::client();
        if ($source) {
            try {
                $client->getLiveInfo((string) $source->peertube_uuid);
                return $source;
            } catch (\Throwable $exception) {
                if (!LiveError::isPeerTubeNotFound($exception)) {
                    throw $exception;
                }
                self::$replacedSource = true;
                Yii::warning('Die öffentliche Community-Livequelle wurde auf PeerTube gelöscht und wird ersetzt.', 'peertube');
            }
        }

        $settings = Yii::$app->getModule('peertube')->settings;
        $profile = SettingsForm::publicLiveProfile();
        $result = $client->createPermanentLive(
            (string) $settings->get('publicLiveWaitingTitle', 'Öffentlicher Live-Kanal'),
            (string) $settings->get('publicLiveWaitingDescription', ''),
            (int) $profile['channelId'],
            '',
            true
        );
        $video = $result['video'] ?? [];
        $live = $result['live'] ?? [];
        $source ??= new LiveSource();
        $source->setAttributes([
            'user_id' => self::SOURCE_USER_ID,
            'peertube_id' => (int) ($video['id'] ?? 0),
            'peertube_uuid' => (string) ($video['uuid'] ?? ''),
            'rtmp_url_encrypted' => VideoPasswordVault::encrypt((string) ($live['rtmpsUrl'] ?? $live['rtmpUrl'] ?? '')),
            'stream_key_encrypted' => VideoPasswordVault::encrypt((string) ($live['streamKey'] ?? '')),
            // Required by the legacy source schema; never returned to the UI.
            'password_encrypted' => VideoPasswordVault::encrypt(''),
            'created_at' => $source->isNewRecord ? date('Y-m-d H:i:s') : $source->created_at,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$source->save()) {
            throw new \RuntimeException('Die öffentliche Community-Livequelle konnte nicht lokal gespeichert werden.');
        }
        return $source;
    }

    public static function assertNoScheduleConflict(string $start, string $end, ?int $exceptId = null): void
    {
        $query = LiveSession::find()->where(['is_public' => 1, 'status' => 'scheduled'])
            ->andWhere(['<', 'start_datetime', $end])
            ->andWhere(['>', 'end_datetime', $start]);
        if ($exceptId) {
            $query->andWhere(['<>', 'id', $exceptId]);
        }
        if ($query->exists()) {
            throw new \RuntimeException('Der gemeinsame Community-Live-Kanal ist in diesem Zeitraum bereits für ein anderes öffentliches Event geplant.');
        }
    }

    /** Creates missing categories (and their public PeerTube playlists) and stores the session assignment. */
    public static function setCategories(LiveSession $session, array $selected, string $newNames = ''): void
    {
        $names = [];
        foreach ($selected as $id) {
            $category = PublicLiveCategory::findOne((int) $id);
            if ($category) $names[(int) $category->id] = $category;
        }
        foreach (preg_split('/[,;\r\n]+/u', $newNames) as $title) {
            $title = trim($title);
            if ($title === '') continue;
            $slug = self::slug($title);
            $category = PublicLiveCategory::findOne(['slug' => $slug]);
            if (!$category) {
                $category = new PublicLiveCategory(['title' => mb_substr($title, 0, 120), 'slug' => $slug, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                if (!$category->save()) throw new \RuntimeException('Die neue Kategorie konnte nicht gespeichert werden.');
            }
            $names[(int) $category->id] = $category;
        }
        $categoryIds = array_keys($names);
        $current = Yii::$app->db->createCommand('SELECT category_id FROM {{%peertube_public_live_session_category}} WHERE session_id = :session', [':session' => $session->id])->queryColumn();
        foreach (array_diff(array_map('intval', $current), $categoryIds) as $categoryId) {
            $category = PublicLiveCategory::findOne($categoryId);
            if ($category && $session->replay_uuid && $category->peertube_playlist_id) self::removeReplay($category, (string) $session->replay_uuid);
            Yii::$app->db->createCommand()->delete('{{%peertube_public_live_session_category}}', ['session_id' => $session->id, 'category_id' => $categoryId])->execute();
        }
        foreach ($names as $category) {
            self::ensurePlaylist($category);
            Yii::$app->db->createCommand()->upsert('{{%peertube_public_live_session_category}}', ['session_id' => $session->id, 'category_id' => $category->id], false)->execute();
            if ($session->replay_uuid) self::addReplay($category, (string) $session->replay_uuid);
        }
        $session->updateAttributes(['public_categories' => json_encode(array_values(array_map(static fn(PublicLiveCategory $c) => $c->title, $names)), JSON_UNESCAPED_UNICODE)]);
    }

    public static function syncReplay(LiveSession $session): void
    {
        if (!$session->replay_uuid) return;
        $ids = Yii::$app->db->createCommand('SELECT category_id FROM {{%peertube_public_live_session_category}} WHERE session_id = :session', [':session' => $session->id])->queryColumn();
        foreach (PublicLiveCategory::find()->where(['id' => $ids])->all() as $category) {
            self::ensurePlaylist($category);
            self::addReplay($category, (string) $session->replay_uuid);
        }
    }

    private static function ensurePlaylist(PublicLiveCategory $category): void
    {
        if ($category->peertube_playlist_id) return;
        $profile = SettingsForm::publicLiveProfile();
        $playlist = self::client()->createPlaylist((string) $category->title, 1, (int) $profile['channelId'], 'Öffentliche Community-Liveaufzeichnungen: ' . $category->title);
        $category->updateAttributes(['peertube_playlist_id' => (int) ($playlist['id'] ?? 0), 'peertube_playlist_uuid' => (string) ($playlist['uuid'] ?? ''), 'updated_at' => date('Y-m-d H:i:s')]);
        if (!(int) $category->peertube_playlist_id) throw new \RuntimeException('PeerTube hat keine Playlist-ID für die Kategorie zurückgegeben.');
    }

    private static function addReplay(PublicLiveCategory $category, string $uuid): void
    {
        $items = self::client()->playlistVideos((int) $category->peertube_playlist_id);
        foreach (($items['data'] ?? []) as $item) {
            if (($item['video']['uuid'] ?? '') === $uuid) return;
        }
        self::client()->addVideoToPlaylist((int) $category->peertube_playlist_id, $uuid);
    }

    private static function removeReplay(PublicLiveCategory $category, string $uuid): void
    {
        foreach ((self::client()->playlistVideos((int) $category->peertube_playlist_id)['data'] ?? []) as $item) {
            if (($item['video']['uuid'] ?? '') === $uuid && !empty($item['id'])) {
                self::client()->removeVideoFromPlaylist((int) $category->peertube_playlist_id, (int) $item['id']);
            }
        }
    }

    private static function slug(string $title): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title), '-'));
        return $slug !== '' ? mb_substr($slug, 0, 100) : 'kategorie-' . substr(hash('sha256', $title), 0, 12);
    }
}
