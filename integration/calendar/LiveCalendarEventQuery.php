<?php

namespace community\videolibrary\integration\calendar;

use humhub\modules\calendar\interfaces\event\AbstractCalendarQuery;

class LiveCalendarEventQuery extends AbstractCalendarQuery
{
    protected static $recordClass = LiveCalendarEvent::class;
}
