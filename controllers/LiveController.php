<?php

namespace selfsein\peertube\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\space\models\Space;
use selfsein\peertube\components\AccessPolicy;
use selfsein\peertube\components\PeerTubeClient;
use selfsein\peertube\components\VideoPasswordVault;
use selfsein\peertube\models\LiveForm;
use selfsein\peertube\models\LiveSession;
use selfsein\peertube\models\LiveSource;
use selfsein\peertube\jobs\FinalizeLiveReplayJob;
use Yii;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use humhub\modules\file\models\File;

class LiveController extends ContentContainerController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => [
            'activate' => ['post'], 'end' => ['post'], 'status' => ['get'],
        ]];
        return $behaviors;
    }

    public function actionStart()
    {
        $this->assertAvailable();
        if (!AccessPolicy::canStartLive($this->contentContainer)) {
            return $this->render('denied');
        }

        $active = LiveSession::find()->where(['user_id' => Yii::$app->user->id])
            ->andWhere(['status' => ['preparing', 'live', 'ending']])->orderBy(['id' => SORT_DESC])->one();
        if ($active) {
            return $this->redirect($active->content->container->createUrl('/peertube/live/studio', ['id' => $active->id]));
        }

        $model = new LiveForm();
        $loaded = $model->load(Yii::$app->request->post());
        if ($loaded) $model->announcementImage = UploadedFile::getInstance($model, 'announcementImage');
        if ($loaded && $model->validate()) {
            try {
                $source = $this->sourceForCurrentUser($model);
            } catch (\Throwable $exception) {
                Yii::error($exception, 'peertube');
                $model->addError('title', \selfsein\peertube\components\LiveError::message($exception));
                return $this->render('start', ['model' => $model]);
            }
            $now = date('Y-m-d H:i:s');
            $scheduledAt = null;
            $scheduledEnd = null;
            if ($model->schedule) {
                $scheduledAt = date('Y-m-d H:i:s', strtotime((string)$model->scheduledAt));
                if (strtotime($scheduledAt) < time() + 60) {
                    $model->addError('scheduledAt', 'Der geplante Beginn muss mindestens eine Minute in der Zukunft liegen.');
                    return $this->render('start', ['model' => $model]);
                }
                $scheduledEnd = date('Y-m-d H:i:s', strtotime($scheduledAt) + ((int)$model->durationMinutes * 60));
            }
            $session = new LiveSession($this->contentContainer, [
                'source_id' => $source->id,
                'user_id' => Yii::$app->user->id,
                'space_id' => $this->contentContainer instanceof Space ? $this->contentContainer->id : null,
                'peertube_uuid' => $source->peertube_uuid,
                'title' => $model->title,
                'description' => $model->description,
                'status' => $scheduledAt ? 'scheduled' : 'preparing',
                'start_datetime' => $scheduledAt,
                'end_datetime' => $scheduledEnd,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!$session->save()) {
                Yii::error($session->getErrors(), 'peertube');
                throw new \RuntimeException('Der Livestream-Beitrag konnte nicht angelegt werden.');
            }
            if ($model->announcementImage) {
                try {
                    $this->storeAnnouncementImage($session, $model->announcementImage);
                    $this->applyAnnouncementImageToPeerTube($source, $model->announcementImage, $model);
                } catch (\Throwable $exception) {
                    Yii::error($exception, 'peertube');
                    $this->view->warning('Der Livestream wurde angelegt, aber das Ankündigungsbild konnte nicht vollständig gespeichert werden.');
                }
            }
            $delay = $scheduledAt ? max(1, strtotime($scheduledAt) - time() - 60) : 1;
            $this->queueReplayCheck($session, $delay);
            return $this->redirect($this->contentContainer->createUrl('/peertube/live/studio', ['id' => $session->id]));
        }
        return $this->render('start', ['model' => $model]);
    }

    public function actionStudio(int $id)
    {
        $session = $this->ownedSession($id);
        // Opening the studio also heals a stopped/missed polling chain. The
        // generation guard makes already queued checks harmless.
        if (in_array($session->status, ['preparing', 'live', 'ending'], true)) {
            $this->queueReplayCheck($session, 1);
        }
        return $this->render('studio', ['session' => $session, 'source' => $session->source]);
    }

    public function actionEdit(int $id)
    {
        $session = $this->ownedSession($id);
        if (!in_array($session->status, ['scheduled', 'preparing'], true)) {
            $this->view->warning('Dieser Livestream hat bereits begonnen und kann nicht mehr als Ankündigung bearbeitet werden. Die fertige Aufzeichnung kannst du anschließend wie ein normales Video bearbeiten.');
            return $this->redirect($this->contentContainer->createUrl('/peertube/live/view', ['id' => $session->id]));
        }

        $model = new LiveForm(['scenario' => LiveForm::SCENARIO_EDIT]);
        $model->title = $session->title;
        $model->description = $session->description;
        $model->schedule = $session->status === 'scheduled';
        $model->scheduledAt = $session->start_datetime
            ? date('Y-m-d\\TH:i', strtotime((string)$session->start_datetime))
            : null;
        if ($session->start_datetime && $session->end_datetime) {
            $model->durationMinutes = max(5, (int)round((strtotime((string)$session->end_datetime) - strtotime((string)$session->start_datetime)) / 60));
        }

        if ($model->load(Yii::$app->request->post())) {
            $model->announcementImage = UploadedFile::getInstance($model, 'announcementImage');
            if ($model->validate()) {
                $scheduledAt = null;
                $scheduledEnd = null;
                $status = 'preparing';
                if ($model->schedule) {
                    $scheduledAt = date('Y-m-d H:i:s', strtotime((string)$model->scheduledAt));
                    if (strtotime($scheduledAt) < time() + 60) {
                        $model->addError('scheduledAt', 'Der geplante Beginn muss mindestens eine Minute in der Zukunft liegen.');
                        return $this->render('edit', ['model' => $model, 'session' => $session]);
                    }
                    $scheduledEnd = date('Y-m-d H:i:s', strtotime($scheduledAt) + ((int)$model->durationMinutes * 60));
                    $status = 'scheduled';
                }

                try {
                    $source = $session->source;
                    if (!$source) {
                        throw new \RuntimeException('Die Livestream-Quelle wurde nicht gefunden.');
                    }
                    (new PeerTubeClient())->update(
                        (string)$source->peertube_uuid,
                        (string)$model->title,
                        (string)$model->description,
                        $source->getVideoPassword(),
                        $model->announcementImage ? [
                            'path' => $model->announcementImage->tempName,
                            'mimeType' => $model->announcementImage->type,
                            'name' => $model->announcementImage->name,
                        ] : null
                    );

                    $session->setAttributes([
                        'title' => $model->title,
                        'description' => $model->description,
                        'status' => $status,
                        'start_datetime' => $scheduledAt,
                        'end_datetime' => $scheduledEnd,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    if (!$session->save()) {
                        throw new \RuntimeException('Die geänderte Livestream-Ankündigung konnte nicht gespeichert werden.');
                    }
                    if ($model->announcementImage) {
                        $this->replaceAnnouncementImage($session, $model->announcementImage);
                    }
                    $delay = $scheduledAt ? max(1, strtotime($scheduledAt) - time() - 60) : 1;
                    $this->queueReplayCheck($session, $delay);
                    $this->view->success('Die Livestream-Ankündigung wurde aktualisiert.');
                    return $this->redirect($this->contentContainer->createUrl('/peertube/live/view', ['id' => $session->id]));
                } catch (\Throwable $exception) {
                    Yii::error($exception, 'peertube');
                    $model->addError('title', 'Die Änderungen konnten nicht vollständig gespeichert werden. Bitte versuche es erneut.');
                }
            }
        }

        return $this->render('edit', ['model' => $model, 'session' => $session]);
    }

    public function actionStatus(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        $session = $this->ownedSession($id);
        return [
            'status' => $session->status,
            'redirectUrl' => $session->media_id
                ? $this->contentContainer->createUrl('/peertube/media/view', ['id' => $session->media_id])
                : null,
        ];
    }

    public function actionActivate(int $id)
    {
        $session = $this->ownedSession($id);
        $this->queueReplayCheck($session, 1);
        $this->view->info('Der Live-Status wird automatisch mit PeerTube abgeglichen.');
        return $this->redirect($this->contentContainer->createUrl('/peertube/live/studio', ['id' => $session->id]));
    }

    public function actionEnd(int $id)
    {
        $session = $this->ownedSession($id);
        if (in_array($session->status, ['preparing', 'live', 'ending'], true)) {
            $session->updateAttributes(['status' => 'ending', 'updated_at' => date('Y-m-d H:i:s')]);
            $this->queueReplayCheck($session, 15);
        }
        $this->view->info('Der Status wird geprüft. Beende die Übertragung bitte in OBS; PeerTube verarbeitet danach die Aufzeichnung.');
        return $this->redirect($this->contentContainer->createUrl('/peertube/live/view', ['id' => $session->id]));
    }

    public function actionView(int $id)
    {
        $this->assertAvailable();
        $session = LiveSession::findOne($id);
        if ($session && $session->media_id) {
            $media = \selfsein\peertube\models\Media::findOne((int)$session->media_id);
            if ($media) return $this->redirect($media->getUrl());
        }
        if (!$session || !$this->belongsToCurrentContainer($session) || !$session->canBeViewedBy()) throw new NotFoundHttpException();
        if ($session->status === 'completed' && !$session->media_id) {
            $this->queueReplayCheck($session, 1);
        }
        return $this->render('view', ['session' => $session]);
    }

    public function actionPassword(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        $session = LiveSession::findOne($id);
        if (!$session || !$this->belongsToCurrentContainer($session) || !$session->canBeViewedBy()) throw new ForbiddenHttpException('Du darfst diesen Livestream nicht ansehen.');
        return ['password' => $session->source->getVideoPassword()];
    }

    private function sourceForCurrentUser(LiveForm $form): LiveSource
    {
        $source = LiveSource::findOne(['user_id' => Yii::$app->user->id]);
        $client = new PeerTubeClient();
        if ($source) {
            $client->update((string)$source->peertube_uuid, $form->title, (string)$form->description, $source->getVideoPassword());
            $client->tryUseSmallLiveLatency((string)$source->peertube_uuid);
            return $source;
        }
        $password = VideoPasswordVault::generate();
        $result = $client->createPermanentLive($form->title, (string)$form->description,
            (int)Yii::$app->getModule('peertube')->settings->get('channelId'), $password);
        $video = $result['video']; $live = $result['live'];
        $remoteId = (string)($video['id'] ?? '');
        $source = new LiveSource([
            'user_id' => Yii::$app->user->id,
            'peertube_id' => (int)$remoteId,
            'peertube_uuid' => (string)($video['uuid'] ?? ''),
            'rtmp_url_encrypted' => VideoPasswordVault::encrypt((string)($live['rtmpsUrl'] ?? $live['rtmpUrl'])),
            'stream_key_encrypted' => VideoPasswordVault::encrypt((string)$live['streamKey']),
            'password_encrypted' => VideoPasswordVault::encrypt($password),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$source->save()) {
            try { $client->delete($remoteId); } catch (\Throwable $e) { Yii::error($e, 'peertube'); }
            throw new \RuntimeException('Die dauerhafte Livestream-Quelle konnte nicht gespeichert werden.');
        }
        return $source;
    }

    private function storeAnnouncementImage(LiveSession $session, UploadedFile $upload): void
    {
        $file = new File([
            'file_name' => $upload->name,
            'title' => 'Livestream-Ankündigung',
            'mime_type' => $upload->type,
            'size' => $upload->size,
            'show_in_stream' => 0,
        ]);
        if (!$file->save()) throw new \RuntimeException('Der Dateieintrag für das Ankündigungsbild konnte nicht gespeichert werden.');
        try {
            $file->setStoredFile($upload);
            $session->fileManager->attach($file);
        } catch (\Throwable $exception) {
            $file->delete();
            throw $exception;
        }
    }

    private function replaceAnnouncementImage(LiveSession $session, UploadedFile $upload): void
    {
        $oldImages = $session->fileManager->find()->andWhere(['like', 'mime_type', 'image/%', false])->all();
        $this->storeAnnouncementImage($session, $upload);
        foreach ($oldImages as $oldImage) {
            try {
                $oldImage->delete();
            } catch (\Throwable $exception) {
                Yii::warning($exception, 'peertube');
            }
        }
    }

    private function applyAnnouncementImageToPeerTube(LiveSource $source, UploadedFile $upload, LiveForm $form): void
    {
        (new PeerTubeClient())->update(
            (string)$source->peertube_uuid,
            (string)$form->title,
            (string)$form->description,
            $source->getVideoPassword(),
            ['path' => $upload->tempName, 'mimeType' => $upload->type, 'name' => $upload->name]
        );
    }

    private function queueReplayCheck(LiveSession $session, int $delay): void
    {
        try {
            LiveSession::updateAllCounters(['poll_generation' => 1], ['id' => (int)$session->id]);
            $session->refresh();
            Yii::$app->queue->delay($delay)->push(new FinalizeLiveReplayJob([
                'sessionId' => (int)$session->id,
                'generation' => (int)$session->poll_generation,
            ]));
        } catch (\Throwable $exception) {
            Yii::error($exception, 'peertube');
            $this->view->warning('Die automatische Verarbeitung konnte nicht gestartet werden. Bitte informiere die Administration.');
        }
    }

    private function ownedSession(int $id): LiveSession
    {
        $this->assertAvailable();
        $session = LiveSession::findOne($id);
        $user = Yii::$app->user->identity;
        if (!$session || !$this->belongsToCurrentContainer($session) || ((int)$session->user_id !== (int)Yii::$app->user->id && !$user->isSystemAdmin())) {
            throw new ForbiddenHttpException('Du darfst diesen Livestream nicht verwalten.');
        }
        return $session;
    }

    private function belongsToCurrentContainer(LiveSession $session): bool
    {
        $actual = $session->content->container;
        return $actual && $this->contentContainer
            && get_class($actual) === get_class($this->contentContainer)
            && (int)$actual->id === (int)$this->contentContainer->id;
    }

    private function assertAvailable(): void
    {
        if (!$this->contentContainer || !$this->contentContainer->moduleManager->isEnabled('peertube')) throw new NotFoundHttpException();
    }
}
