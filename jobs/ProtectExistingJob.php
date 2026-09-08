<?php

namespace community\videolibrary\jobs;

use humhub\modules\queue\ActiveJob;
use community\videolibrary\components\PeerTubeClient;
use community\videolibrary\components\VideoPasswordVault;
use community\videolibrary\models\Media;
use Yii;

class ProtectExistingJob extends ActiveJob
{
    public function run(): void
    {
        $client = new PeerTubeClient();
        foreach (Media::find()->where(['or', ['password_encrypted' => null], ['password_encrypted' => '']])->each(20) as $media) {
            try {
                $password = VideoPasswordVault::generate();
                $client->protectExisting((string) $media->peertube_uuid, $password);
                $media->updateAttributes(['password_encrypted' => VideoPasswordVault::encrypt($password), 'sync_status' => 'synced', 'last_sync_error' => null]);
            } catch (\Throwable $exception) {
                $media->updateAttributes(['sync_status' => 'error', 'last_sync_error' => mb_substr($exception->getMessage(), 0, 1000)]);
                Yii::error($exception, 'peertube');
            }
        }
    }
}
