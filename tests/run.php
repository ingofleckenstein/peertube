<?php
// Isolated regression tests; no credentials, database or remote API required.
namespace yii\helpers {
    class FileHelper { public static function createDirectory($path, $mode = 0775) { if (!is_dir($path)) mkdir($path, $mode, true); } }
}
namespace humhub\modules\content\components { class ContentContainerController { public $contentContainer; } }
namespace yii\web {
    class ForbiddenHttpException extends \RuntimeException {}
    class NotFoundHttpException extends \RuntimeException {}
    class Response { const FORMAT_RAW = 'raw'; }
}
namespace selfsein\peertube\models {
    class SettingsForm { const DEFAULT_EMBED_DOMAINS = 'example.org'; public static function normalizeEmbedDomains($value) { return ['example.org']; } }
    class Media {
        public static $item;
        public $thumbnail_url = 'https://video.example.org/static/thumbnails/test.jpg';
        public $updated_at = '1';
        public $allowed = false;
        public static function find() { return new class { function contentContainer($x) { return $this; } function andWhere($x) { return $this; } function one() { return Media::$item; } }; }
        public static function tableName() { return 'media'; }
        public function canBeViewedBy() { return $this->allowed; }
    }
}
namespace selfsein\peertube\components {
    function mb_strlen($value) { return strlen($value); }
    function curl_init($url) { return (object)['url' => $url, 'options' => []]; }
    function curl_setopt_array($handle, $options) { $handle->options = $options; }
    function curl_exec($handle) {
        $GLOBALS['requests'][] = $handle;
        if (isset($handle->options[CURLOPT_WRITEFUNCTION])) {
            $body = $GLOBALS['image'];
            return $handle->options[CURLOPT_WRITEFUNCTION]($handle, $body) === strlen($body);
        }
        if (str_ends_with($handle->url, '/videos/live') && ($handle->options[CURLOPT_CUSTOMREQUEST] ?? '') === 'POST') return '{"video":{"uuid":"test-id"}}';
        if (str_contains($handle->url, '/videos/live/')) return '{"streamKey":"test-key","rtmpUrl":"rtmp://example.org"}';
        return '{"name":"Test","description":"Text"}';
    }
    function curl_getinfo($handle, $option) { return 200; }
    function curl_error($handle) { return ''; }
    function curl_close($handle) {}
}
namespace {
    // The test replaces cURL with namespace-local functions and must therefore
    // also run on a PHP CLI installation without the cURL extension.
    foreach (['CURLOPT_RETURNTRANSFER', 'CURLOPT_CUSTOMREQUEST', 'CURLOPT_HTTPHEADER', 'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT', 'CURLOPT_POSTFIELDS', 'CURLOPT_HEADERFUNCTION', 'CURLOPT_FOLLOWLOCATION', 'CURLOPT_PROTOCOLS', 'CURLOPT_WRITEFUNCTION', 'CURLINFO_RESPONSE_CODE', 'CURLPROTO_HTTPS'] as $index => $constant) {
        if (!defined($constant)) {
            define($constant, 10000 + $index);
        }
    }
    class Yii {
        public static $app;
        public static function getAlias($alias) { return sys_get_temp_dir() . '/peertube-regression-' . getmypid(); }
        public static function warning(...$args) {}
        public static $errors = [];
        public static function error($data, $category) { self::$errors[] = [$data, $category]; }
    }
    class Cache {
        public $data = []; public $now = 0;
        public function get($key) { $row = $this->data[$key] ?? null; return $row && $row[1] > $this->now ? $row[0] : false; }
        public function set($key, $value, $ttl) { $this->data[$key] = [$value, $this->now + $ttl]; return true; }
    }
    class App {
        public $cache; public $response;
        function getModule($id) { return (object)['settings' => new class { function get($key, $default = null) { return ['baseUrl'=>'https://video.example.org', 'username'=>'tester', 'password'=>'test-only'][$key] ?? $default; } }]; }
    }
    Yii::$app = new App(); Yii::$app->cache = new Cache();
    Yii::$app->response = (object)['headers' => new class { function set($key, $value) {} }, 'format'=>null];
    foreach (['CredentialVault', 'RemoteCache', 'ThumbnailCache', 'PeerTubeClient', 'LiveError'] as $class) require dirname(__DIR__) . '/components/' . $class . '.php';
    require dirname(__DIR__) . '/controllers/MediaController.php';
    $count = 0;
    function check($condition, $message) { global $count; if (!$condition) throw new \RuntimeException($message); ++$count; }
    function fails($callback, $message) { try { $callback(); } catch (\RuntimeException $e) { check(true, $message); return; } check(false, $message); }
    $loads = 0; $load = function () use (&$loads) { ++$loads; return ['ok'=>true]; };
    $cache = '\\selfsein\\peertube\\components\\RemoteCache';
    $cache::remember('success', 300, $load); $cache::remember('success', 300, $load);
    check($loads === 1, 'Cache hit must avoid loader');
    Yii::$app->cache->now = 301; $cache::remember('success', 300, $load); check($loads === 2, 'Expired cache must refresh');
    $failures = 0; $bad = function () use (&$failures) { ++$failures; throw new \RuntimeException('secret'); };
    fails(fn() => $cache::remember('error', 300, $bad), 'Initial failure');
    fails(fn() => $cache::remember('error', 300, $bad), 'Cooldown');
    check($failures === 1 && !str_contains(serialize(Yii::$app->cache->data), 'secret'), 'Failure cache must suppress retries without storing secrets');
    Yii::$app->cache->now += 61; fails(fn() => $cache::remember('error', 300, $bad), 'Retry after cooldown'); check($failures === 2, 'Retry permitted');
    $cache::remember('locked', 300, function () use ($cache) { fails(fn() => $cache::remember('locked', 300, fn()=>die('Duplicate load')), 'Parallel request must not load'); return 1; });
    $client = new \selfsein\peertube\components\PeerTubeClient();
    $property = new \ReflectionProperty($client, 'accessTokenCache'); $property->setValue($client, 'fake-token');
    foreach (['', '  ', 'Eine Beschreibung'] as $description) {
        $GLOBALS['requests'] = []; $client->createPermanentLive('Test', $description, 1, 'test-password');
        $fields = $GLOBALS['requests'][0]->options[CURLOPT_POSTFIELDS];
        check(trim($description) === '' ? !isset($fields['description']) : $fields['description'] === $description, 'Live description omission / preservation');
        check($fields['privacy'] === '5' && $fields['videoPasswords[0]'] === 'test-password', 'Password protection retained');
    }
    foreach (['d', 'ab', str_repeat('x', 10001)] as $invalidDescription) {
        $GLOBALS['requests'] = [];
        fails(fn()=>$client->createPermanentLive('Test', $invalidDescription, 1, 'test-password'), 'Invalid live description rejected');
        check($GLOBALS['requests'] === [], 'Validation precedes PeerTube request');
    }
    check(str_contains(\selfsein\peertube\components\LiveError::message(new \RuntimeException('max_user_lives_limit_reached')), 'technischen PeerTube-Kontos'), 'User live quota explanation');
    check(str_contains(\selfsein\peertube\components\LiveError::message(new \RuntimeException('max_instance_lives_limit_reached')), 'PeerTube-Servers'), 'Server live quota explanation');
    check(!str_contains(\selfsein\peertube\components\LiveError::message(new \RuntimeException('secret-payload')), 'secret-payload'), 'No raw remote errors in UI');
    $quota = new \RuntimeException('PeerTube API (403): {"type":"https://docs.joinpeertube.org/api-rest-reference.html#section/Errors/max_user_lives_limit_reached","detail":"Cannot create this live because the max user lives limit is reached.","status":403,"code":"max_user_lives_limit_reached"}');
    $liveError = \selfsein\peertube\components\LiveError::class;
    check($liveError::code($quota) === 'PT-LIVE-403-USER-LIMIT', 'Actual production response classified');
    check($liveError::code(new \RuntimeException('wrapper', 0, $quota)) === 'PT-LIVE-403-USER-LIMIT', 'Nested origin classified');
    foreach ([400, 401, 403, 404, 429, 500, 503] as $status) {
        check($liveError::code(new \RuntimeException("PeerTube API ($status): secret")) === "PT-LIVE-HTTP-$status", 'HTTP status preserved');
    }
    check($liveError::isPeerTubeNotFound(new \RuntimeException('PeerTube API (404): secret')), 'Missing PeerTube source classified');
    check($liveError::code(new \RuntimeException('secret')) === 'PT-LIVE-UNKNOWN', 'Unknown origin not invented');
    check($liveError::managementUrl($quota, 'https://video.example.org/') === 'https://video.example.org/my-library/videos', 'Management route uses configured host');
    foreach (['javascript:alert(1)', '//evil.example', 'https://user:secret@example.org', "https://example.org\n", 'https://example.org?token=secret', 'https://example.org\\@evil.org'] as $unsafe) {
        check($liveError::managementUrl($quota, $unsafe) === null, 'Unsafe management URL rejected');
    }
    check($liveError::managementUrl(new \RuntimeException('unknown'), 'https://example.org') === null, 'No irrelevant recovery link');
    $reported = $liveError::report($quota, 'prepare-source', '2.8.9');
    $entry = Yii::$errors[0];
    check(str_contains($reported, $entry[0]['reference']) && strlen($entry[0]['reference']) === 12, 'Visible reference matches log');
    check($entry[0]['code'] === 'PT-LIVE-403-USER-LIMIT' && $entry[1] === 'peertube.live', 'Structured error code and category');
    $liveError::report(new \RuntimeException('secret token <script>'), 'prepare-source', '2.8.9');
    check(!str_contains(json_encode(Yii::$errors), 'secret'), 'No sensitive exception payload logged');
    check(Yii::$errors[0][0]['reference'] !== Yii::$errors[1][0]['reference'], 'Each occurrence has own reference');
    $GLOBALS['requests'] = []; $client->getCachedVideo('test'); $client->getCachedVideo('test'); check(count($GLOBALS['requests']) === 1, 'Metadata cache');
    $client->getVideo('test'); check(count($GLOBALS['requests']) === 2, 'Fresh verification bypasses cache');
    $thumb = '\\selfsein\\peertube\\components\\ThumbnailCache';
    foreach (['https://evil.example/static/thumbnails/a.jpg', 'http://video.example.org/static/thumbnails/a.jpg', 'https://video.example.org/api/v1/config', 'https://u:p@video.example.org/static/thumbnails/a.jpg', 'https://video.example.org/static/thumbnails/a.jpg?x=y'] as $url) {
        fails(fn() => $thumb::get($url, 'https://video.example.org', '1'), 'Unsafe thumbnail rejected');
    }
    $GLOBALS['image'] = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    $GLOBALS['requests'] = []; $url = 'https://video.example.org/static/thumbnails/test.jpg';
    $thumb::get($url, 'https://video.example.org', '1'); $thumb::get($url, 'https://video.example.org', '1');
    check(count($GLOBALS['requests']) === 1, 'Image cached');
    $thumb::get($url, 'https://video.example.org', '2'); check(count($GLOBALS['requests']) === 2, 'Changed media refreshes image');
    $GLOBALS['image'] = '<svg onload="alert(1)"></svg>'; fails(fn()=>$thumb::get($url,'https://video.example.org','3'), 'Non-raster response rejected');
    $GLOBALS['image'] = str_repeat('a', 5 * 1024 * 1024 + 1); fails(fn()=>$thumb::get($url,'https://video.example.org','4'), 'Oversize rejected');
    $controller = new \selfsein\peertube\controllers\MediaController();
    $controller->contentContainer = (object)['moduleManager'=>new class { function isEnabled($id) { return true; } }];
    \selfsein\peertube\models\Media::$item = new \selfsein\peertube\models\Media();
    $before = count($GLOBALS['requests']);
    fails(fn()=>$controller->actionThumbnail(1), 'Unauthorized access rejected even for cached image');
    check(count($GLOBALS['requests']) === $before, 'Authorization precedes download');
    \selfsein\peertube\models\Media::$item->allowed = true;
    check($controller->actionThumbnail(1) !== '', 'Authorized cached image delivered');
    \selfsein\peertube\models\Media::$item = null; fails(fn()=>$controller->actionThumbnail(1), 'Deleted record rejected');
    foreach (glob(Yii::getAlias('') . '/*.lock') as $file) unlink($file); rmdir(Yii::getAlias(''));
    echo "$count regression checks passed\n";
}
