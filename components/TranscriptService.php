<?php

namespace community\videolibrary\components;

use humhub\modules\content\services\ContentSearchService;
use community\videolibrary\models\Media;
use Yii;

/** Synchronizes PeerTube WebVTT captions into a searchable HumHub media post. */
class TranscriptService
{
    private const CHECK_INTERVAL = 15;
    private const MAX_CUES = 20000;
    private const MAX_TEXT_LENGTH = 1024 * 1024;

    /**
     * This method is intentionally called by the authorized browser request:
     * the first view of a new video starts PeerTube's asynchronous caption job.
     */
    public function get(Media $media): array
    {
        if (!$media->hasAttribute('transcript_status')) {
            return $this->unavailable('Das Transkript wird nach der Modulaktualisierung verfügbar.');
        }

        if ((string) $media->transcript_status === 'ready' && trim((string) $media->transcript_cues) !== '') {
            return $this->ready($media);
        }

        if (!$this->shouldCheck($media)) {
            return $this->processing();
        }

        $now = date('Y-m-d H:i:s');
        try {
            $password = VideoPasswordVault::decrypt((string) $media->password_encrypted);
            $client = new PeerTubeClient();
            $caption = $this->selectCaption($client->getCaptions((string) $media->peertube_uuid, $password));

            if ($caption !== null) {
                $vtt = $client->downloadCaption((string) $caption['captionPath'], $password);
                [$cues, $text] = self::parseWebVtt($vtt);
                if (!$cues || $text === '') {
                    throw new \RuntimeException('Der Videoserver hat keine verwendbaren Zeitmarken geliefert.');
                }

                $media->updateAttributes([
                    'transcript_status' => 'ready',
                    'transcript_language' => (string) ($caption['language']['id'] ?? ''),
                    'transcript_cues' => json_encode($cues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'transcript_text' => $text,
                    'transcript_checked_at' => $now,
                    'transcript_requested_at' => $media->transcript_requested_at ?: $now,
                ]);
                $media->refresh();
                $this->updateSearchIndex($media);
                return $this->ready($media);
            }

            if ((string) $media->transcript_status === 'pending') {
                // Atomically claim generation. Parallel page views must not
                // submit the same PeerTube transcription job repeatedly.
                $claimed = Media::updateAll([
                    'transcript_status' => 'generating',
                    'transcript_requested_at' => $now,
                    'transcript_checked_at' => $now,
                ], [
                    'and',
                    ['id' => (int) $media->id],
                    ['transcript_status' => 'pending'],
                ]);
                if ($claimed) {
                    try {
                        $client->generateCaption((string) $media->peertube_uuid);
                    } catch (\Throwable $exception) {
                        Media::updateAll([
                            'transcript_status' => 'unavailable',
                            'transcript_checked_at' => $now,
                        ], ['id' => (int) $media->id]);
                        Yii::warning([
                            'message' => 'Die Transkription konnte nicht angefordert werden.',
                            'mediaId' => (int) $media->id,
                            'exception' => get_class($exception),
                        ], 'peertube.transcript');
                        return $this->unavailable('Für dieses Video ist derzeit kein Transkript verfügbar.');
                    }
                }
            } else {
                $media->updateAttributes(['transcript_checked_at' => $now]);
            }
        } catch (\Throwable $exception) {
            // A temporary PeerTube outage must not expose API responses or
            // permanently mark a still-processing transcription as failed.
            Yii::warning([
                'message' => 'Das Transkript konnte nicht geprüft werden.',
                'mediaId' => (int) $media->id,
                'exception' => get_class($exception),
            ], 'peertube.transcript');
        }

        return $this->processing();
    }

    public static function parseWebVtt(string $vtt): array
    {
        if (strlen($vtt) > 2 * 1024 * 1024) {
            throw new \RuntimeException('Das Transkript ist zu groß.');
        }

        $lines = preg_split('/\r\n|\r|\n/', preg_replace('/^\xEF\xBB\xBF/', '', $vtt));
        $cues = [];
        $textParts = [];
        for ($index = 0, $count = count($lines); $index < $count;) {
            $line = trim((string) $lines[$index]);
            if ($line === '' || $line === 'WEBVTT' || str_starts_with($line, 'NOTE') || str_starts_with($line, 'STYLE')) {
                ++$index;
                continue;
            }
            if (!str_contains($line, '-->')) {
                ++$index; // Optional cue identifier.
                $line = trim((string) ($lines[$index] ?? ''));
            }
            if (!preg_match('/^([^\s]+)\s+-->\s+([^\s]+)/', $line, $matches)) {
                while ($index < $count && trim((string) $lines[$index]) !== '') {
                    ++$index;
                }
                continue;
            }
            $start = self::parseTimestamp($matches[1]);
            $end = self::parseTimestamp($matches[2]);
            ++$index;
            $cueLines = [];
            while ($index < $count && trim((string) $lines[$index]) !== '') {
                $cueLines[] = trim((string) $lines[$index]);
                ++$index;
            }
            $cueText = self::plainText(implode(' ', $cueLines));
            if ($start === null || $end === null || $end <= $start || $cueText === '') {
                continue;
            }
            $cues[] = ['start' => $start, 'end' => $end, 'text' => $cueText];
            $textParts[] = $cueText;
            if (count($cues) > self::MAX_CUES || strlen(implode("\n", $textParts)) > self::MAX_TEXT_LENGTH) {
                throw new \RuntimeException('Das Transkript ist zu umfangreich.');
            }
        }
        return [$cues, implode("\n", $textParts)];
    }

    private static function parseTimestamp(string $value): ?float
    {
        if (!preg_match('/^(?:(\d+):)?(\d{2}):(\d{2})[.,](\d{3})$/', trim($value), $matches)) {
            return null;
        }
        $minutes = (int) $matches[2];
        $seconds = (int) $matches[3];
        if ($minutes > 59 || $seconds > 59) {
            return null;
        }
        return ((int) ($matches[1] ?? 0) * 3600) + ($minutes * 60) + $seconds + ((int) $matches[4] / 1000);
    }

    private static function plainText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function shouldCheck(Media $media): bool
    {
        $checkedAt = strtotime((string) $media->transcript_checked_at) ?: 0;
        return $checkedAt + self::CHECK_INTERVAL <= time();
    }

    private function selectCaption(array $response): ?array
    {
        $captions = $response['data'] ?? [];
        if (!is_array($captions) || !$captions) {
            return null;
        }
        $preferred = strtolower(substr((string) Yii::$app->language, 0, 2));
        usort($captions, static function (array $left, array $right) use ($preferred): int {
            $leftLanguage = strtolower((string) ($left['language']['id'] ?? ''));
            $rightLanguage = strtolower((string) ($right['language']['id'] ?? ''));
            return (($rightLanguage === $preferred) <=> ($leftLanguage === $preferred))
                ?: (($right['automaticallyGenerated'] ?? false) <=> ($left['automaticallyGenerated'] ?? false));
        });
        foreach ($captions as $caption) {
            if (is_array($caption) && is_string($caption['captionPath'] ?? null)) {
                return $caption;
            }
        }
        return null;
    }

    private function ready(Media $media): array
    {
        $cues = json_decode((string) $media->transcript_cues, true);
        if (!is_array($cues)) {
            return $this->processing();
        }
        return ['status' => 'ready', 'language' => (string) $media->transcript_language, 'cues' => $cues];
    }

    private function processing(): array
    {
        return ['status' => 'processing', 'message' => 'Transkript wird erstellt.'];
    }

    private function unavailable(string $message): array
    {
        return ['status' => 'unavailable', 'message' => $message];
    }

    private function updateSearchIndex(Media $media): void
    {
        if ($media->content && !$media->content->isNewRecord) {
            (new ContentSearchService($media->content))->update(false);
        }
    }
}
