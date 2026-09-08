<?php

namespace community\videolibrary\jobs;

use humhub\modules\queue\ActiveJob;
use community\videolibrary\components\AdminAlert;
use community\videolibrary\components\UserPlaylistService;
use community\videolibrary\models\Media;
use Yii;

/** One-off, idempotent backfill for videos uploaded before user playlists existed. */
class SyncUserPlaylistsJob extends ActiveJob
{
    public function run(): void
    {
        foreach (Media::find()->orderBy(['id' => SORT_ASC])->each(100) as $media) {
            try {
                UserPlaylistService::add($media);
            } catch (\Throwable $exception) {
                Yii::error($exception, 'peertube');
                AdminAlert::raise('Bestehendes Video konnte nicht in eine persönliche PeerTube-Playlist einsortiert werden: Medien-ID ' . $media->id, $exception);
            }
        }
    }
}
