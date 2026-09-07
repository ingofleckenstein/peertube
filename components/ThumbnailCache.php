<?php

namespace selfsein\peertube\components;

use RuntimeException;

class ThumbnailCache
{
    public static function get(string $url, string $baseUrl, string $revision): array
    {
        // Only static images of the configured PeerTube origin, no redirects.
        $base = parse_url(rtrim($baseUrl, '/'));
        $target = parse_url($url);
        if (!$base || !$target || ($base['scheme'] ?? '') !== 'https'
            || ($target['scheme'] ?? '') !== 'https'
            || strtolower($base['host'] ?? '') !== strtolower($target['host'] ?? '')
            || ($base['port'] ?? 443) !== ($target['port'] ?? 443)
            || isset($target['user']) || isset($target['pass']) || isset($target['query'])
            || !preg_match('~^/(?:lazy-)?static/(?:thumbnails|previews)/[a-zA-Z0-9_.-]+$~D', $target['path'] ?? '')) {
            throw new RuntimeException('Ungültige PeerTube-Vorschaubildadresse.');
        }
        return RemoteCache::remember('thumbnail|' . $url . '|' . $revision, 86400, static function () use ($url): array {
            $body = '';
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 5 * 1024 * 1024) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            $info = $ok !== false && $status === 200 ? @getimagesizefromstring($body) : false;
            if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                throw new RuntimeException('PeerTube-Vorschaubild konnte nicht geladen werden.');
            }
            return ['body' => $body, 'mime' => $info['mime']];
        });
    }
}
