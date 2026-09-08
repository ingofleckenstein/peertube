<?php
namespace community\videolibrary\components;

class LiveError
{
    public const VERSION = '2.8.9';

    public static function code(\Throwable $error): string
    {
        $fallback = 'PT-LIVE-UNKNOWN';
        do {
            $text = $error->getMessage();
            if (str_contains($text, 'max_user_lives_limit_reached')) return 'PT-LIVE-403-USER-LIMIT';
            if (str_contains($text, 'max_instance_lives_limit_reached')) return 'PT-LIVE-403-INSTANCE-LIMIT';
            if (preg_match('/PeerTube API \(([1-5][0-9]{2})\):/', $text, $match)) {
                $fallback = 'PT-LIVE-HTTP-' . $match[1];
            }
            if ($error instanceof \yii\db\Exception) $fallback = 'PT-LIVE-DB';
        } while ($error = $error->getPrevious());
        return $fallback;
    }

    public static function message(\Throwable $error): string
    {
        $code = self::code($error);
        $message = match ($code) {
            'PT-LIVE-403-USER-LIMIT' => 'Das Livestream-Limit des technischen Videokontos ist erreicht. Die Administration muss die vorhandenen Livequellen im Video-Dienst prüfen und bei Bedarf das Limit anpassen. Erneutes Laden oder Cache-Leeren behebt dieses Limit nicht.',
            'PT-LIVE-403-INSTANCE-LIMIT' => 'Das Livestream-Limit des Videoservers ist erreicht. Bitte informiere die Administration.',
            'PT-LIVE-HTTP-401' => 'Der Video-Dienst lehnt die Anmeldung ab. Bitte lasse die Zugangsdaten des technischen Kontos prüfen.',
            'PT-LIVE-HTTP-403' => 'Der Video-Dienst verweigert die Aktion. Bitte lasse die Berechtigungen und Livestream-Einstellungen prüfen.',
            'PT-LIVE-HTTP-400' => 'Der Video-Dienst hat die übermittelten Angaben abgelehnt. Bitte informiere die Administration.',
            'PT-LIVE-HTTP-429' => 'Der Video-Dienst erhält zu viele Anfragen. Bitte versuche es später erneut.',
            'PT-LIVE-DB' => 'Die Livestream-Daten konnten lokal nicht verarbeitet werden. Bitte informiere die Administration.',
            default => str_starts_with($code, 'PT-LIVE-HTTP-')
                ? 'Der Videoserver hat die Anfrage mit einem HTTP-Fehler abgelehnt. Bitte informiere die Administration.'
                : 'Die Livestream-Quelle konnte nicht vorbereitet werden. Bitte informiere die Administration mit diesem Fehlercode und der Referenz.',
        };
        return '[' . $code . '] ' . $message;
    }

    public static function isPeerTubeNotFound(\Throwable $error): bool
    {
        return self::code($error) === 'PT-LIVE-HTTP-404';
    }

    /** Link to PeerTube 8.2.x management; never include credentials or arbitrary schemes. */
    public static function managementUrl(\Throwable $error, string $baseUrl): ?string
    {
        if (!in_array(self::code($error), ['PT-LIVE-403-USER-LIMIT', 'PT-LIVE-403-INSTANCE-LIMIT'], true)) return null;
        $parts = parse_url($baseUrl);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\\\\]/', $baseUrl)) return null;
        return rtrim($baseUrl, '/') . '/my-library/videos';
    }

    /** Log only diagnostic metadata: no response bodies, tokens or trace arguments. */
    public static function report(\Throwable $error, string $phase, string $controllerVersion): string
    {
        $reference = strtoupper(bin2hex(random_bytes(6)));
        $causes = [];
        for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
            $causes[] = ['class' => get_class($cause), 'file' => basename($cause->getFile()), 'line' => $cause->getLine()];
        }
        \Yii::error([
            'code' => self::code($error), 'reference' => $reference, 'phase' => $phase,
            'controllerVersion' => $controllerVersion, 'errorHandlerVersion' => self::VERSION,
            'causes' => $causes,
        ], 'peertube.live');
        return self::message($error) . ' Referenz: ' . $reference . '.';
    }
}
