<?php

use yii\helpers\Html;
use yii\helpers\Json;

$baseUrl = rtrim(Yii::$app->getModule('peertube')->settings->get('baseUrl', ''), '/');
$id = 'peertube-player-' . (int) $media->id . '-' . substr(md5($this->context->id), 0, 8);
$request = Yii::$app->request;
$start = $request instanceof \yii\web\Request
    ? min(604800, max(0, (int) $request->get('t', 0)))
    : 0;
$embedParameters = [
    'api' => 1,
    'waitPasswordFromEmbedAPI' => 1,
    'p2p' => 0,
    'warningTitle' => 0,
    'peertubeLink' => 0,
    'humhubUrl' => $media->getPermalink(true),
];
if ($clickToLoad) {
    // This is only used after an explicit click on the thumbnail/warning.
    $embedParameters['autoplay'] = 1;
}
if ($start > 0) {
    $embedParameters['start'] = $start;
}
$embedUrl = $baseUrl . '/videos/embed/' . rawurlencode($media->peertube_uuid)
    . '?' . http_build_query($embedParameters, '', '&', PHP_QUERY_RFC3986);
$passwordUrl = $media->content->container->createUrl('/peertube/media/password', ['id' => $media->id]);
$thumbnailUrl = $media->getDisplayThumbnailUrl();
?>
<div class="ratio ratio-16x9 bg-light" id="<?= Html::encode($id) ?>-container">
    <?php if ($warnings): ?>
        <div id="<?= Html::encode($id) ?>-warning" class="d-flex flex-column justify-content-center align-items-center text-center bg-light p-4 h-100">
            <i class="fa fa-exclamation-triangle fa-2x text-warning mb-2" aria-hidden="true"></i>
            <strong>Content Warning</strong>
            <p class="mb-2">Dieses Medium enthält folgende möglicherweise belastende Inhalte:</p>
            <ul class="text-start mb-3">
                <?php foreach ($warnings as $warning): ?><li><?= Html::encode($warning) ?></li><?php endforeach; ?>
            </ul>
            <button type="button" class="btn btn-primary" id="<?= Html::encode($id) ?>-reveal">Medium trotzdem anzeigen</button>
        </div>
    <?php elseif ($clickToLoad): ?>
        <button type="button" id="<?= Html::encode($id) ?>-load" class="peertube-load-button" aria-label="<?= Html::encode($media->title) ?> abspielen"<?= $thumbnailUrl !== '' ? ' style="background-image:url(&quot;' . Html::encode($thumbnailUrl) . '&quot;)"' : '' ?>>
            <span class="peertube-load-play"><i class="fa fa-play" aria-hidden="true"></i></span>
            <span class="visually-hidden">Video laden und abspielen</span>
        </button>
    <?php endif; ?>
    <?php if (!$warnings && !$clickToLoad): ?>
        <iframe id="<?= Html::encode($id) ?>" src="about:blank" data-src="<?= Html::encode($embedUrl) ?>" title="<?= Html::encode($media->title) ?>" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen sandbox="allow-same-origin allow-scripts allow-popups allow-forms"></iframe>
    <?php endif; ?>
</div>
<?php
$js = <<<'JS'
(function (id, passwordUrl, embedUrl, title) {
    var started = false;
    async function start() {
        if (started) return;
        started = true;
        var frame = document.getElementById(id);
        var warning = document.getElementById(id + '-warning');
        var placeholder = document.getElementById(id + '-load');
        try {
            var response = await fetch(passwordUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Zugriff verweigert');
            var data = await response.json();
            if (!frame) {
                frame = document.createElement('iframe');
                frame.id = id;
                frame.title = title;
                frame.loading = 'lazy';
                frame.setAttribute('allow', 'fullscreen; picture-in-picture');
                frame.setAttribute('allowfullscreen', '');
                frame.setAttribute('sandbox', 'allow-same-origin allow-scripts allow-popups allow-forms');
                document.getElementById(id + '-container').appendChild(frame);
            }
            frame.src = embedUrl;
            if (warning) warning.remove();
            if (placeholder) placeholder.remove();
            var player = new window.PeerTubePlayer(frame);
            await player.setVideoPassword(data.password);
            try { await player.play(); } catch (_) { /* Browser retains the visible play control as fallback. */ }
        } catch (error) {
            started = false;
            if (warning || placeholder) {
                var message = document.createElement('p');
                message.className = 'text-danger mt-2';
                message.textContent = 'Das geschützte Video konnte nicht geladen werden.';
                (warning || placeholder).appendChild(message);
            }
        }
    }
    var button = document.getElementById(id + '-reveal') || document.getElementById(id + '-load');
    if (button) button.addEventListener('click', start); else start();
})(%s, %s, %s, %s);
JS;
$this->registerJs(sprintf($js, Json::htmlEncode($id), Json::htmlEncode($passwordUrl), Json::htmlEncode($embedUrl), Json::htmlEncode((string) $media->title)));
?>
