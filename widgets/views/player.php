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
$transcriptUrl = $media->content->container->createUrl('/peertube/media/transcript', ['id' => $media->id]);
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
<div class="pt-transcript-control mt-2">
    <button type="button" class="btn btn-default btn-sm" id="<?= Html::encode($id) ?>-transcript" disabled aria-expanded="false">
        <i class="fa fa-file-text-o" aria-hidden="true"></i> <span>Transkript wird erstellt.</span>
    </button>
</div>
<div class="modal fade" id="<?= Html::encode($id) ?>-transcript-modal" tabindex="-1" role="dialog" aria-labelledby="<?= Html::encode($id) ?>-transcript-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="<?= Html::encode($id) ?>-transcript-title">Transkript</h4>
            </div>
            <div class="modal-body">
                <div id="<?= Html::encode($id) ?>-transcript-list" class="list-group" aria-live="polite"></div>
            </div>
        </div>
    </div>
</div>
<?php
$js = <<<'JS'
(function (id, passwordUrl, embedUrl, transcriptUrl, title) {
    var started = false;
    var player = null;
    var cues = [];
    var activeCue = -1;
    var transcriptOpen = false;
    var pollTimer = null;
    var transcriptButton = document.getElementById(id + '-transcript');
    var transcriptList = document.getElementById(id + '-transcript-list');
    var transcriptModal = document.getElementById(id + '-transcript-modal');

    function formatTime(seconds) {
        seconds = Math.max(0, Math.floor(Number(seconds) || 0));
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var remaining = seconds % 60;
        return (hours ? String(hours).padStart(2, '0') + ':' : '') + String(minutes).padStart(2, '0') + ':' + String(remaining).padStart(2, '0');
    }
    function setTranscriptLabel(label, enabled) {
        if (!transcriptButton) return;
        transcriptButton.disabled = !enabled;
        transcriptButton.querySelector('span').textContent = label;
    }
    function renderTranscript() {
        if (!transcriptList) return;
        transcriptList.textContent = '';
        cues.forEach(function (cue, index) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item text-start';
            item.dataset.cueIndex = String(index);
            var timestamp = document.createElement('small');
            timestamp.className = 'text-muted me-2';
            timestamp.textContent = formatTime(cue.start);
            item.appendChild(timestamp);
            item.appendChild(document.createTextNode(' ' + cue.text));
            item.addEventListener('click', async function () {
                if (!player) await start();
                if (player) {
                    try { await player.seek(Number(cue.start)); } catch (_) { /* Player controls remain usable as fallback. */ }
                }
            });
            transcriptList.appendChild(item);
        });
    }
    function updateActiveCue(seconds) {
        var next = -1;
        for (var index = 0; index < cues.length; index++) {
            if (seconds >= Number(cues[index].start) && seconds < Number(cues[index].end)) { next = index; break; }
        }
        if (next === activeCue) return;
        var previous = transcriptList && transcriptList.querySelector('[data-cue-index="' + activeCue + '"]');
        if (previous) previous.classList.remove('active');
        activeCue = next;
        var current = transcriptList && transcriptList.querySelector('[data-cue-index="' + activeCue + '"]');
        if (current) {
            current.classList.add('active');
            if (transcriptOpen) current.scrollIntoView({block: 'nearest', behavior: 'smooth'});
        }
    }
    async function syncActiveCue() {
        if (!transcriptOpen || !player || !cues.length) return;
        try { updateActiveCue(await player.getCurrentTime()); } catch (_) { /* A loading iframe has no current time yet. */ }
    }
    function showTranscript() {
        transcriptOpen = true;
        transcriptButton.setAttribute('aria-expanded', 'true');
        if (window.jQuery && window.jQuery.fn.modal) window.jQuery(transcriptModal).modal('show');
        else { transcriptModal.style.display = 'block'; transcriptModal.classList.add('in'); transcriptModal.setAttribute('aria-hidden', 'false'); }
        syncActiveCue();
    }
    function hideTranscript() {
        transcriptOpen = false;
        transcriptButton.setAttribute('aria-expanded', 'false');
    }
    async function loadTranscript() {
        try {
            var response = await fetch(transcriptUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Nicht verfügbar');
            var data = await response.json();
            if (data.status === 'ready' && Array.isArray(data.cues)) {
                cues = data.cues;
                renderTranscript();
                setTranscriptLabel('Transkript öffnen', true);
                if (pollTimer) { window.clearInterval(pollTimer); pollTimer = null; }
                return;
            }
            setTranscriptLabel(data.message || (data.status === 'unavailable' ? 'Transkript nicht verfügbar' : 'Transkript wird erstellt.'), false);
            if (data.status === 'processing' && !pollTimer) pollTimer = window.setInterval(loadTranscript, 15000);
        } catch (_) {
            setTranscriptLabel('Transkript wird erstellt.', false);
            if (!pollTimer) pollTimer = window.setInterval(loadTranscript, 15000);
        }
    }
    async function start() {
        if (started) return player;
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
            player = new window.PeerTubePlayer(frame);
            await player.setVideoPassword(data.password);
            try { await player.play(); } catch (_) { /* Browser retains the visible play control as fallback. */ }
            loadTranscript();
            return player;
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
    if (transcriptButton) transcriptButton.addEventListener('click', showTranscript);
    if (transcriptModal && window.jQuery) window.jQuery(transcriptModal).on('hidden.bs.modal', hideTranscript);
})(%s, %s, %s, %s, %s);
JS;
$this->registerJs(sprintf($js, Json::htmlEncode($id), Json::htmlEncode($passwordUrl), Json::htmlEncode($embedUrl), Json::htmlEncode($transcriptUrl), Json::htmlEncode((string) $media->title)));
?>
