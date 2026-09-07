<?php
namespace selfsein\peertube\permissions;
use humhub\libs\BasePermission;
use humhub\modules\space\models\Space;
class StartLive extends BasePermission
{
    protected $moduleId = 'peertube';
    public $defaultAllowedGroups = [Space::USERGROUP_OWNER, Space::USERGROUP_ADMIN, Space::USERGROUP_MODERATOR];
    protected $fixedGroups = [Space::USERGROUP_GUEST];
    public function getTitle() { return 'Livestreams starten'; }
    public function getDescription() { return 'Erlaubt berechtigten Community-Mitgliedern, in diesem Space live zu senden.'; }
}
