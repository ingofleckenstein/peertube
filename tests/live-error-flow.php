<?php
// Execute the real actionStart catch path with a simulated PeerTube rejection.
namespace humhub\modules\content\components {
    class ContentContainerController {
        public $contentContainer;
        public function render($view, $params = []) { return ['view' => $view, 'params' => $params]; }
    }
}
namespace yii\web { class UploadedFile { public static function getInstance($model, $attribute) { return null; } } }
namespace community\videolibrary\models {
    class LiveSession {
        public static function find() { return new class {
            public function where($v) { return $this; }
            public function andWhere($v) { return $this; }
            public function orderBy($v) { return $this; }
            public function one() { return null; }
        }; }
    }
    class LiveSource { public static function findOne($v) { return null; } }
    class LiveForm {
        public $title = 'Test'; public $description = ''; public $announcementImage; public $errors = [];
        public function load($v) { return true; }
        public function validate() { return true; }
        public function addError($key, $message) { $this->errors[$key][] = $message; }
    }
}
namespace community\videolibrary\components {
    class AccessPolicy { public static function canStartLive($container) { return true; } }
    class LiveCapacity {
        public static $available = true;
        public static function reserve() {
            if (!self::$available) return null;
            return new class { public function commit() {} public function rollback() {} };
        }
    }
    class VideoPasswordVault { public static function generate() { return 'test-only'; } }
    class PeerTubeClient {
        public static $error;
        public function createPermanentLive(...$args) { throw self::$error; }
    }
}
namespace {
    class Yii { public static $app; public static $logs = []; public static function error($data, $category) { self::$logs[] = $data; } }
    Yii::$app = new class {
        public $user; public $request;
        public function getModule($id) { return (object)['settings' => new class {
            public function get($key, $default = null) { return ['channelId' => 1, 'baseUrl' => 'https://video.example.org'][$key] ?? $default; }
        }]; }
    };
    Yii::$app->user = (object)['id' => 1];
    Yii::$app->request = new class { public function post() { return ['LiveForm' => ['title' => 'Test']]; } };
    require dirname(__DIR__) . '/components/LiveError.php';
    require dirname(__DIR__) . '/controllers/LiveController.php';
    $controller = new \community\videolibrary\controllers\LiveController();
    $controller->contentContainer = (object)['moduleManager' => new class { public function isEnabled($id) { return true; } }];
    $count = 0;
    foreach (['PeerTube API (403): {"status":403,"code":"max_user_lives_limit_reached"}' => 'PT-LIVE-403-USER-LIMIT', 'secret-token <script>alert(1)</script>' => 'PT-LIVE-UNKNOWN'] as $error => $code) {
        \community\videolibrary\components\PeerTubeClient::$error = new \RuntimeException($error);
        $result = $controller->actionStart();
        $log = end(Yii::$logs);
        $message = $result['params']['model']->errors['title'][0];
        foreach ([
            $result['view'] === 'start',
            str_contains($message, $code),
            str_contains($message, $log['reference']),
            $log['phase'] === 'prepare-source',
            !str_contains($message . json_encode($log), 'secret-token'),
            $result['params']['liveManagementUrl'] === ($code === 'PT-LIVE-403-USER-LIMIT' ? 'https://video.example.org/my-library/videos' : null),
        ] as $ok) { if (!$ok) throw new \RuntimeException('Live controller diagnostic regression'); ++$count; }
    }
    \community\videolibrary\components\LiveCapacity::$available = false;
    $result = $controller->actionStart();
    $message = $result['params']['model']->errors['title'][0];
    if ($result['view'] !== 'start' || !str_contains($message, 'zu viele gleichzeitige Streams')) {
        throw new \RuntimeException('Live capacity rejection regression');
    }
    ++$count;
    echo "$count live controller checks passed\n";
}
