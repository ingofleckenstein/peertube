<?php

namespace community\videolibrary\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\space\models\Space;
use community\videolibrary\components\AccessPolicy;
use community\videolibrary\components\LiveCapacity;
use community\videolibrary\components\PeerTubeClient;
use community\videolibrary\components\PublicLiveService;
use community\videolibrary\components\VideoPasswordVault;
use community\videolibrary\models\LiveForm;
use community\videolibrary\models\LiveSession;
use community\videolibrary\models\LiveSource;
use community\videolibrary\models\PublicLiveCategory;
use community\videolibrary\jobs\FinalizeLiveReplayJob;
use Yii;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;
use humhub\modules\file\models\File;

class LiveController extends ContentContainerController
{
    public const DIAGNOSTICS_VERSION = '2.9.1';

    private bool $replacedMissingSource = false;

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
            $isPublic = !empty($model->publicEvent);
            if ($isPublic && !AccessPolicy::canStartPublicLive()) {
                return $this->render('denied');
            }
            $now = date('Y-m-d H:i:s');
            $scheduledAt = null;
            $scheduledEnd = null;
            if (!empty($model->schedule)) {
                $scheduledAt = date('Y-m-d H:i:s', strtotime((string)$model->scheduledAt));
                if (strtotime($scheduledAt) < time() + 60) {
                    $model->addError('scheduledAt', 'Der geplante Beginn muss mindestens eine Minute in der Zukunft liegen.');
                    return $this->render('start', ['model' => $model]);
                }
                $scheduledEnd = date('Y-m-d H:i:s', strtotime($scheduledAt) + ((int)$model->durationMinutes * 60));
                if ($isPublic) {
                    try {
                        PublicLiveService::assertNoScheduleConflict($scheduledAt, $scheduledEnd);
                    } catch (\Throwable $exception) {
                        $model->addError('scheduledAt', $exception->getMessage());
                        return $this->render('start', ['model' => $model]);
                    }
                }
            }
            try {
                // The reservation holds a database lock until the LiveSession is
                // saved, so two simultaneous requests cannot both take the last
                // available slot.
                $capacityReservation = LiveCapacity::reserve($scheduledAt, $scheduledEnd, $isPublic);
            } catch (\Throwable $exception) {
                Yii::error($exception, 'peertube');
                $model->addError('title', 'Die verfügbare Livestream-Kapazität kann derzeit nicht geprüft werden. Bitte informiere die Administration.');
                return $this->render('start', ['model' => $model]);
            }
            if ($capacityReservation === null) {
                $model->addError('title', 'Es sind zu viele gleichzeitige Streams aktiv. Versuche es später erneut.');
                return $this->render('start', ['model' => $model]);
            }
            try {
                // The database lock held by the capacity reservation makes the
                // shared source check race-safe even when no numeric cap is set.
                if ($isPublic && $scheduledAt && $scheduledEnd) {
                    PublicLiveService::assertNoScheduleConflict($scheduledAt, $scheduledEnd);
                }
                $source = $isPublic ? PublicLiveService::source() : $this->sourceForCurrentUser($model);
            } catch (\Throwable $exception) {
                $capacityReservation->rollback();
                $model->addError('title', \community\videolibrary\components\LiveError::report(
                    $exception, 'prepare-source', self::DIAGNOSTICS_VERSION
                ));
                return $this->render('start', [
                    'model' => $model,
                    'liveManagementUrl' => \community\videolibrary\components\LiveError::managementUrl(
                        $exception, (string)Yii::$app->getModule('peertube')->settings->get('baseUrl', '')
                    ),
                ]);
            }
            $session = new LiveSession($this->contentContainer, [
                'source_id' => $source->id,
                'user_id' => Yii::$app->user->id,
                'space_id' => $this->contentContainer instanceof Space ? $this->contentContainer->id : null,
                'peertube_uuid' => $source->peertube_uuid,
                'title' => $model->title,
                'description' => $model->description,
                'status' => $scheduledAt ? 'scheduled' : 'preparing',
                'is_public' => $isPublic ? 1 : 0,
                'start_datetime' => $scheduledAt,
                'end_datetime' => $scheduledEnd,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!$session->save()) {
                $capacityReservation->rollback();
                Yii::error($session->getErrors(), 'peertube');
                throw new \RuntimeException('Der Livestream-Beitrag konnte nicht angelegt werden.');
            }
            $capacityReservation->commit();
            if ($isPublic) {
                try {
                    PublicLiveService::setCategories($session, (array) $model->publicCategories, (string) $model->newPublicCategories);
                } catch (\Throwable $exception) {
                    Yii::error($exception, 'peertube');
                    $this->view->warning('Der öffentliche Termin wurde angelegt, aber seine Kategorien konnten noch nicht vollständig mit dem Video-Dienst synchronisiert werden.');
                }
            }
            if ($model->announcementImage) {
                try {
                    $this->storeAnnouncementImage($session, $model->announcementImage);
                    // The public source deliberately retains its fixed waiting
                    // screen. Event artwork lives on the HumHub announcement.
                    if (!$isPublic) $this->applyAnnouncementImageToPeerTube($source, $model->announcementImage, $model);
                } catch (\Throwable $exception) {
                    Yii::error($exception, 'peertube');
                    $this->view->warning('Der Livestream wurde angelegt, aber das Ankündigungsbild konnte nicht vollständig gespeichert werden.');
                }
            }
            $delay = $scheduledAt ? max(1, strtotime($scheduledAt) - time() - 60) : 1;
            $this->queueReplayCheck($session, $delay);
            if ($this->replacedMissingSource) {
                $this->view->error('Deine bisherige wartende Livequelle wurde im Video-Dienst gelöscht. HumHub hat eine neue Quelle angelegt. Bitte verwende für OBS die jetzt angezeigte Server-Adresse und den neuen Streamschlüssel.');
            }
            if ($isPublic && PublicLiveService::wasSourceReplaced()) {
                $this->view->error('Die wartende öffentliche Livequelle wurde im Video-Dienst gelöscht. HumHub hat eine neue Quelle angelegt. Das Redaktionsteam muss in OBS die jetzt angezeigte Server-Adresse und den neuen gemeinsamen Streamschlüssel verwenden.');
            }
            return $this->redirect($this->contentContainer->createUrl('/peertube/live/studio', ['id' => $session->id]));
        }
        return $this->render('start', ['model' => $model, 'publicCategoryOptions' => PublicLiveCategory::options(), 'canStartPublicLive' => AccessPolicy::canStartPublicLive()]);
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
        if (!in_array($session->status, ['scheduled', 'preparing'], true) && !(bool) $session->is_public) {
            $this->view->warning('Dieser Livestream hat bereits begonnen und kann nicht mehr als Ankündigung bearbeitet werden. Die fertige Aufzeichnung kannst du anschließend wie ein normales Video bearbeiten.');
            return $this->redirect($this->contentContainer->createUrl('/peertube/live/view', ['id' => $session->id]));
        }

