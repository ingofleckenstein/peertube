<?php

namespace community\videolibrary\components;

use humhub\modules\user\models\User;
use community\videolibrary\models\Media;
use community\videolibrary\models\UserPeerTubePlaylist;

/** Keeps the technical humhub_uploads account tidy without exposing playlists publicly. */
final class UserPlaylistService
{
    public static function add(Media $media): void
    {
        if (!$media->user_id || !$media->peertube_uuid) return;
        $playlist = UserPeerTubePlaylist::findOne(['user_id' => $media->user_id]);
        if (!$playlist) {
            $user = User::findOne((int) $media->user_id);
            $name = trim((string) ($user?->displayName ?? 'Unbekannt'));
            $playlist = new UserPeerTubePlaylist(['user_id' => $media->user_id, 'title' => 'HumHub · ' . mb_substr($name, 0, 100), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            $remote = (new PeerTubeClient())->createPlaylist($playlist->title, 3);
            $playlist->peertube_playlist_id = (int) ($remote['id'] ?? 0);
            $playlist->peertube_playlist_uuid = (string) ($remote['uuid'] ?? '');
            if (!$playlist->save() || !$playlist->peertube_playlist_id) throw new \RuntimeException('Die persönliche PeerTube-Playlist konnte nicht angelegt werden.');
        }
        $client = new PeerTubeClient();
        foreach (($client->playlistVideos((int) $playlist->peertube_playlist_id)['data'] ?? []) as $item) {
            if (($item['video']['uuid'] ?? '') === $media->peertube_uuid) return;
        }
        $client->addVideoToPlaylist((int) $playlist->peertube_playlist_id, (string) $media->peertube_uuid);
    }
}
