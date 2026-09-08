<?php

namespace community\videolibrary\permissions;

use humhub\libs\BasePermission;

/** Global group permission controlling who may use PeerTube uploads. */
class UsePeerTube extends BasePermission
{
    protected $moduleId = 'peertube';
    protected $defaultState = self::STATE_DENY;

    public function getTitle()
    {
        return 'Videos in die Community hochladen';
    }

    public function getDescription()
    {
        return 'Erlaubt Mitgliedern dieser Benutzergruppe, Videos in dafür freigegebenen Bereichen hochzuladen.';
    }
}
