<?php

namespace selfsein\peertube;

use humhub\modules\space\widgets\Menu;
use humhub\modules\ui\menu\MenuLink;
use humhub\modules\user\widgets\ProfileMenu;
use humhub\modules\content\widgets\WallCreateContentFormFooter;
use humhub\modules\file\models\FileUpload;
use humhub\modules\file\handler\FileHandlerCollection;
use humhub\modules\file\handler\UploadVideoFileHandler;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use selfsein\peertube\components\AccessPolicy;
use Yii;
use yii\base\ModelEvent;
use yii\base\WidgetEvent;
use yii\helpers\Html;
use humhub\modules\content\components\ContentContainerActiveRecord;

class Events
{
    public static function onSpaceMenuInit($event): void
    {
        /** @var Menu $menu */
        $menu = $event->sender;
        $space = $menu->space;

        if (!$space || !$space->moduleManager->isEnabled('peertube')) {
            return;
        }

        $menu->addEntry(new MenuLink([
            'label' => 'Mediathek',
            'url' => $space->createUrl('/peertube/media/index'),
            'icon' => 'video-camera',
            'sortOrder' => 550,
        ]));
    }

    public static function onProfileMenuInit($event): void
    {
        /** @var ProfileMenu $menu */
        $menu = $event->sender;
        $user = $menu->user;

        if (!$user instanceof User || !$user->moduleManager->isEnabled('peertube')) {
            return;
        }

        $menu->addEntry(new MenuLink([
            'label' => 'Mediathek',
            'url' => $user->createUrl('/peertube/media/index'),
            'icon' => 'video-camera',
            'sortOrder' => 250,
        ]));
    }

    /** Add the profile upload as an explicit action on HumHub's compact dashboard composer. */
    public static function onWallCreateContentFormFooterAfterRun(WidgetEvent $event): void
    {
        /** @var WallCreateContentFormFooter $footer */
        $footer = $event->sender;
        $route = (string) (Yii::$app->controller->route ?? '');

        if (!str_starts_with($route, 'dashboard/') || !$footer->contentContainer instanceof User) {
            return;
        }
        $links = '';
        if (AccessPolicy::canUpload($footer->contentContainer)) {
            $links .= Html::a('<i class="fa fa-video-camera"></i> Video hochladen', $footer->contentContainer->createUrl('/peertube/media/upload'), ['class'=>'btn btn-light btn-sm','data-pjax'=>'0']);
        }
        if (AccessPolicy::canStartLive($footer->contentContainer)) {
            $links .= ' ' . Html::a('<i class="fa fa-circle text-danger"></i> Live gehen', $footer->contentContainer->createUrl('/peertube/live/start'), ['class'=>'btn btn-light btn-sm','data-pjax'=>'0']);
        }
        if ($links !== '') $event->result .= Html::tag('div', $links, ['class'=>'pt-dashboard-video-upload','style'=>'margin-top:10px']);
    }

    /** Prevent videos from being persisted through HumHub's generic uploader. */
    public static function onFileBeforeValidate(ModelEvent $event): void
    {
        $file = $event->sender;
        if (!$file instanceof FileUpload || !$file->uploadedFile) {
            return;
        }

        $container = self::resolveContainer();
        if (!$container instanceof Space && !$container instanceof User) {
            return;
        }

        $mime = strtolower((string) $file->uploadedFile->type);
        $extension = strtolower((string) $file->uploadedFile->extension);
        $videoExtensions = ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'ogv', 'avi', 'wmv', 'flv', '3gp', '3g2', 'mts', 'm2ts', 'mpeg', 'mpg'];
        if (str_starts_with($mime, 'video/') || in_array($extension, $videoExtensions, true)) {
            $message = AccessPolicy::canUpload($container)
                ? 'Videos bitte über „Video hochladen“ in die Community hochladen.'
                : 'Direkte Video-Uploads stehen in diesem Bereich nicht zur Verfügung.';
            $file->addError('uploadedFile', $message);
            $event->isValid = false;
        }
    }

    /** Remove HumHub's native video attachment; the visible Video content tab remains. */
    public static function onFileHandlerCollectionInit($event): void
    {
        $collection = $event->sender;
        if (!$collection instanceof FileHandlerCollection || $collection->type !== FileHandlerCollection::TYPE_CREATE) {
            return;
        }

        $container = self::resolveContainer();
        if (!$container instanceof Space && !$container instanceof User) {
            return;
        }

        $fileModule = Yii::$app->getModule('file');
        $fileModule->defaultFileHandlers = array_values(array_filter(
            $fileModule->defaultFileHandlers,
            static fn($class) => $class !== UploadVideoFileHandler::class
        ));
        // Do not add the action to the cloud/file menu. Media::$wallEntryClass
        // exposes the upload as a regular, visible content tab instead.
    }

    private static function resolveContainer(): ?ContentContainerActiveRecord
    {
        $container = Yii::$app->controller->contentContainer ?? null;
        if ($container instanceof ContentContainerActiveRecord) {
            return $container;
        }

        // The dashboard composer creates content in the current user's profile,
        // but its controller itself has no contentContainer property.
        $route = (string) (Yii::$app->controller->route ?? '');
        if (!Yii::$app->user->isGuest && str_starts_with($route, 'dashboard/')) {
            $identity = Yii::$app->user->identity;
            return $identity instanceof User ? $identity : null;
        }

        return null;
    }
}
