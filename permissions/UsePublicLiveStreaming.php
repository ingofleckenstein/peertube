<?php

namespace community\videolibrary\permissions;

use humhub\libs\BasePermission;

/** Permission for the small editorial team using the shared public source. */
class UsePublicLiveStreaming extends BasePermission
{
    protected $moduleId = 'peertube';
    protected $defaultState = self::STATE_DENY;
    public function getTitle() { return 'Öffentliche Community-Livestreams planen'; }
    public function getDescription() { return 'Erlaubt das Planen und Senden über den gemeinsamen öffentlichen Live-Kanal.'; }
}
