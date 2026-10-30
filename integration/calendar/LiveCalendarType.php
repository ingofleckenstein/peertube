<?php

namespace community\videolibrary\integration\calendar;

use humhub\modules\calendar\interfaces\event\CalendarTypeIF;
use community\videolibrary\Events;

class LiveCalendarType implements CalendarTypeIF
{
    public function getKey() { return 'peertube-live'; }
    public function getDefaultColor() { return '#e53955'; }
    public function getTitle() { return 'Geplante Livestreams'; }
    public function getDescription() { return 'Angekündigte Livestreams aus der Community-Mediathek'; }
    public function getIcon() { return 'fa-video-camera'; }
}
