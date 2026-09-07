<?php

use humhub\modules\space\widgets\Menu;
use humhub\modules\file\models\FileUpload;
use humhub\modules\file\handler\FileHandlerCollection;
use humhub\modules\content\widgets\WallCreateContentFormFooter;
use humhub\modules\user\widgets\ProfileMenu;
use selfsein\peertube\Events;
use yii\base\Model;
use yii\base\Widget;

return [
    'id' => 'peertube',
    'class' => selfsein\peertube\Module::class,
    'namespace' => 'selfsein\\peertube',
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => ProfileMenu::class, 'event' => ProfileMenu::EVENT_INIT, 'callback' => [Events::class, 'onProfileMenuInit']],
        ['class' => WallCreateContentFormFooter::class, 'event' => Widget::EVENT_AFTER_RUN, 'callback' => [Events::class, 'onWallCreateContentFormFooterAfterRun']],
        ['class' => FileUpload::class, 'event' => Model::EVENT_BEFORE_VALIDATE, 'callback' => [Events::class, 'onFileBeforeValidate']],
        ['class' => FileHandlerCollection::class, 'event' => FileHandlerCollection::EVENT_INIT, 'callback' => [Events::class, 'onFileHandlerCollectionInit']],
    ],
];
