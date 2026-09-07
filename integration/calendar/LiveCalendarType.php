<?php

namespace selfsein\peertube\integration\calendar;

use humhub\modules\calendar\interfaces\CalendarTypeIF;
use selfsein\peertube\Events;

class LiveCalendarType implements CalendarTypeIF
{
    public function getKey() { return Events::LIVE_CALENDAR_TYPE; }
    public function getDefaultColor() { return '#e53955'; }
    public function getTitle() { return 'Geplante Livestreams'; }
    public function getDescription() { return 'Angekündigte Livestreams aus der Community-Mediathek'; }
    public function getIcon() { return 'fa-video-camera'; }
}
