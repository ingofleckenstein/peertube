<?php
namespace selfsein\peertube\components;

class LiveError
{
    public static function message(\Throwable $error): string
    {
        if (str_contains($error->getMessage(), 'max_user_lives_limit_reached')) {
            return 'Das Livestream-Limit des technischen PeerTube-Kontos ist erreicht. Die Administration muss die vorhandenen Livequellen auf PeerTube prüfen und bei Bedarf das Limit anpassen. Erneutes Laden oder Cache-Leeren behebt dieses Limit nicht.';
        }
        if (str_contains($error->getMessage(), 'max_instance_lives_limit_reached')) {
            return 'Das Livestream-Limit des PeerTube-Servers ist erreicht. Bitte informiere die Administration.';
        }
        return 'Die Livestream-Quelle konnte nicht vorbereitet werden. Bitte versuche es erneut oder informiere die Administration.';
    }
}
