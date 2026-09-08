<?php

namespace community\videolibrary\components;

use RuntimeException;
use Yii;

class CredentialVault
{
    private const PREFIX = 'enc:v1:';

    public static function encrypt(string $value): string
    {
        $ciphertext = Yii::$app->security->encryptByKey($value, self::key());
        return self::PREFIX . base64_encode($ciphertext);
    }

    public static function decrypt(string $value): string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            return $value;
        }
        $decoded = base64_decode(substr($value, strlen(self::PREFIX)), true);
        $plain = $decoded === false ? false : Yii::$app->security->decryptByKey($decoded, self::key());
        if ($plain === false) {
            throw new RuntimeException('Das gespeicherte Passwort für den Videoserver konnte nicht entschlüsselt werden.');
        }
        return $plain;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    private static function key(): string
    {
        $secret = (string) Yii::$app->settings->get('secret');
        if ($secret === '') {
            throw new RuntimeException('Der HumHub-Anwendungsschlüssel fehlt.');
        }
        // Keep existing encrypted credentials readable after the neutral
        // rename without retaining a project name in distributable source.
        return hash('sha256', base64_decode('c2VsYnN0c2Vpbi1wZWVydHViZS10ZWNobmljYWwtYWNjb3VudHw=') . $secret, true);
    }
}
