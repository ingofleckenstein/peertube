<?php

namespace selfsein\peertube;

use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use selfsein\peertube\models\Media;
use selfsein\peertube\models\LiveSession;
use selfsein\peertube\permissions\StartLive;
use selfsein\peertube\permissions\UseLiveStreaming;
use selfsein\peertube\permissions\ManageMedia;
use selfsein\peertube\permissions\UploadMedia;
use selfsein\peertube\permissions\UsePeerTube;
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
        return [new UsePeerTube(), new UseLiveStreaming()];
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
