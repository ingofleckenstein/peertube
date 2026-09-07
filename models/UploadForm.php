<?php

namespace selfsein\peertube\models;

use yii\base\Model;

class UploadForm extends Model
{
    public $title;
    public $description;
    public $mediaFile;
    public $consentConfirmed = false;
    public $contentWarnings = [];
    public $publishToStream = true;
    public $uploadToken;
    public $topics = [];
    public $folderId;
    public $thumbnailFile;
    public $thumbnailTimestamp;
    public $thumbnailData;

    public function init(): void
    {
        parent::init();
        $this->uploadToken = bin2hex(random_bytes(16));
    }

    public function rules(): array
    {
        return [
            [['title', 'mediaFile'], 'required'],
            ['title', 'string', 'min' => 3, 'max' => 120],
            ['description', 'string'],
            ['topics', 'safe'],
            ['folderId', 'integer'],
            ['thumbnailTimestamp', 'match', 'pattern' => '/^(?:\d+(?:\.\d+)?|(?:(?:\d+):)?[0-5]?\d:[0-5]\d)$/', 'message' => 'Bitte Sekunden oder einen Zeitpunkt wie 01:25 eingeben.'],
            ['thumbnailData', 'string'],
            ['thumbnailFile', 'file', 'extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'checkExtensionByMimeType' => true, 'maxSize' => 10 * 1024 * 1024],
            ['consentConfirmed', 'required', 'requiredValue' => true, 'message' => 'Bitte bestätige die Veröffentlichungsberechtigung.'],
            ['contentWarnings', 'each', 'rule' => ['in', 'range' => array_keys(SettingsForm::getWarningCategoryOptions())]],
            ['publishToStream', 'boolean'],
            ['uploadToken', 'required'],
            ['uploadToken', 'match', 'pattern' => '/^[a-f0-9]{32}$/'],
            ['mediaFile', 'file', 'extensions' => ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'mp3', 'm4a', 'aac', 'ogg', 'wav', 'flac'], 'checkExtensionByMimeType' => false],
        ];
    }
}
