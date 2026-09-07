<?php
namespace selfsein\peertube\widgets;
use humhub\modules\content\widgets\stream\WallStreamEntryWidget;
class LiveWallEntry extends WallStreamEntryWidget
{
    public $createRoute = '/peertube/live/start';
    public $createMode = self::EDIT_MODE_NEW_WINDOW;
    public $createFormSortOrder = 160;
    public $editRoute = '/peertube/live/edit';
    public $editMode = self::EDIT_MODE_NEW_WINDOW;
    protected function renderContent() { return $this->render('liveWallEntry', ['session' => $this->model]); }
}
