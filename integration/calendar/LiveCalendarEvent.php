<?php

namespace selfsein\peertube\integration\calendar;

use DateTime;
use humhub\modules\calendar\interfaces\CalendarEventIF;
use humhub\modules\content\components\ActiveQueryContent;
use selfsein\peertube\models\LiveSession;
use Yii;

class LiveCalendarEvent extends LiveSession implements CalendarEventIF
{
    public static function getObjectModel() { return LiveSession::class; }
    public static function find() { return new ActiveQueryContent(static::class); }
    public function getUid() { return 'peertube-live-' . $this->id; }
    public function getType() { return new LiveCalendarType(); }
    public function isAllDay() { return false; }
    public function getStartDateTime() { return new DateTime((string)$this->start_datetime); }
    public function getEndDateTime() { return new DateTime((string)$this->end_datetime); }
    public function getTimezone() { return Yii::$app->timeZone; }
    public function getEndTimezone() { return null; }
    public function getTitle() { return (string)$this->title; }
    public function getDescription() { return (string)$this->description; }
    public function getLastModified() { return new DateTime((string)$this->updated_at); }
    public function getColor() { return null; }
    public function getSequence() { return null; }
    public function getLocation() { return null; }
    public function getBadge() { return $this->status === 'live' ? 'LIVE' : 'Livestream'; }
    public function getCalendarOptions() { return []; }
}
