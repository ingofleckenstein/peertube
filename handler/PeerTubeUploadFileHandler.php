<?php

namespace community\videolibrary\handler;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\file\handler\BaseFileHandler;
use humhub\modules\ui\icon\widgets\Icon;

class PeerTubeUploadFileHandler extends BaseFileHandler
{
    public ContentContainerActiveRecord $contentContainer;
    public $position = self::POSITION_TOP;

    public function getLinkAttributes(): array
    {
        return [
            'label' => Icon::get('video-camera') . 'Video hochladen',
            'url' => $this->contentContainer->createUrl('/peertube/media/upload'),
            'data-pjax' => '0',
        ];
    }
}
