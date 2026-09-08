<?php

namespace community\videolibrary\components;

use Yii;

class AdminAlert
{
    public static function raise(string $message, ?\Throwable $exception = null): void
    {
        $safe = mb_substr($message, 0, 2000);
        Yii::$app->getModule('peertube')->settings->set('adminAlert', date('c') . ' ' . $safe);
        Yii::error($exception ?: $safe, 'peertube.critical');
    }
}
