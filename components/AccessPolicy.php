<?php

namespace community\videolibrary\components;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\user\helpers\UserHelper;
use community\videolibrary\permissions\UploadMedia;
use community\videolibrary\permissions\UsePeerTube;
use community\videolibrary\permissions\StartLive;
use community\videolibrary\permissions\UseLiveStreaming;
use community\videolibrary\permissions\UsePublicLiveStreaming;

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

    public static function canStartPublicLive($user = null): bool
    {
        $user = UserHelper::getUserByParam($user);
        return $user instanceof User && ($user->isSystemAdmin() || $user->canManageAllContent() || $user->can(UsePublicLiveStreaming::class));
    }
}
