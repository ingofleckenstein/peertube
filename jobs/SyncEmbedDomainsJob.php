<?php

namespace community\videolibrary\jobs;

use humhub\modules\queue\ActiveJob;
use community\videolibrary\components\PeerTubeClient;
use community\videolibrary\models\Media;
use Yii;

class SyncEmbedDomainsJob extends ActiveJob
{
    public function run(): void
    {
        $client = new PeerTubeClient();
        foreach (Media::find()->each(20) as $media) {
            try {
                $client->refreshEmbedPrivacy((string) $media->peertube_uuid);
                $media->updateAttributes(['sync_status' => 'synced', 'last_sync_error' => null]);
            } catch (\Throwable $exception) {
                $media->updateAttributes(['sync_status' => 'error', 'last_sync_error' => mb_substr($exception->getMessage(), 0, 1000)]);
                Yii::error($exception, 'peertube');
            }
        }
    }
}
