<?php
namespace community\videolibrary\widgets;
use humhub\components\Widget;
use community\videolibrary\assets\PlayerAsset;
use community\videolibrary\models\LiveSession;
class LivePlayer extends Widget
{
    public LiveSession $session;
    public function run() { if (!$this->session->canBeViewedBy()) return ''; PlayerAsset::register($this->view); return $this->render('livePlayer', ['session'=>$this->session]); }
}
