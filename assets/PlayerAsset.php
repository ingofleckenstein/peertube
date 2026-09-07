<?php

namespace selfsein\peertube\assets;

use yii\web\AssetBundle;

class PlayerAsset extends AssetBundle
{
    public $js = ['js/peertube-player.min.js'];

    public function init()
    {
        $this->sourcePath = dirname(__DIR__) . '/resources';
        parent::init();
    }
}
