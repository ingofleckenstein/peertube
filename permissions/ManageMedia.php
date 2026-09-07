<?php

namespace selfsein\peertube\permissions;

use humhub\libs\BasePermission;
use humhub\modules\space\models\Space;

class ManageMedia extends BasePermission
{
    protected $moduleId = 'peertube';
    public $defaultAllowedGroups = [Space::USERGROUP_OWNER, Space::USERGROUP_ADMIN, Space::USERGROUP_MODERATOR];
    protected $fixedGroups = [Space::USERGROUP_GUEST];

    public function getTitle() { return 'Videos verwalten'; }
    public function getDescription() { return 'Erlaubt das Bearbeiten und Löschen aller Medien im Space.'; }
}
