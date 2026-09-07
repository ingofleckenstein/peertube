<?php

namespace selfsein\peertube\widgets;

use humhub\modules\content\widgets\stream\WallStreamEntryWidget;

class WallEntry extends WallStreamEntryWidget
{
    public $createRoute = '/peertube/media/upload';
    public $createMode = self::EDIT_MODE_NEW_WINDOW;
    public $createFormSortOrder = 150;
    public $editRoute = '/peertube/media/edit';
    public $editMode = self::EDIT_MODE_NEW_WINDOW;

    protected function renderContent()
    {
        return $this->render('wallEntry', ['media' => $this->model]);
    }
}
