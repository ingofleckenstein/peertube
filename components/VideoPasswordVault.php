<?php

namespace community\videolibrary\components;

use RuntimeException;
use Yii;

class VideoPasswordVault
{
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function encrypt(string $password): string
    {
        return base64_encode(Yii::$app->security->encryptByKey($password, self::key()));
    }

    public static function decrypt(string $ciphertext): string
    {
        $plain = Yii::$app->security->decryptByKey(base64_decode($ciphertext, true) ?: '', self::key());
        if ($plain === false) {
            throw new RuntimeException('Das Video-Passwort konnte nicht entschlüsselt werden.');
        }
        return $plain;
    }

    private static function key(): string
    {
        $secret = (string) Yii::$app->settings->get('secret');
        if ($secret === '') {
            throw new RuntimeException('Der HumHub-Anwendungsschlüssel fehlt.');
        }
        return hash('sha256', 'selfsein-peertube-video-password|' . $secret, true);
    }
}