        $model = new LiveForm(['scenario' => LiveForm::SCENARIO_EDIT]);
        $model->title = $session->title;
        $model->description = $session->description;
        $model->schedule = $session->status === 'scheduled';
        $model->publicEvent = (bool) $session->is_public;
        $model->publicCategories = $session->is_public
            ? Yii::$app->db->createCommand('SELECT category_id FROM {{%peertube_public_live_session_category}} WHERE session_id = :session', [':session' => $session->id])->queryColumn()
            : [];
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
                    if ($session->is_public) {
                        try {
                            PublicLiveService::assertNoScheduleConflict($scheduledAt, $scheduledEnd, (int) $session->id);
                        } catch (\Throwable $exception) {
                            $model->addError('scheduledAt', $exception->getMessage());
                            return $this->render('edit', ['model' => $model, 'session' => $session, 'publicCategoryOptions' => PublicLiveCategory::options()]);
                        }
                    }
                }

                try {
                    $source = $session->source;
                    if (!$source) {
                        throw new \RuntimeException('Die Livestream-Quelle wurde nicht gefunden.');
                    }
                    if (!$session->is_public) {
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
                    }

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
                    if ($session->is_public) {
                        PublicLiveService::setCategories($session, (array) $model->publicCategories, (string) $model->newPublicCategories);
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

        return $this->render('edit', ['model' => $model, 'session' => $session, 'publicCategoryOptions' => PublicLiveCategory::options()]);
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
        $this->view->info('Der Live-Status wird automatisch mit dem Video-Dienst abgeglichen.');
        return $this->redirect($this->contentContainer->createUrl('/peertube/live/studio', ['id' => $session->id]));
    }

    public function actionEnd(int $id)
    {
        $session = $this->ownedSession($id);
        if (in_array($session->status, ['preparing', 'live', 'ending'], true)) {
            $session->updateAttributes(['status' => 'ending', 'updated_at' => date('Y-m-d H:i:s')]);
            $this->queueReplayCheck($session, 15);
        }
        $this->view->info('Der Status wird geprüft. Beende die Übertragung bitte in OBS; der Video-Dienst verarbeitet danach die Aufzeichnung.');
        return $this->redirect($this->contentContainer->createUrl('/peertube/live/view', ['id' => $session->id]));
    }

    public function actionView(int $id)
    {
        $this->assertAvailable();
        $session = LiveSession::findOne($id);
        if ($session && $session->media_id) {
            $media = \community\videolibrary\models\Media::findOne((int)$session->media_id);
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
        return ['password' => $session->is_public ? '' : $session->source->getVideoPassword()];
    }

    private function sourceForCurrentUser(LiveForm $form): LiveSource
    {
        $source = LiveSource::findOne(['user_id' => Yii::$app->user->id]);
        $client = new PeerTubeClient();
        if ($source) {
            try {
                $client->update((string)$source->peertube_uuid, $form->title, (string)$form->description, $source->getVideoPassword());
                $client->tryUseSmallLiveLatency((string)$source->peertube_uuid);
                return $source;
            } catch (\Throwable $exception) {
                if (!\community\videolibrary\components\LiveError::isPeerTubeNotFound($exception)) {
                    throw $exception;
                }
                // A removed PeerTube video leaves behind a locally encrypted but
                // unusable RTMP key. Replace it; never try to revive the old key.
                $this->replacedMissingSource = true;
                Yii::warning(['message' => 'PeerTube-Livequelle nicht gefunden; erstelle Ersatzquelle.', 'userId' => (int)Yii::$app->user->id], 'peertube');
            }
        }

        return $this->createLiveSource($source, $client, $form);
    }

    private function createLiveSource(?LiveSource $source, PeerTubeClient $client, LiveForm $form): LiveSource
    {
        $password = VideoPasswordVault::generate();
        $result = $client->createPermanentLive($form->title, (string)$form->description,
            (int)Yii::$app->getModule('peertube')->settings->get('channelId'), $password);
        $video = $result['video']; $live = $result['live'];
        $remoteId = (string)($video['id'] ?? '');
        $source ??= new LiveSource();
        $source->setAttributes([
            'user_id' => Yii::$app->user->id,
            'peertube_id' => (int)$remoteId,
            'peertube_uuid' => (string)($video['uuid'] ?? ''),
            'rtmp_url_encrypted' => VideoPasswordVault::encrypt((string)($live['rtmpsUrl'] ?? $live['rtmpUrl'])),
            'stream_key_encrypted' => VideoPasswordVault::encrypt((string)$live['streamKey']),
            'password_encrypted' => VideoPasswordVault::encrypt($password),
            'created_at' => $source->isNewRecord ? date('Y-m-d H:i:s') : $source->created_at,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$source->save()) {
            try { $client->delete($remoteId); } catch (\Throwable $e) { Yii::error($e, 'peertube'); }
            throw new \RuntimeException('Die dauerhafte Ersatz-Livestream-Quelle konnte nicht gespeichert werden.');
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
        $publicTeamAccess = $session && $session->is_public && AccessPolicy::canStartPublicLive($user);
        if (!$session || !$this->belongsToCurrentContainer($session) || ((int)$session->user_id !== (int)Yii::$app->user->id && !$user->isSystemAdmin() && !$publicTeamAccess)) {
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
