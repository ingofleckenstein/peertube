<?php

namespace community\videolibrary\components;

use RuntimeException;
use Yii;
use yii\helpers\FileHelper;

/** Server-side data only; callers must authorize every response separately. */
class RemoteCache
{
    public static function remember(string $key, int $ttl, callable $load)
    {
        $key = 'peertube.remote.v1.' . hash('sha256', $key);
        $cached = Yii::$app->cache->get($key);
        if (is_array($cached)) {
            if (isset($cached['value'])) {
                return $cached['value'];
            }
            throw new RuntimeException('PeerTube-Abruf pausiert nach einem Fehler.');
        }
        $directory = Yii::getAlias('@runtime/peertube-locks');
        FileHelper::createDirectory($directory, 0700);
        $lock = fopen($directory . '/' . $key . '.lock', 'c');
        if (!$lock) {
            throw new RuntimeException('PeerTube-Cache-Sperre nicht verfügbar.');
        }
        // One remote request per key, even when multiple page loads coincide.
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('PeerTube-Abruf läuft bereits.');
        }
        try {
            $cached = Yii::$app->cache->get($key);
            if (is_array($cached)) {
                if (isset($cached['value'])) {
                    return $cached['value'];
                }
                throw new RuntimeException('PeerTube-Abruf pausiert nach einem Fehler.');
            }
            try {
                $value = $load();
                Yii::$app->cache->set($key, ['value' => $value], $ttl);
                return $value;
            } catch (\Throwable $exception) {
                // Do not cache exception bodies: remote errors may include secrets.
                Yii::$app->cache->set($key, ['failed' => true], 60);
                throw $exception;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
