<?php
namespace selfsein\peertube\permissions;
use humhub\libs\BasePermission;
class UseLiveStreaming extends BasePermission
{
    protected $moduleId = 'peertube';
    protected $defaultState = self::STATE_DENY;
    public function getTitle() { return 'Livestreams starten'; }
    public function getDescription() { return 'Erlaubt Mitgliedern dieser Benutzergruppe, Livestreams in dafür freigegebenen Bereichen zu starten.'; }
}
