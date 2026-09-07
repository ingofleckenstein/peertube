<?php

namespace selfsein\peertube\components;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\user\helpers\UserHelper;
use selfsein\peertube\permissions\UploadMedia;
use selfsein\peertube\permissions\UsePeerTube;
use selfsein\peertube\permissions\StartLive;
use selfsein\peertube\permissions\UseLiveStreaming;

class AccessPolicy
{
    public static function canUpload(?ContentContainerActiveRecord $container, $user = null): bool
    {
        $user = UserHelper::getUserByParam($user);
        if (!$container || !$user instanceof User) {
            return false;
        }

        if ((!($container instanceof Space) && !($container instanceof User))
            || !$container->moduleManager->isEnabled('peertube')) {
            return false;
        }

        if ($user->isSystemAdmin() || $user->canManageAllContent()) {
            return true;
        }

        if (!$user->can(UsePeerTube::class)) {
            return false;
        }

        if ($container instanceof Space) {
            return $container->getPermissionManager($user)->can(UploadMedia::class);
        }

        if ($container instanceof User) {
            return (int) $container->id === (int) $user->id;
        }

        return false;
    }

    public static function canStartLive(?ContentContainerActiveRecord $container, $user = null): bool
    {
        $user = UserHelper::getUserByParam($user);
        if (!$container || !$user instanceof User
            || (!$container instanceof Space && !$container instanceof User)
            || !$container->moduleManager->isEnabled('peertube')) {
            return false;
        }
        if ($user->isSystemAdmin() || $user->canManageAllContent()) {
            return true;
        }
        if (!$user->can(UseLiveStreaming::class)) {
            return false;
        }
        return $container instanceof User
            ? (int) $container->id === (int) $user->id
            : $container->getPermissionManager($user)->can(StartLive::class);
    }
}
