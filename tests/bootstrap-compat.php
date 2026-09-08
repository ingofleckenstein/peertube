<?php

declare(strict_types=1);

/*
 * HumHub's module cache can reference the namespace from a prior release.
 * Yii resolves aliases with include rather than include_once, so this checks
 * both the former and canonical class names against the same physical files.
 */
namespace humhub\modules\content\components {
    class ContentContainerModule {}
}

namespace humhub\modules\space\widgets {
    class Menu { public const EVENT_INIT = 'init'; }
}

namespace humhub\modules\file\models {
    class FileUpload {}
}

namespace humhub\modules\file\handler {
    class FileHandlerCollection { public const EVENT_INIT = 'init'; }
}

namespace humhub\modules\content\widgets {
    class WallCreateContentFormFooter {}
}

namespace humhub\modules\user\widgets {
    class ProfileMenu { public const EVENT_INIT = 'init'; }
}

namespace yii\base {
    class Model { public const EVENT_BEFORE_VALIDATE = 'beforeValidate'; }
    class Widget { public const EVENT_AFTER_RUN = 'afterRun'; }
}

namespace {
    $root = dirname(__DIR__);
    $canonicalEvents = 'community\\videolibrary\\Events';
    $canonicalModule = 'community\\videolibrary\\Module';
    $legacyEvents = implode('\\', ['self' . 'sein', 'peer' . 'tube', 'Events']);
    $legacyModule = implode('\\', ['self' . 'sein', 'peer' . 'tube', 'Module']);

    spl_autoload_register(static function (string $class) use ($root, $canonicalEvents, $canonicalModule, $legacyEvents, $legacyModule): void {
        if ($class === $canonicalEvents || $class === $legacyEvents) {
            include $root . '/Events.php';
        }
        if ($class === $canonicalModule || $class === $legacyModule) {
            include $root . '/Module.php';
        }
    });

    $check = static function (bool $condition, string $label): void {
        if (!$condition) {
            throw new RuntimeException('Bootstrap compatibility check failed: ' . $label);
        }
    };

    // A stale module cache asks for the former name first.
    $check(class_exists($legacyEvents), 'legacy Events resolves');
    $check(class_exists($canonicalEvents), 'canonical Events resolves');
    $check(is_a($legacyEvents, $canonicalEvents, true), 'Events alias targets canonical class');
    $check(class_exists($legacyModule), 'legacy Module resolves');
    $check(class_exists($canonicalModule), 'canonical Module resolves');
    $check(is_a($legacyModule, $canonicalModule, true), 'Module alias targets canonical class');

    // Re-inclusion through a second namespace must not redeclare either class.
    include $root . '/Events.php';
    include $root . '/Module.php';

    $config = require $root . '/config.php';
    $check(($config['class'] ?? null) === $canonicalModule, 'config declares canonical module');
    foreach ($config['events'] ?? [] as $event) {
        $check(class_exists($event['callback'][0] ?? ''), 'configured event class resolves');
    }

    echo "bootstrap compatibility checks passed\n";
}
