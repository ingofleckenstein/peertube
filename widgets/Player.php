<?php

namespace selfsein\peertube\widgets;

use humhub\components\Widget;
use selfsein\peertube\assets\PlayerAsset;
use selfsein\peertube\models\Media;
use selfsein\peertube\models\SettingsForm;
use Yii;

class Player extends Widget
{
    public Media $media;
    public bool $clickToLoad = false;

    public function run()
    {
        if (!$this->media->canBeViewedBy()) {
            return '';
        }

        PlayerAsset::register($this->view);
        $options = SettingsForm::getWarningCategoryOptions();
        $warnings = array_map(static fn($key) => $options[$key] ?? $key, $this->media->getContentWarningKeys());
        return $this->render('player', [
            'media' => $this->media,
            'warnings' => $warnings,
            'clickToLoad' => $this->clickToLoad,
        ]);
    }
}
