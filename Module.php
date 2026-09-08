<?php

namespace community\videolibrary;

use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use community\videolibrary\models\Media;
use community\videolibrary\models\LiveSession;
use community\videolibrary\permissions\StartLive;
use community\videolibrary\permissions\UseLiveStreaming;
use community\videolibrary\permissions\ManageMedia;
use community\videolibrary\permissions\UploadMedia;
use community\videolibrary\permissions\UsePeerTube;
use community\videolibrary\permissions\UsePublicLiveStreaming;
use yii\helpers\Url;
use Yii;

class Module extends ContentContainerModule
{
    public $resourcesPath = 'resources';

    public function getName(): string
    {
        return 'Community-Mediathek';
    }

    public function getDescription(): string
    {
        return 'Video- und Audio-Mediathek für die Community.';
    }

    public function getConfigUrl(): string
    {
        return Url::to(['/peertube/admin']);
    }

    public function getContentContainerTypes(): array
    {
        return [Space::class, User::class];
    }

    public function getContentClasses(): array
    {
        return [Media::class, LiveSession::class];
    }

    public function getContainerPermissions($contentContainer = null)
    {
        if ($contentContainer instanceof Space) {
            return [new UploadMedia(['contentContainer' => $contentContainer]), new StartLive(['contentContainer' => $contentContainer]), new ManageMedia(['contentContainer' => $contentContainer])];
        }

        return [];
    }

    protected function getGlobalPermissions()
    {
        return [new UsePeerTube(), new UseLiveStreaming(), new UsePublicLiveStreaming()];
    }

    /**
     * HumHub normally executes uninstall.php while merely disabling a module.
     * For a media library this is too destructive. Preserve all module data
     * unless an administrator explicitly armed the one-shot danger setting.
     */
    public function disable()
    {
        if ((bool)$this->settings->get('allowDataDeletionOnDisable', false)) {
            return parent::disable();
        }

        Yii::$app->moduleManager->disable($this);
        return true;
    }
}
