<?php

namespace selfsein\peertube\controllers;

use humhub\modules\content\components\ContentContainerController;
use selfsein\peertube\components\PeerTubeClient;
use selfsein\peertube\components\VideoPasswordVault;
use selfsein\peertube\models\Media;
use selfsein\peertube\models\UploadForm;
use selfsein\peertube\models\EditForm;
use selfsein\peertube\models\Folder;
use selfsein\peertube\models\LibrarySetting;
use selfsein\peertube\models\PendingOperation;
use selfsein\peertube\components\AdminAlert;
use selfsein\peertube\permissions\ManageMedia;
use humhub\modules\user\models\User;
use humhub\modules\topic\models\Topic;
use humhub\modules\content\widgets\stream\StreamEntryOptions;
use humhub\modules\content\widgets\stream\WallStreamEntryOptions;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\filters\VerbFilter;
use yii\web\Response;
use yii\data\Pagination;
use humhub\modules\space\models\Space;
use selfsein\peertube\components\AccessPolicy;
use selfsein\peertube\components\DirectUploadTicket;
use selfsein\peertube\components\TranscriptService;
use selfsein\peertube\models\SettingsForm;

class MediaController extends ContentContainerController
{
    public function beforeAction($action)
    {
        // A library area explicitly marked for all community members must also be
        // reachable in a non-public Space. The actions below perform their own,
        // media-specific visibility checks.
        if (!Yii::$app->user->isGuest
            && $this->contentContainer instanceof Space
            && in_array($action->id, ['index', 'view', 'password', 'thumbnail', 'transcript'], true)
            && !$this->contentContainer->canAccessPrivateContent(Yii::$app->user->identity)) {
            $this->detachBehavior('containerControllerBehavior');
            $this->subLayout = '@humhub/modules/space/views/space/_layout';
        }

        return parent::beforeAction($action);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => ['delete' => ['post'], 'direct-init' => ['post'], 'direct-finalize' => ['post'], 'direct-cancel' => ['post'], 'create-folder' => ['post'], 'manage-library' => ['post'], 'rename-folder'=>['post'], 'delete-folder'=>['post'], 'folder-visibility'=>['post'], 'unfiled-visibility'=>['post']]];
        return $behaviors;
    }

    public function actionIndex()
    {
        $this->assertModuleEnabled();
        if (Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException();
        }
        $isSpace = $this->contentContainer instanceof Space;
        $memberAccess = $isSpace
            ? $this->contentContainer->canAccessPrivateContent(Yii::$app->user->identity)
            : true;
        $requestedFolder = Yii::$app->request->get('folder');
        $librarySetting = $isSpace
            ? LibrarySetting::forSpace((int) $this->contentContainer->id)
            : (object) ['unfiled_visibility' => 'members'];
        $publicUnfiled = $isSpace && !$requestedFolder && $librarySetting->unfiled_visibility === 'public';
        $hasPublicFolders = $isSpace && Folder::find()->where(['space_id'=>$this->contentContainer->id,'visibility'=>'public'])->exists();
        $publicFolder = $isSpace && $requestedFolder ? Folder::findOne(['id'=>(int)$requestedFolder,'space_id'=>$this->contentContainer->id,'visibility'=>'public']) : null;
        if ($isSpace && !$memberAccess && (($requestedFolder && !$publicFolder) || (!$requestedFolder && !$publicUnfiled && !$hasPublicFolders))) {
            Yii::$app->response->statusCode = 403;
            return $this->render('libraryDenied');
        }
        $query = Media::find()->contentContainer($this->contentContainer);
        $q = trim((string) Yii::$app->request->get('q', ''));
        if ($q !== '') $query->andWhere(['or',['like','title',$q],['like','description',$q],['like','topics',$q]]);
        $folderId = Yii::$app->request->get('folder');
        if ($isSpace && $folderId !== null && $folderId !== '') {
            $query->andWhere(['folder_id'=>(int)$folderId]);
        } elseif ($isSpace && $q === '' && trim((string) Yii::$app->request->get('topic', '')) === '') {
            $query->andWhere(['folder_id'=>null]);
        }
        $uploaderId = Yii::$app->request->get('uploader'); if ($uploaderId !== null && $uploaderId !== '') $query->andWhere(['user_id'=>(int)$uploaderId]);
        $items = $query->orderBy(['created_at' => SORT_DESC])->all();
        $invalidMediaCount = 0;
        if ($isSpace && !$memberAccess && !$publicFolder && !$publicUnfiled) $items = [];
        $topic = trim((string) Yii::$app->request->get('topic', ''));
        if ($topic !== '') {
            $items = array_filter($items, static function (Media $media) use ($topic): bool {
                if (str_starts_with($topic, 'legacy:')) {
                    return in_array(substr($topic, 7), $media->getTopicNames(), true) && !$media->getTopicIds();
                }
                return is_numeric($topic) && in_array((int) $topic, $media->getTopicIds(), true);
            });
        }
        $items = array_values(array_filter($items, function (Media $item) use ($publicFolder, $publicUnfiled, &$invalidMediaCount): bool {
            try {
                $content = $item->content;
                if ($content === null || $content->isNewRecord) {
                    ++$invalidMediaCount;
                    Yii::warning('Medieneintrag ohne gültige HumHub-Content-Verknüpfung: media_id=' . (int) $item->id, 'peertube');
                    return false;
                }

                return $publicFolder || $publicUnfiled
                    || ($content->getStateService()->isPublished() && $content->canView());
            } catch (\Throwable $exception) {
                ++$invalidMediaCount;
                Yii::warning(
                    'Medieneintrag konnte in der Mediathek nicht geprüft werden: media_id=' . (int) $item->id
                    . '; ' . $exception->getMessage(),
                    'peertube'
                );
                return false;
            }
        }));
        $pagination = new Pagination([
            'totalCount' => count($items),
            'pageSize' => 12,
            'pageSizeLimit' => [1, 12],
        ]);
        $items = array_slice($items, $pagination->getOffset(), $pagination->getLimit());
        $this->hydrateThumbnailUrls($items);
        $folderQuery = Folder::find()->where(['space_id'=>$isSpace ? $this->contentContainer->id : -1]); if (!$memberAccess) $folderQuery->andWhere(['visibility'=>'public']);
        $folders = $folderQuery->orderBy(['name'=>SORT_ASC])->all();
        $folderCounts = $isSpace ? Media::find()->select(['folder_id', 'COUNT(*) AS media_count'])->contentContainer($this->contentContainer)->andWhere(['not',['folder_id'=>null]])->groupBy(['folder_id'])->asArray()->all() : [];
        $folderCounts = array_column($folderCounts, 'media_count', 'folder_id');
        $metaQuery=Media::find()->contentContainer($this->contentContainer);
        if (!$memberAccess) $metaQuery->andWhere(['folder_id'=>array_map(static fn($f)=>(int)$f->id,$folders)]);
        $topics = [];
        foreach ($metaQuery->all() as $metaMedia) {
            try {
                if ($metaMedia->content === null || $metaMedia->content->isNewRecord) {
                    continue;
                }
                $ids = $metaMedia->getTopicIds();
                if ($ids) {
                    foreach (Topic::findByContent($metaMedia->content)->all() as $humhubTopic) {
                        $topics[(string) $humhubTopic->id] = $humhubTopic->name;
                    }
                } else {
                    foreach ($metaMedia->getTopicNames() as $legacyTopic) {
                        $topics['legacy:' . $legacyTopic] = $legacyTopic . ' (bisheriges Thema)';
                    }
                }
            } catch (\Throwable $exception) {
                Yii::warning('Themen eines Medieneintrags konnten nicht gelesen werden: media_id=' . (int) $metaMedia->id . '; ' . $exception->getMessage(), 'peertube');
            }
        }
        natcasesort($topics);
        $uploaderQuery=Media::find()->select('user_id')->contentContainer($this->contentContainer);
        if (!$memberAccess) $uploaderQuery->andWhere(['folder_id'=>array_map(static fn($f)=>(int)$f->id,$folders)]);
        $uploaderIds=$uploaderQuery->distinct()->column();
        $uploaders=User::find()->where(['id'=>$uploaderIds])->all(); usort($uploaders, static fn($a,$b)=>strnatcasecmp($a->displayName,$b->displayName));
        return $this->render('index', ['media' => $items, 'pagination'=>$pagination, 'folders'=>$folders, 'folderCounts'=>$folderCounts, 'topics'=>$topics, 'q'=>$q, 'topic'=>$topic, 'folderId'=>$folderId, 'uploaders'=>$uploaders, 'uploaderId'=>$uploaderId, 'memberAccess'=>$memberAccess, 'librarySetting'=>$librarySetting, 'invalidMediaCount'=>$invalidMediaCount]);
    }

    public function actionView($id)
    {
        $this->assertModuleEnabled();
        $media = $this->findMedia($id);
        if (!$media->canBeViewedBy()) {
            throw new ForbiddenHttpException();
        }

        return $this->render('view', [
            'media' => $media,
            'renderOptions' => new StreamEntryOptions([
                'viewContext' => WallStreamEntryOptions::VIEW_CONTEXT_DETAIL,
            ]),
        ]);
    }

    public function actionUpload()
    {
        $this->assertModuleEnabled();
        if (!AccessPolicy::canUpload($this->contentContainer)) {
            Yii::$app->response->statusCode = 403;
            return $this->render('uploadDenied');
        }

        $model = new UploadForm();
        $folders = $this->contentContainer instanceof Space
            ? Folder::find()->where(['space_id'=>$this->contentContainer->id])->orderBy(['name'=>SORT_ASC])->all()
            : [];
        if ($model->load(Yii::$app->request->post())) {
            $model->mediaFile = UploadedFile::getInstance($model, 'mediaFile');
            $model->thumbnailFile = UploadedFile::getInstance($model, 'thumbnailFile');
            if ($model->validate()) {
                $existing = Media::find()->contentContainer($this->contentContainer)->andWhere(['upload_token' => $model->uploadToken])->one();
                if ($existing) {
                    $this->view->info('Dieser Upload wurde bereits verarbeitet.');
                    return $this->redirect($this->contentContainer->createUrl('/peertube/media/index'));
                }
                $pending = new PendingOperation([
                    'operation_token' => $model->uploadToken,
                    'operation_type' => 'upload',
                    'space_id' => $this->contentContainer instanceof Space ? $this->contentContainer->id : null,
                    'user_id' => Yii::$app->user->id,
                    'status' => 'pending',
                    'payload' => json_encode(['title' => $model->title], JSON_UNESCAPED_UNICODE),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                if (!$pending->save()) {
                    $model->addError('mediaFile', 'Der lokale Upload-Auftrag konnte nicht gespeichert werden.');
                    return $this->render('upload', ['model' => $model, 'folders'=>$folders]);
                }
                $remoteId = null;
                try {
                    $settings = Yii::$app->getModule('peertube')->settings;
                    $password = VideoPasswordVault::generate();
                    $thumbnail = $this->thumbnailPayload($model->thumbnailFile, $model->thumbnailData);
                    try {
                        $result = (new PeerTubeClient())->upload(
                            $model->mediaFile->tempName,
                            $model->mediaFile->name,
                            $model->mediaFile->type,
                            $model->title,
                            $model->description ?: '',
                            (int) $settings->get('channelId'),
                            $password,
                            $thumbnail
                        );
                    } finally {
                        $this->releaseThumbnail($thumbnail);
                    }
                    $video = $result['video'] ?? $result;
                    $remoteId = (string) ($video['uuid'] ?? $video['id'] ?? '');
                    (new PeerTubeClient())->verifyVideo($remoteId, $model->title, (string) ($model->description ?: ''));
                    $thumbnailPath = $video['thumbnailPath'] ?? ($video['thumbnail']['path'] ?? '');
                    $media = new Media($this->contentContainer, [
                        'space_id' => $this->contentContainer instanceof Space ? $this->contentContainer->id : null,
                        'user_id' => Yii::$app->user->id,
                        'peertube_id' => $video['id'] ?? null,
                        'peertube_uuid' => $video['uuid'] ?? '',
                        'title' => $model->title,
                        'description' => $model->description,
                        'content_warnings' => json_encode(array_values($model->contentWarnings ?: []), JSON_UNESCAPED_UNICODE),
                        'password_encrypted' => VideoPasswordVault::encrypt($password),
                        'publish_to_stream' => $model->publishToStream ? 1 : 0,
                        'thumbnail_url' => $thumbnailPath ? rtrim($settings->get('baseUrl', ''), '/') . '/' . ltrim($thumbnailPath, '/') : null,
                        'media_type' => preg_match('/audio|mp3|m4a|aac|ogg|wav|flac/i', $model->mediaFile->type . ' ' . $model->mediaFile->extension) ? 'audio' : 'video',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'upload_token' => $model->uploadToken,
                        'sync_status' => 'synced',
                        'topics' => '[]',
                        'folder_id' => $this->validFolderId($model->folderId),
                    ]);
                    $media->setPublishToStream((bool) $model->publishToStream);
                    if (!$media->save()) {
                        throw new \RuntimeException('Der lokale Medieneintrag konnte nicht gespeichert werden.');
                    }
                    try {
                        $media->syncTopics($model->topics);
                    } catch (\Throwable $topicException) {
                        Yii::error($topicException, 'peertube');
                        $this->view->error('Das Medium wurde hochgeladen, aber die HumHub-Themen konnten nicht gespeichert werden. Bitte das Medium bearbeiten und erneut speichern.');
                    }
                    $pending->delete();
                    $this->view->success('Upload abgeschlossen. Das Video wird jetzt verarbeitet.');
                    return $this->redirect($this->contentContainer->createUrl('/peertube/media/index'));
                } catch (\Throwable $exception) {
                    $rollbackFailed = false;
                    if ($remoteId) {
                        try {
                            (new PeerTubeClient())->delete($remoteId);
                        } catch (\Throwable $cleanupException) {
                            $rollbackFailed = true;
                            AdminAlert::raise('Upload-Rollback fehlgeschlagen: Remote-Video ' . $remoteId . ' konnte nicht entfernt werden.', $cleanupException);
                        }
                    }
                    if ($rollbackFailed) {
                        $pending->updateAttributes(['status' => 'rollback_failed', 'last_error' => $this->safeError($exception), 'updated_at' => date('Y-m-d H:i:s')]);
                    } else {
                        $pending->delete();
                    }
                    Yii::error($exception, 'peertube');
                    $model->addError('mediaFile', 'Das Video konnte nicht vollständig gespeichert werden. Bitte erneut versuchen oder die Administration informieren.');
                }
            }
        }
        return $this->render('upload', ['model' => $model, 'folders'=>$folders]);
    }

    public function actionDirectInit()
    {
        $this->assertModuleEnabled();
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!AccessPolicy::canUpload($this->contentContainer)) {
            Yii::$app->response->statusCode = 403;
            return ['success' => false, 'message' => 'Du hast keine Berechtigung, Videos in diesem Bereich zu veröffentlichen.'];
        }

        $model = new UploadForm();
        if (!$model->load(Yii::$app->request->post()) || !$model->validate([
            'title', 'description', 'topics', 'folderId', 'thumbnailTimestamp',
            'consentConfirmed', 'contentWarnings', 'publishToStream', 'uploadToken',
        ])) {
            Yii::$app->response->statusCode = 422;
            return ['success' => false, 'message' => $this->firstModelError($model)];
        }

        $filename = trim((string) Yii::$app->request->post('fileName', ''));
        $mimeType = trim((string) Yii::$app->request->post('fileType', 'application/octet-stream'));
        $size = (int) Yii::$app->request->post('fileSize', 0);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac'];
        if ($filename === '' || $size < 1 || !in_array($extension, $allowed, true)) {
            Yii::$app->response->statusCode = 422;
            return ['success' => false, 'message' => 'Bitte eine unterstützte Video- oder Audiodatei auswählen.'];
        }
        $secret = SettingsForm::getDirectUploadSecret();
        if (strlen($secret) < 32) {
            Yii::$app->response->statusCode = 503;
            return ['success' => false, 'message' => 'Der Direktupload ist noch nicht vollständig eingerichtet. Bitte die Administration informieren.'];
        }
        $existing = PendingOperation::findOne(['operation_token' => $model->uploadToken, 'user_id' => Yii::$app->user->id]);
        if ($existing) {
            $existing->delete();
        }
        PendingOperation::deleteAll([
            'and',
            ['operation_type' => 'direct_upload'],
            ['<>', 'status', 'rollback_failed'],
            ['<', 'created_at', date('Y-m-d H:i:s', time() - 86400)],
        ]);

        $password = VideoPasswordVault::generate();
        $payload = [
            'container_guid' => (string) $this->contentContainer->guid,
            'title' => (string) $model->title,
            'description' => (string) $model->description,
            'contentWarnings' => array_values($model->contentWarnings ?: []),
            'publishToStream' => (bool) $model->publishToStream,
            'topics' => $model->topics ?: [],
            'folderId' => $this->validFolderId($model->folderId),
            'filename' => $filename,
            'mimeType' => $mimeType,
            'size' => $size,
            'password_encrypted' => VideoPasswordVault::encrypt($password),
        ];
        $pending = new PendingOperation([
            'operation_token' => $model->uploadToken,
            'operation_type' => 'direct_upload',
            'space_id' => $this->contentContainer instanceof Space ? $this->contentContainer->id : null,
            'user_id' => Yii::$app->user->id,
            'status' => 'initializing',
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$pending->save()) {
            Yii::$app->response->statusCode = 500;
            return ['success' => false, 'message' => 'Der Uploadauftrag konnte nicht angelegt werden.'];
        }

        try {
            $session = (new PeerTubeClient())->initResumableUpload(
                $filename, $mimeType, $size, $model->title, (string) $model->description,
                (int) Yii::$app->getModule('peertube')->settings->get('channelId'), $password
            );
            $payload['uploadId'] = $session['uploadId'];
            $pending->updateAttributes([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'uploading',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $origin = rtrim(Yii::$app->request->hostInfo, '/');
            $ticket = DirectUploadTicket::encode([
                'u' => $session['uploadId'], 'a' => $session['accessToken'], 't' => $size,
                'o' => $origin, 'exp' => time() + 7200,
            ], $secret);
            $baseUrl = rtrim((string) Yii::$app->getModule('peertube')->settings->get('baseUrl', ''), '/');
            return [
                'success' => true,
                'uploadToken' => $model->uploadToken,
                'uploadUrl' => $baseUrl . '/plugins/humhub-permalinks/router/direct-upload?ticket=' . rawurlencode($ticket),
                'chunkSize' => 8 * 1024 * 1024,
            ];
        } catch (\Throwable $exception) {
            $pending->delete();
            Yii::error($exception, 'peertube');
            Yii::$app->response->statusCode = 502;
            return ['success' => false, 'message' => 'Der Videoserver konnte den Upload nicht vorbereiten.'];
        }
    }

    public function actionDirectFinalize()
    {
        $this->assertModuleEnabled();
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!AccessPolicy::canUpload($this->contentContainer)) {
            Yii::$app->response->statusCode = 403;
            return ['success' => false, 'message' => 'Du hast keine Berechtigung, Videos in diesem Bereich zu veröffentlichen.'];
        }
        $token = (string) Yii::$app->request->post('uploadToken', '');
        $pending = PendingOperation::findOne(['operation_token' => $token, 'operation_type' => 'direct_upload', 'user_id' => Yii::$app->user->id]);
        $payload = $pending ? json_decode((string) $pending->payload, true) : null;
        if (!$pending || !is_array($payload) || !hash_equals((string) ($payload['container_guid'] ?? ''), (string) $this->contentContainer->guid)) {
            throw new NotFoundHttpException();
        }
        $remote = Yii::$app->request->post('video', []);
        $remoteId = (string) ($remote['uuid'] ?? $remote['id'] ?? '');
        $proof = (string) Yii::$app->request->post('completionProof', '');
        $expectedProof = hash_hmac(
            'sha256',
            (string) ($payload['uploadId'] ?? '') . "\0" . (string) ($remote['uuid'] ?? '') . "\0" . (string) ($remote['id'] ?? ''),
            SettingsForm::getDirectUploadSecret()
        );
        if ($remoteId === '' || $proof === '' || !hash_equals($expectedProof, $proof)) {
            Yii::$app->response->statusCode = 422;
            return ['success' => false, 'message' => 'Der Videoserver hat den Abschluss nicht eindeutig bestätigt.'];
        }

        $transaction = null;
        try {
            $password = VideoPasswordVault::decrypt((string) $payload['password_encrypted']);
            $thumbnailModel = new UploadForm();
            $thumbnailModel->load(Yii::$app->request->post());
            $thumbnailModel->thumbnailFile = UploadedFile::getInstance($thumbnailModel, 'thumbnailFile');
            $thumbnail = $this->thumbnailPayload($thumbnailModel->thumbnailFile, $thumbnailModel->thumbnailData);
            try {
                $client = new PeerTubeClient();
                // The signed completion proof already binds this video to the
                // upload session. PeerTube may trim or Unicode-normalize the
                // initial metadata, so apply our canonical values before the
                // strict post-update verification.
                $client->getVideo($remoteId);
                $client->update($remoteId, (string) $payload['title'], (string) $payload['description'], $password, $thumbnail);
                $verifiedVideo = $client->verifyVideo($remoteId, (string) $payload['title'], (string) $payload['description']);
            } finally {
                $this->releaseThumbnail($thumbnail);
            }
            $transaction = Yii::$app->db->beginTransaction();
            $media = new Media($this->contentContainer, [
                'space_id' => $this->contentContainer instanceof Space ? $this->contentContainer->id : null,
                'user_id' => Yii::$app->user->id,
                'peertube_id' => isset($remote['id']) ? (int) $remote['id'] : null,
                'peertube_uuid' => (string) ($remote['uuid'] ?? $remoteId),
                'title' => (string) $payload['title'],
                'description' => (string) $payload['description'],
                'content_warnings' => json_encode(array_values($payload['contentWarnings'] ?? []), JSON_UNESCAPED_UNICODE),
                'password_encrypted' => (string) $payload['password_encrypted'],
                'publish_to_stream' => !empty($payload['publishToStream']) ? 1 : 0,
                'thumbnail_url' => !empty($verifiedVideo['thumbnailPath'])
                    ? rtrim((string) Yii::$app->getModule('peertube')->settings->get('baseUrl', ''), '/') . '/' . ltrim((string) $verifiedVideo['thumbnailPath'], '/')
                    : null,
                'media_type' => preg_match('/audio|mp3|m4a|aac|ogg|wav|flac/i', ($payload['mimeType'] ?? '') . ' ' . ($payload['filename'] ?? '')) ? 'audio' : 'video',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'upload_token' => $token,
                'sync_status' => 'synced',
                'topics' => '[]',
                'folder_id' => $this->validFolderId($payload['folderId'] ?? null),
            ]);
            $media->setPublishToStream(!empty($payload['publishToStream']));
            if (!$media->save()) {
                throw new \RuntimeException('Der lokale Medieneintrag konnte nicht gespeichert werden.');
            }
            $media->syncTopics($payload['topics'] ?? []);
            $pending->delete();
            $transaction->commit();
            return ['success' => true, 'redirectUrl' => $this->contentContainer->createUrl('/peertube/media/index')];
        } catch (\Throwable $exception) {
            if ($transaction && $transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::error($exception, 'peertube');
            try {
                (new PeerTubeClient())->delete($remoteId);
                $pending->delete();
            } catch (\Throwable $cleanupException) {
                $pending->updateAttributes(['status' => 'rollback_failed', 'last_error' => $this->safeError($exception), 'updated_at' => date('Y-m-d H:i:s')]);
                AdminAlert::raise('Direktupload konnte nach einem Finalisierungsfehler nicht entfernt werden: ' . $remoteId, $cleanupException);
            }
            Yii::$app->response->statusCode = 500;
            return ['success' => false, 'message' => 'Das Video wurde übertragen, konnte aber nicht in der Community gespeichert werden. Die Administration wurde informiert.'];
        }
    }

    public function actionDirectCancel()
    {
        $this->assertModuleEnabled();
        Yii::$app->response->format = Response::FORMAT_JSON;
        $token = (string) Yii::$app->request->post('uploadToken', '');
        PendingOperation::deleteAll(['operation_token' => $token, 'operation_type' => 'direct_upload', 'user_id' => Yii::$app->user->id]);
        return ['success' => true];
    }

    public function actionEdit($id)
    {
        $this->assertModuleEnabled();
        $media = $this->findMedia($id);
        if (!$this->canManage($media)) {
            throw new ForbiddenHttpException();
        }
        $model = new EditForm([
            'title' => $media->title,
            'description' => $media->description,
            'contentWarnings' => $media->getContentWarningKeys(),
            'publishToStream' => (bool) $media->publish_to_stream,
            'topics' => Topic::findByContent($media->content), 'folderId'=>$media->folder_id,
        ]);
        $loaded = $model->load(Yii::$app->request->post());
        if ($loaded) {
            $model->thumbnailFile = UploadedFile::getInstance($model, 'thumbnailFile');
        }
        if ($loaded && $model->validate()) {
            $old = [
                'title' => $media->title,
                'description' => (string) $media->description,
                'content_warnings' => $media->content_warnings,
                'publish_to_stream' => $media->publish_to_stream,
                'stream_channel' => $media->content->stream_channel,
                'folder_id' => $media->folder_id,
            ];
            $oldTopics = Topic::findByContent($media->content)->all();
            $pending = new PendingOperation([
                'operation_token' => bin2hex(random_bytes(32)),
                'operation_type' => 'edit',
                'space_id' => $this->contentContainer instanceof Space ? $this->contentContainer->id : null,
                'user_id' => Yii::$app->user->id,
                'status' => 'pending',
                'payload' => json_encode(['media_id' => (int) $media->id, 'old' => $old], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$pending->save()) {
                $model->addError('title', 'Der lokale Bearbeitungsauftrag konnte nicht gespeichert werden.');
                $folders = $this->contentContainer instanceof Space
                    ? Folder::find()->where(['space_id'=>$this->contentContainer->id])->orderBy(['name'=>SORT_ASC])->all()
                    : [];
                return $this->render('edit', ['model' => $model, 'media' => $media, 'folders'=>$folders]);
            }
            $password = null;
            $thumbnailWasChanged = $model->thumbnailFile !== null;
            $remoteUpdateStarted = false;
            try {
                $password = $this->ensurePassword($media);
                $media->title = $model->title;
                $media->description = $model->description;
                $media->content_warnings = json_encode(array_values($model->contentWarnings ?: []), JSON_UNESCAPED_UNICODE);
                $media->setPublishToStream((bool) $model->publishToStream);
                $media->sync_status = 'pending';
                $media->last_sync_error = null;
                $media->updated_at = date('Y-m-d H:i:s');
                $media->folder_id = $this->validFolderId($model->folderId);
                if (!$media->save()) {
                    throw new \RuntimeException('Die lokalen Änderungen konnten nicht gespeichert werden.');
                }
                $media->syncTopics($model->topics);
                $thumbnail = $this->thumbnailPayload($model->thumbnailFile);
                try {
                    $client = new PeerTubeClient();
                    $remoteUpdateStarted = true;
                    $client->update($media->peertube_uuid, $model->title, (string) $model->description, $password, $thumbnail);
                    $client->verifyVideo($media->peertube_uuid, $model->title, (string) $model->description);
                } finally {
                    $this->releaseThumbnail($thumbnail);
                }
                $media->sync_status = 'synced';
                $media->last_sync_error = null;
                $media->save(false, ['sync_status', 'last_sync_error']);
                $pending->delete();
                $this->view->success('Das Video wurde aktualisiert.');
                return $this->redirect($this->contentContainer->createUrl('/peertube/media/index'));
            } catch (\Throwable $exception) {
                $safe = $this->safeError($exception);
                $rollbackErrors = [];
                try {
                    $media->title = $old['title'];
                    $media->description = $old['description'];
                    $media->content_warnings = $old['content_warnings'];
                    $media->publish_to_stream = $old['publish_to_stream'];
                    $media->streamChannel = $old['stream_channel'];
                    $media->folder_id = $old['folder_id'];
                    $media->sync_status = 'synced';
                    $media->last_sync_error = null;
                    if (!$media->save(false)) throw new \RuntimeException('Lokale Rücksetzung fehlgeschlagen.');
                    Topic::attach($media->content, $oldTopics);
                } catch (\Throwable $rollbackException) {
                    $rollbackErrors[] = $rollbackException->getMessage();
                }
                if ($password !== null) {
                    try {
                        $rollbackClient = new PeerTubeClient();
                        $rollbackClient->update($media->peertube_uuid, $old['title'], $old['description'], $password);
                        $rollbackClient->verifyVideo($media->peertube_uuid, $old['title'], $old['description']);
                    } catch (\Throwable $rollbackException) {
                        $rollbackErrors[] = $rollbackException->getMessage();
                    }
                }
                if ($thumbnailWasChanged && $remoteUpdateStarted) {
                    $rollbackErrors[] = 'Das vorherige PeerTube-Vorschaubild konnte nicht automatisch wiederhergestellt werden.';
                }
                if ($rollbackErrors) {
                    $rollbackMessage = $safe . ' Rücksetzung fehlgeschlagen: ' . implode(' | ', $rollbackErrors);
                    $media->updateAttributes(['sync_status' => 'error', 'last_sync_error' => mb_substr($rollbackMessage, 0, 1000)]);
                    $pending->updateAttributes(['status' => 'rollback_failed', 'last_error' => mb_substr($rollbackMessage, 0, 2000), 'updated_at' => date('Y-m-d H:i:s')]);
                    AdminAlert::raise($rollbackMessage, $exception);
                } else {
                    $pending->delete();
                }
                Yii::error($exception, 'peertube');
                $model->addError('title', $rollbackErrors
                    ? 'Das Video konnte nicht vollständig aktualisiert werden. Die Administration wurde informiert.'
                    : 'Das Video konnte nicht aktualisiert werden. Die Änderungen wurden zurückgesetzt.');
            }
        }
        $folders = $this->contentContainer instanceof Space
            ? Folder::find()->where(['space_id'=>$this->contentContainer->id])->orderBy(['name'=>SORT_ASC])->all()
            : [];
        return $this->render('edit', ['model' => $model, 'media' => $media, 'folders'=>$folders]);
    }

    public function actionCreateFolder()
    {
        $this->assertModuleEnabled();
        if (!($this->contentContainer instanceof Space)) throw new NotFoundHttpException();
        if (!$this->canManageSpace()) throw new ForbiddenHttpException();
        $name=trim((string)Yii::$app->request->post('name'));
        if ($name !== '') {
            $folder = new Folder(['space_id'=>$this->contentContainer->id,'name'=>$name,'created_by'=>Yii::$app->user->id,'visibility'=>'members','created_at'=>date('Y-m-d H:i:s')]);
            if ($folder->hasAttribute('icon')) {
                $folder->icon = 'folder';
            }
            $folder->save() ? $this->view->success('Ordner erstellt.') : $this->view->error('Der Ordner konnte nicht erstellt werden oder existiert bereits.');
        }
        return $this->redirect($this->contentContainer->createUrl('/peertube/media/index'));
    }
    public function actionManageLibrary()
    {
        $this->assertModuleEnabled();
        if (!($this->contentContainer instanceof Space)) throw new NotFoundHttpException();
        $folderData = (array) Yii::$app->request->post('folders', []);
        $saved = 0;
        foreach ($folderData as $id => $values) {
            $folder = $this->managedFolder((int) $id);
            $folder->name = trim((string) ($values['name'] ?? $folder->name));
            $folder->visibility = ($values['visibility'] ?? '') === 'public' ? 'public' : 'members';
            if ($folder->hasAttribute('icon')) {
                $requestedIcon = (string) ($values['icon'] ?? 'folder');
                $folder->icon = isset(Folder::iconOptions()[$requestedIcon]) ? $requestedIcon : 'folder';
            }
            if (!$folder->save()) {
                $this->view->error('„' . $folder->name . '“ konnte nicht gespeichert werden. Der Name ist eventuell bereits vergeben.');
                return $this->redirect($this->contentContainer->createUrl('/peertube/media/index'));
            }
            $saved++;
        }
        if ($this->canManageSpace()) {
            $setting = LibrarySetting::forSpace((int) $this->contentContainer->id);
            if ($setting instanceof LibrarySetting) {
                $setting->unfiled_visibility = Yii::$app->request->post('unfiled_visibility') === 'public' ? 'public' : 'members';
                $setting->save(false, ['unfiled_visibility']);
            }
        }
        $this->view->success($saved > 0 ? 'Änderungen an der Mediathek wurden gespeichert.' : 'Sichtbarkeit wurde gespeichert.');
        return $this->redirect($this->contentContainer->createUrl('/peertube/media/index'));
    }
    public function actionRenameFolder($id) { $f=$this->managedFolder($id); $f->name=trim((string)Yii::$app->request->post('name')); $f->save() ? $this->view->success('Ordner umbenannt.') : $this->view->error('Name ungültig oder bereits vergeben.'); return $this->redirect($this->contentContainer->createUrl('/peertube/media/index')); }
    public function actionFolderVisibility($id) { $f=$this->managedFolder($id); $f->visibility=Yii::$app->request->post('visibility')==='public'?'public':'members'; $f->save(false,['visibility']); $this->view->success('Sichtbarkeit geändert.'); return $this->redirect($this->contentContainer->createUrl('/peertube/media/index')); }
    public function actionDeleteFolder($id) { $f=$this->managedFolder($id); $f->delete(); $this->view->success('Ordner gelöscht. Die Videos sind jetzt ohne Ordner.'); return $this->redirect($this->contentContainer->createUrl('/peertube/media/index')); }
    public function actionUnfiledVisibility() { $this->assertModuleEnabled(); if(!$this->canManageSpace()) throw new ForbiddenHttpException(); $s=LibrarySetting::forSpace((int)$this->contentContainer->id); if(!($s instanceof LibrarySetting)){ $this->view->error('Bitte zuerst in der Modulkonfiguration die Datenbank aktualisieren.'); } else { $s->unfiled_visibility=Yii::$app->request->post('visibility')==='public'?'public':'members'; $s->save(); $this->view->success('Sichtbarkeit gespeichert.'); } return $this->redirect($this->contentContainer->createUrl('/peertube/media/index')); }
    private function managedFolder($id): Folder { $this->assertModuleEnabled(); if (!($this->contentContainer instanceof Space)) throw new NotFoundHttpException(); $f=Folder::findOne(['id'=>(int)$id,'space_id'=>$this->contentContainer->id]); if(!$f) throw new NotFoundHttpException(); if(!$this->canManageSpace() && (int)$f->created_by!==(int)Yii::$app->user->id) throw new ForbiddenHttpException(); return $f; }
    private function validFolderId($id): ?int
    {
        if (!($this->contentContainer instanceof Space)) return null;
        if (!$id) return null;
        $folder=Folder::findOne(['id'=>(int)$id,'space_id'=>$this->contentContainer->id]);
        return $folder ? (int)$folder->id : null;
    }
    private function canManageSpace(): bool
    {
        if (!($this->contentContainer instanceof Space)) return false;
        if (Yii::$app->user->isGuest) return false; $u=Yii::$app->user->identity;
        return $u->isSystemAdmin() || $u->canManageAllContent() || $this->contentContainer->can(ManageMedia::class);
    }

    public function actionThumbnail($id)
    {
        $this->assertModuleEnabled();
        Yii::$app->response->headers->set('Cache-Control', 'no-store, private');
        $media = $this->findMedia($id);
        if (!$media->canBeViewedBy()) {
            throw new ForbiddenHttpException();
        }
        try {
            $this->hydrateThumbnailUrls([$media]);
            $image = \selfsein\peertube\components\ThumbnailCache::get(
                (string) $media->thumbnail_url,
                (string) Yii::$app->getModule('peertube')->settings->get('baseUrl', ''),
                (string) $media->updated_at
            );
        } catch (\Throwable $exception) {
            // No remote redirect fallback: that would defeat the request limit.
            throw new NotFoundHttpException('Vorschaubild derzeit nicht verfügbar.');
        }
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', $image['mime']);
        Yii::$app->response->headers->set('X-Content-Type-Options', 'nosniff');
        return $image['body'];
    }

    public function actionPassword($id)
    {
        $this->assertModuleEnabled();
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store, private');
        Yii::$app->response->headers->set('Pragma', 'no-cache');

        $media = Media::find()->contentContainer($this->contentContainer)->andWhere([Media::tableName() . '.id' => (int) $id])->one();
        $canView = $media && $media->canBeViewedBy();
        if (!$canView) {
            throw new ForbiddenHttpException();
        }
        if (!$media->password_encrypted) {
            $this->ensurePassword($media);
        }
        return ['password' => VideoPasswordVault::decrypt((string) $media->password_encrypted)];
    }

    /**
     * The browser uses this endpoint both to start the asynchronous PeerTube
     * transcription on first view and to poll its status. It never exposes the
     * PeerTube password or a direct caption URL.
     */
    public function actionTranscript($id)
    {
        $this->assertModuleEnabled();
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store, private');
        Yii::$app->response->headers->set('Pragma', 'no-cache');

        $media = Media::find()->contentContainer($this->contentContainer)->andWhere([Media::tableName() . '.id' => (int) $id])->one();
        if (!$media || !$media->canBeViewedBy()) {
            throw new ForbiddenHttpException();
        }

        return (new TranscriptService())->get($media);
    }

    public function actionDelete($id)
    {
        $this->assertModuleEnabled();
        $media = $this->findMedia($id);
        if (!$this->canManage($media)) {
            throw new ForbiddenHttpException();
        }
        if (!$media->delete()) {
            $this->view->error($media->getFirstError('peertube_uuid') ?: 'Das Medium konnte nicht gelöscht werden.');
            return $this->redirect($this->contentContainer->createUrl('/peertube/media/index'));
        }
        $this->view->success('Medium gelöscht.');
        return $this->redirect($this->contentContainer->createUrl('/peertube/media/index'));
    }

    private function findMedia($id): Media
    {
        $media = Media::find()->contentContainer($this->contentContainer)->andWhere([Media::tableName() . '.id' => (int) $id])->one();
        if (!$media) {
            throw new NotFoundHttpException();
        }
        return $media;
    }

    private function canManage(Media $media): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        $user = Yii::$app->user->identity;
        return (int) $media->user_id === (int) Yii::$app->user->id
            || $user->isSystemAdmin()
            || $user->canManageAllContent()
            || ($this->contentContainer instanceof Space && $this->contentContainer->can(ManageMedia::class));
    }

    private function ensurePassword(Media $media): string
    {
        if ($media->password_encrypted) {
            return VideoPasswordVault::decrypt((string) $media->password_encrypted);
        }
        $password = VideoPasswordVault::generate();
        (new PeerTubeClient())->protectExisting($media->peertube_uuid, $password);
        $media->updateAttributes(['password_encrypted' => VideoPasswordVault::encrypt($password)]);
        return $password;
    }

    private function safeError(\Throwable $exception): string
    {
        $message = preg_replace('/(access_token|password|client_secret)\s*[=:]\s*[^\s,&]+/i', '$1=[geschützt]', $exception->getMessage());
        return mb_substr('Synchronisierung fehlgeschlagen: ' . $message, 0, 1000);
    }

    private function firstModelError($model): string
    {
        foreach ($model->getFirstErrors() as $error) {
            return (string) $error;
        }
        return 'Bitte überprüfe die Eingaben.';
    }

    /**
     * Older module versions did not persist PeerTube's real thumbnailPath.
     * PeerTube generates the filename, so it cannot safely be derived from the UUID.
     */
    private function hydrateThumbnailUrls(array $mediaItems): void
    {
        $client = null;
        $baseUrl = rtrim((string) Yii::$app->getModule('peertube')->settings->get('baseUrl', ''), '/');
        foreach ($mediaItems as $media) {
            if (!$media instanceof Media || trim((string) $media->thumbnail_url) !== '') {
                continue;
            }
            try {
                $client ??= new PeerTubeClient();
                $video = $client->getCachedVideo((string) $media->peertube_uuid);
                $path = (string) ($video['thumbnailPath'] ?? $video['previewPath'] ?? '');
                if ($path === '' || !str_starts_with($path, '/')) {
                    throw new \RuntimeException('PeerTube hat keinen gültigen Thumbnail-Pfad geliefert.');
                }
                $media->updateAttributes(['thumbnail_url' => $baseUrl . $path]);
                $media->thumbnail_url = $baseUrl . $path;
            } catch (\Throwable $exception) {
                Yii::warning(
                    'Vorschaubild konnte nicht synchronisiert werden: media_id=' . (int) $media->id
                    . '; ' . $exception->getMessage(),
                    'peertube'
                );
            }
        }
    }

    private function thumbnailPayload(?UploadedFile $file, ?string $data = null): ?array
    {
        if ($file) {
            return ['path' => $file->tempName, 'mimeType' => $file->type ?: 'image/jpeg', 'name' => $file->name, 'temporary' => false];
        }
        if (!$data) {
            return null;
        }
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $data, $matches)) {
            throw new \RuntimeException('Das erzeugte Vorschaubild ist ungültig.');
        }
        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) > 10 * 1024 * 1024) {
            throw new \RuntimeException('Das erzeugte Vorschaubild ist ungültig oder zu groß.');
        }
        $path = tempnam(Yii::getAlias('@runtime'), 'pt-thumb-');
        if ($path === false || file_put_contents($path, $binary) === false) {
            throw new \RuntimeException('Das Vorschaubild konnte nicht zwischengespeichert werden.');
        }
        return ['path' => $path, 'mimeType' => 'image/' . $matches[1], 'name' => 'thumbnail.' . ($matches[1] === 'jpeg' ? 'jpg' : $matches[1]), 'temporary' => true];
    }

    private function releaseThumbnail(?array $thumbnail): void
    {
        if ($thumbnail && !empty($thumbnail['temporary']) && is_file($thumbnail['path'])) {
            @unlink($thumbnail['path']);
        }
    }

    private function assertModuleEnabled(): void
    {
        if (!$this->contentContainer || !$this->contentContainer->moduleManager->isEnabled('peertube')) {
            throw new NotFoundHttpException();
        }
    }
}
