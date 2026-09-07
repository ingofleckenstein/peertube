<?php
namespace selfsein\peertube\models;
use yii\base\Model;
class LiveForm extends Model
{
    public const SCENARIO_EDIT = 'edit';
    public $title;
    public $description;
    public $consentConfirmed = false;
    public $schedule = false;
    public $scheduledAt;
    public $durationMinutes = 60;
    public $announcementImage;
    public function rules(): array
    {
        return [
            ['title', 'required'],
            ['title', 'string', 'min' => 3, 'max' => 120],
            ['description', 'filter', 'filter' => 'trim', 'skipOnArray' => true],
            ['description', 'string', 'min' => 3, 'max' => 10000,
                'tooShort' => 'Die Beschreibung muss mindestens 3 Zeichen enthalten oder leer bleiben.',
                'tooLong' => 'Die Beschreibung darf höchstens 10.000 Zeichen enthalten.'],
            ['schedule', 'boolean'],
            ['scheduledAt', 'required', 'when' => static fn(self $model) => (bool)$model->schedule,
                'whenClient' => "function(){return document.getElementById('liveform-schedule').checked;}",
                'message' => 'Bitte wähle den geplanten Beginn.'],
            ['scheduledAt', 'datetime', 'format' => 'php:Y-m-d\\TH:i', 'when' => static fn(self $model) => (bool)$model->schedule],
            ['durationMinutes', 'integer', 'min' => 5, 'max' => 1440],
            ['announcementImage', 'file', 'extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'checkExtensionByMimeType' => true, 'maxSize' => 10 * 1024 * 1024],
            ['consentConfirmed', 'required', 'requiredValue' => true, 'message' => 'Bitte bestätige die Veröffentlichungsberechtigung.', 'except' => self::SCENARIO_EDIT],
        ];
    }
}
