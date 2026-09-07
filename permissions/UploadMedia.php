<?php

namespace selfsein\peertube\permissions;

use humhub\libs\BasePermission;
use humhub\modules\space\models\Space;

class UploadMedia extends BasePermission
{
    protected $moduleId = 'peertube';
    public $defaultAllowedGroups = [Space::USERGROUP_OWNER, Space::USERGROUP_ADMIN, Space::USERGROUP_MODERATOR, Space::USERGROUP_MEMBER];
    protected $fixedGroups = [Space::USERGROUP_GUEST];

    public function getTitle() { return 'Videos hochladen'; }
    public function getDescription() { return 'Erlaubt das Hochladen von Videos und Audio in die Community-Mediathek.'; }
}
