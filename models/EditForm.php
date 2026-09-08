<?php

namespace community\videolibrary\models;

use yii\base\Model;

class EditForm extends Model
{
    public $title;
    public $description;
    public $contentWarnings = [];
    public $publishToStream = true;
    public $topics = [];
    public $folderId;
    public $thumbnailFile;

    public function rules(): array
    {
        return [
            ['title', 'required'],
            ['title', 'string', 'max' => 255],
            ['description', 'string'],
            ['topics', 'safe'],
            ['folderId', 'integer'],
            ['thumbnailFile', 'file', 'extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'checkExtensionByMimeType' => true, 'maxSize' => 10 * 1024 * 1024],
            ['contentWarnings', 'each', 'rule' => ['in', 'range' => array_keys(SettingsForm::getWarningCategoryOptions())]],
            ['publishToStream', 'boolean'],
        ];
    }
}
