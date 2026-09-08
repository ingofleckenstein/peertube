<?php

namespace community\videolibrary\components;

use RuntimeException;

class DirectUploadTicket
{
    public static function encode(array $payload, string $secret): string
    {
        if (strlen($secret) < 32 || !function_exists('openssl_encrypt')) {
            throw new RuntimeException('Der Direktupload-Schlüssel fehlt oder ist zu kurz.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $encrypted = openssl_encrypt($json, 'aes-256-gcm', hash('sha256', $secret, true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($encrypted === false) {
            throw new RuntimeException('Das Direktupload-Ticket konnte nicht erzeugt werden.');
        }
        return rtrim(strtr(base64_encode($iv . $tag . $encrypted), '+/', '-_'), '=');
    }
}
