<?php

use humhub\modules\space\widgets\Menu;
use humhub\modules\file\models\FileUpload;
use humhub\modules\file\handler\FileHandlerCollection;
use humhub\modules\content\widgets\WallCreateContentFormFooter;
use humhub\modules\user\widgets\ProfileMenu;
use community\videolibrary\Events;
use yii\base\Model;
use yii\base\Widget;

return [
    'id' => 'peertube',
    'class' => community\videolibrary\Module::class,
    'namespace' => 'community\\videolibrary',
    'urlManagerRules' => [
        'videos/public-events' => 'peertube/public-events/index',
        'videos/public-events.json' => 'peertube/public-events/json',
        'videos/<controller:[a-z0-9-]+>/<action:[a-z0-9-]+>' => 'peertube/<controller>/<action>',
    ],
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => ProfileMenu::class, 'event' => ProfileMenu::EVENT_INIT, 'callback' => [Events::class, 'onProfileMenuInit']],
        ['class' => WallCreateContentFormFooter::class, 'event' => Widget::EVENT_AFTER_RUN, 'callback' => [Events::class, 'onWallCreateContentFormFooterAfterRun']],
        ['class' => FileUpload::class, 'event' => Model::EVENT_BEFORE_VALIDATE, 'callback' => [Events::class, 'onFileBeforeValidate']],
        ['class' => FileHandlerCollection::class, 'event' => FileHandlerCollection::EVENT_INIT, 'callback' => [Events::class, 'onFileHandlerCollectionInit']],
    ],
];
