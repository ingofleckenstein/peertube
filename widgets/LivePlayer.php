<?php
namespace selfsein\peertube\widgets;
use humhub\components\Widget;
use selfsein\peertube\assets\PlayerAsset;
use selfsein\peertube\models\LiveSession;
class LivePlayer extends Widget
{
    public LiveSession $session;
    public function run() { if (!$this->session->canBeViewedBy()) return ''; PlayerAsset::register($this->view); return $this->render('livePlayer', ['session'=>$this->session]); }
}
