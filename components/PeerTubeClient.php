<?php

namespace selfsein\peertube\components;

use RuntimeException;
use selfsein\peertube\models\SettingsForm;
use Yii;
use selfsein\peertube\components\CredentialVault;

class PeerTubeClient
{
    private $baseUrl;
    private $username;
    private $password;
    private $accessTokenCache;

    public function __construct()
    {
        $settings = Yii::$app->getModule('peertube')->settings;
        $this->baseUrl = rtrim($settings->get('baseUrl', ''), '/');
        $this->username = $settings->get('username', '');
        $this->password = CredentialVault::decrypt((string) $settings->get('password', ''));
    }

    public function testConnection(): array
    {
        $config = $this->request('GET', '/api/v1/config');
        $this->accessToken();
        return $config;
    }

    public function upload(string $path, string $originalName, string $mimeType, string $title, string $description, int $channelId, string $videoPassword, ?array $thumbnail = null): array
    {
        $token = $this->accessToken();
        $fields = [
            'videofile' => new \CURLFile($path, $mimeType ?: 'application/octet-stream', $originalName),
            'name' => $title,
            'channelId' => (string) $channelId,
            'privacy' => '5',
            'videoPasswords[0]' => $videoPassword,
            'commentsPolicy' => '2',
            'downloadEnabled' => 'false',
            'waitTranscoding' => 'false',
        ];
        if (trim($description) !== '') {
            $fields['description'] = $description;
        }
        if ($thumbnail) {
            $fields['thumbnailfile'] = new \CURLFile($thumbnail['path'], $thumbnail['mimeType'], $thumbnail['name']);
        }

        $result = $this->request('POST', '/api/v1/videos/upload', $fields, $token, true);
        $video = $result['video'] ?? $result;
        $id = (string) ($video['uuid'] ?? $video['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('PeerTube hat keine Video-ID zurückgegeben.');
        }
        $this->restrictEmbedToHumHub($id, $token);
        return $result;
    }

    public function initResumableUpload(string $filename, string $mimeType, int $size, string $title, string $description, int $channelId, string $videoPassword): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Die PHP-cURL-Erweiterung fehlt.');
        }
        $token = $this->accessToken();
        $payload = [
            'filename' => $filename,
            'name' => $title,
            'channelId' => $channelId,
            'privacy' => 5,
            'videoPasswords' => [$videoPassword],
            'commentsPolicy' => 2,
            'downloadEnabled' => false,
            'waitTranscoding' => false,
        ];
        if (trim($description) !== '') {
            $payload['description'] = $description;
        }
        $location = '';
        $handle = curl_init($this->baseUrl . '/api/v1/videos/upload-resumable');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-Upload-Content-Length: ' . $size,
                'X-Upload-Content-Type: ' . ($mimeType ?: 'application/octet-stream'),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$location): int {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                }
                return strlen($header);
            },
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($error !== '' || !in_array($status, [200, 201], true)) {
            $decoded = json_decode((string) $body, true);
            $message = is_array($decoded) ? ($decoded['error'] ?? $decoded['message'] ?? json_encode($decoded)) : $body;
            throw new RuntimeException('PeerTube Direktupload (' . $status . '): ' . ($message ?: $error));
        }
        $query = parse_url($location, PHP_URL_QUERY);
        parse_str((string) $query, $parameters);
        $uploadId = (string) ($parameters['upload_id'] ?? '');
        if ($uploadId === '' || !preg_match('/^[a-zA-Z0-9-]{16,128}$/', $uploadId)) {
            throw new RuntimeException('PeerTube hat keine gültige Upload-Sitzung zurückgegeben.');
        }
        return ['uploadId' => $uploadId, 'accessToken' => $token];
    }

    public function delete(string $id): void
    {
        try {
            $this->request('DELETE', '/api/v1/videos/' . rawurlencode($id), null, $this->accessToken());
        } catch (RuntimeException $exception) {
            if (strpos($exception->getMessage(), 'PeerTube API (404)') === false) {
                throw $exception;
            }
        }
    }

    public function update(string $id, string $title, string $description, string $videoPassword, ?array $thumbnail = null): void
    {
        $token = $this->accessToken();
        $fields = [
            'name' => $title,
            'privacy' => '5',
            'videoPasswords[0]' => $videoPassword,
            'commentsPolicy' => '2',
            'downloadEnabled' => 'false',
        ];
        if (trim($description) !== '') {
            $fields['description'] = $description;
        }
        if ($thumbnail) {
            $fields['thumbnailfile'] = new \CURLFile($thumbnail['path'], $thumbnail['mimeType'], $thumbnail['name']);
        }
        $this->request('PUT', '/api/v1/videos/' . rawurlencode($id), $fields, $token, true);
        $this->restrictEmbedToHumHub($id, $token);
    }

    public function protectExisting(string $id, string $videoPassword): void
    {
        $token = $this->accessToken();
        $this->request('PUT', '/api/v1/videos/' . rawurlencode($id), [
            'privacy' => '5',
            'videoPasswords[0]' => $videoPassword,
            'downloadEnabled' => 'false',
        ], $token, true);
        $this->restrictEmbedToHumHub($id, $token);
    }

    public function refreshEmbedPrivacy(string $id): void
    {
        $this->restrictEmbedToHumHub($id, $this->accessToken());
    }

    public function verifyVideo(string $id, string $title, string $description): array
    {
        $video = [];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $video = $this->getVideo($id);
            $actualTitle = $this->normalizeMetadata((string) ($video['name'] ?? ''));
            $actualDescription = $this->normalizeMetadata((string) ($video['description'] ?? ''));
            if ($actualTitle === $this->normalizeMetadata($title)
                && $actualDescription === $this->normalizeMetadata($description)) {
                return $video;
            }
            if ($attempt < 2) {
                usleep(250000 * ($attempt + 1));
            }
        }

        Yii::warning([
            'message' => 'PeerTube-Metadaten weichen nach dem Aktualisieren ab.',
            'videoId' => $id,
            'expectedTitleHash' => hash('sha256', $this->normalizeMetadata($title)),
            'actualTitleHash' => hash('sha256', $this->normalizeMetadata((string) ($video['name'] ?? ''))),
            'expectedDescriptionHash' => hash('sha256', $this->normalizeMetadata($description)),
            'actualDescriptionHash' => hash('sha256', $this->normalizeMetadata((string) ($video['description'] ?? ''))),
        ], 'peertube');
        throw new RuntimeException('PeerTube-Prüfung fehlgeschlagen: Die gespeicherten Metadaten konnten nicht bestätigt werden.');
    }

    public function getVideo(string $id): array
    {
        return $this->request('GET', '/api/v1/videos/' . rawurlencode($id), null, $this->accessToken());
    }

    public function createPermanentLive(string $title, string $description, int $channelId, string $videoPassword): array
    {
        $token = $this->accessToken();
        $fields = [
            'name' => $title,
            'description' => $description,
            'channelId' => (string) $channelId,
            'privacy' => '5',
            'videoPasswords[0]' => $videoPassword,
            'commentsPolicy' => '2',
            'downloadEnabled' => 'false',
            'permanentLive' => 'true',
            'saveReplay' => 'true',
            // PeerTube accepts password privacy for the live itself, but not for
            // a not-yet-created replay. Keep the recording private until HumHub
            // imports it and applies its own generated password.
            'replaySettings[privacy]' => '3',
            // PeerTube SMALL_LATENCY: about 15 seconds and no P2P buffering.
            // This is the best fit for interactive community livestreams.
            'latencyMode' => '3',
        ];
        try {
            $result = $this->request('POST', '/api/v1/videos/live', $fields, $token, true);
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), 'Custom latency mode') && !str_contains($exception->getMessage(), 'latency mode')) {
                throw $exception;
            }
            // Some PeerTube instances expose the setting in the UI but forbid
            // custom latency modes for API clients. Fall back to the normal
            // mode instead of making livestream creation fail.
            Yii::warning('PeerTube erlaubt keine geringe Live-Latenz; Standardmodus wird verwendet.', 'peertube');
            $fields['latencyMode'] = '1';
            $result = $this->request('POST', '/api/v1/videos/live', $fields, $token, true);
        }
        $video = $result['video'] ?? $result;
        $id = (string) ($video['uuid'] ?? $video['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('PeerTube hat keine Live-Video-ID zurückgegeben.');
        }
        $this->protectExisting($id, $videoPassword);
        $live = $this->request('GET', '/api/v1/videos/live/' . rawurlencode($id), null, $token);
        if (empty($live['streamKey']) || (empty($live['rtmpsUrl']) && empty($live['rtmpUrl']))) {
            throw new RuntimeException('PeerTube hat keine vollständigen Streaming-Zugangsdaten zurückgegeben.');
        }
        return ['video' => $video, 'live' => $live];
    }

    public function getLiveInfo(string $id): array
    {
        return $this->request('GET', '/api/v1/videos/live/' . rawurlencode($id), null, $this->accessToken());
    }

    public function tryUseSmallLiveLatency(string $id): bool
    {
        try {
            $this->request('PUT', '/api/v1/videos/live/' . rawurlencode($id), ['latencyMode' => '3'], $this->accessToken());
            return true;
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), 'Custom latency mode') && !str_contains($exception->getMessage(), 'latency mode')) {
                throw $exception;
            }
            Yii::warning('PeerTube erlaubt keine geringe Live-Latenz; die bestehende Live-Quelle bleibt im Standardmodus.', 'peertube');
            return false;
        }
    }

    public function getLiveSessions(string $id): array
    {
        return $this->request('GET', '/api/v1/videos/live/' . rawurlencode($id) . '/sessions', null, $this->accessToken());
    }

    private function normalizeMetadata(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", trim($value));
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }
        return $value;
    }

    private function restrictEmbedToHumHub(string $id, string $token): void
    {
        $settings = Yii::$app->getModule('peertube')->settings;
        $domains = SettingsForm::normalizeEmbedDomains($settings->get('embedDomains', SettingsForm::DEFAULT_EMBED_DOMAINS));
        if (!$domains) {
            return;
        }
        $this->requestJson('PUT', '/api/v1/videos/' . rawurlencode($id) . '/embed-privacy', [
            'policy' => 2,
            'domains' => $domains,
        ], $token);
    }

    private function requestJson(string $method, string $path, array $payload, string $token): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Die PHP-cURL-Erweiterung fehlt.');
        }
        $body = '';
        $status = 0;
        $error = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $handle = curl_init($this->baseUrl . $path);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $token],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
            ]);
            $body = (string) curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);

            if ($error === '' && $status >= 200 && $status < 300) {
                $decoded = json_decode($body, true);
                return is_array($decoded) ? $decoded : [];
            }
            $serializationConflict = $status === 500
                && (stripos($body, 'could not serialize access') !== false
                    || stripos($body, 'SequelizeDatabaseError') !== false);
            if (!$serializationConflict || $attempt === 4) {
                break;
            }
            // PostgreSQL recommends retrying the complete transaction after a
            // serialization failure. Jitter prevents parallel workers from
            // immediately colliding again.
            usleep((200000 * (2 ** $attempt)) + random_int(25000, 175000));
        }
        throw new RuntimeException('PeerTube Embed-Schutz (' . $status . '): ' . ($body ?: $error));
    }

    private function accessToken(): string
    {
        if ($this->accessTokenCache) {
            return $this->accessTokenCache;
        }
        $cacheKey = 'peertube.oauth.' . hash('sha256', $this->baseUrl . "\0" . $this->username . "\0" . $this->password);
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $this->accessTokenCache = $cached;
        }
        $client = $this->request('GET', '/api/v1/oauth-clients/local');
        $token = $this->request('POST', '/api/v1/users/token', [
            'client_id' => $client['client_id'] ?? '',
            'client_secret' => $client['client_secret'] ?? '',
            'grant_type' => 'password',
            'response_type' => 'code',
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (empty($token['access_token'])) {
            throw new RuntimeException('PeerTube hat kein Zugriffstoken zurückgegeben.');
        }
        $this->accessTokenCache = $token['access_token'];
        $ttl = max(60, min(3600, ((int) ($token['expires_in'] ?? 3600)) - 60));
        Yii::$app->cache->set($cacheKey, $this->accessTokenCache, $ttl);
        return $this->accessTokenCache;
    }

    private function request(string $method, string $path, ?array $fields = null, ?string $token = null, bool $multipart = false): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Die PHP-cURL-Erweiterung fehlt.');
        }
        if ($this->baseUrl === '') {
            throw new RuntimeException('Die PeerTube-URL ist nicht konfiguriert.');
        }

        $handle = curl_init($this->baseUrl . $path);
        $headers = ['Accept: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $multipart ? 300 : 30,
        ];
        if ($fields !== null) {
            $options[CURLOPT_POSTFIELDS] = $multipart ? $fields : http_build_query($fields);
            if (!$multipart) {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                $options[CURLOPT_HTTPHEADER] = $headers;
            }
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        $decoded = json_decode((string) $body, true);
        if ($error !== '' || $status < 200 || $status >= 300) {
            $message = is_array($decoded) ? ($decoded['error'] ?? $decoded['message'] ?? json_encode($decoded)) : $body;
            throw new RuntimeException('PeerTube API (' . $status . '): ' . ($message ?: $error));
        }
        return is_array($decoded) ? $decoded : [];
    }
}
