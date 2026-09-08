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
<div class="pt-transcript-dock" id="<?= Html::encode($id) ?>-transcript-dock">
    <button type="button" class="pt-transcript-toggle" id="<?= Html::encode($id) ?>-transcript" disabled aria-expanded="false" aria-controls="<?= Html::encode($id) ?>-transcript-panel">
        <i class="fa fa-file-text-o" aria-hidden="true"></i>
        <span>Transkript wird erstellt.</span>
        <i class="fa fa-chevron-down pt-transcript-chevron" aria-hidden="true"></i>
    </button>
    <section class="pt-transcript-panel" id="<?= Html::encode($id) ?>-transcript-panel" hidden aria-label="Transkript">
        <div class="pt-transcript-panel-header">
            <strong>Transkript</strong>
            <button type="button" class="btn btn-default btn-xs" id="<?= Html::encode($id) ?>-transcript-copy" disabled>
                <i class="fa fa-clipboard" aria-hidden="true"></i> Kopieren
            </button>
        </div>
        <div id="<?= Html::encode($id) ?>-transcript-list" class="pt-transcript-list" aria-live="polite"></div>
    </section>
</div>
<?php
$this->registerCss(<<<'CSS'
.pt-transcript-dock { width: 100%; margin: -1px 0 0 auto; text-align: right; }
.pt-transcript-toggle { display: inline-flex; max-width: 100%; align-items: center; gap: 7px; padding: 6px 9px; border: 0; border-radius: 0 0 5px 5px; background: rgba(255, 255, 255, .82); color: #4b5a67; font-size: 12px; line-height: 16px; text-align: left; box-shadow: 0 1px 4px rgba(0, 0, 0, .13); }
.pt-transcript-toggle:hover, .pt-transcript-toggle:focus { background: rgba(255, 255, 255, .98); color: #263746; text-decoration: none; }
.pt-transcript-toggle:disabled { cursor: wait; opacity: .74; }
.pt-transcript-toggle > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pt-transcript-chevron { margin-left: 2px; transition: transform .18s ease; }
.pt-transcript-dock.is-open .pt-transcript-chevron { transform: rotate(180deg); }
.pt-transcript-panel { display: flex; max-height: calc(100vh - 24px); overflow: hidden; flex-direction: column; margin-top: 4px; border: 1px solid #dbe3e8; border-radius: 5px; background: rgba(255, 255, 255, .97); box-shadow: 0 3px 11px rgba(0, 0, 0, .12); text-align: left; }
.pt-transcript-panel-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 9px; border-bottom: 1px solid #e5eaed; color: #34495e; font-size: 12px; }
.pt-transcript-list { min-height: 0; overflow-y: auto; flex: 1 1 auto; }
.pt-transcript-cue { display: block; width: 100%; padding: 7px 9px; border: 0; border-bottom: 1px solid #f0f2f4; background: transparent; color: #87939c; font-size: 13px; line-height: 1.4; text-align: left; transition: color .16s ease, background .16s ease; }
.pt-transcript-cue:hover, .pt-transcript-cue:focus { background: #f4f8fa; color: #34495e; }
.pt-transcript-cue.is-past { color: #5c6973; }
.pt-transcript-cue.is-active { background: #e9f5f2; color: #177d70; font-weight: 700; }
.pt-transcript-cue.is-upcoming { color: #9ba6ad; }
.pt-transcript-time { display: inline-block; min-width: 42px; margin-right: 5px; color: #81909a; font-variant-numeric: tabular-nums; font-size: 11px; }
.pt-transcript-cue.is-active .pt-transcript-time { color: #177d70; }
CSS);
$js = <<<'JS'
(function (id, passwordUrl, embedUrl, transcriptUrl, title) {
    var started = false;
    var player = null;
    var cues = [];
    var activeCue = -1;
    var transcriptOpen = false;
    var pollTimer = null;
    var transcriptButton = document.getElementById(id + '-transcript');
    var transcriptDock = document.getElementById(id + '-transcript-dock');
    var transcriptPanel = document.getElementById(id + '-transcript-panel');
    var transcriptCopyButton = document.getElementById(id + '-transcript-copy');
    var transcriptList = document.getElementById(id + '-transcript-list');

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
        if (transcriptCopyButton) transcriptCopyButton.disabled = !enabled;
    }
    function renderTranscript() {
        if (!transcriptList) return;
        transcriptList.textContent = '';
        cues.forEach(function (cue, index) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'pt-transcript-cue is-upcoming';
            item.dataset.cueIndex = String(index);
            var timestamp = document.createElement('small');
            timestamp.className = 'pt-transcript-time';
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
        if (previous) {
            previous.classList.remove('is-active');
            previous.classList.add('is-past');
        }
        activeCue = next;
        var current = transcriptList && transcriptList.querySelector('[data-cue-index="' + activeCue + '"]');
        if (current) {
            current.classList.remove('is-past', 'is-upcoming');
            current.classList.add('is-active');
            if (transcriptOpen) current.scrollIntoView({block: 'nearest', behavior: 'smooth'});
        }
    }
    async function syncActiveCue() {
        if (!transcriptOpen || !player || !cues.length) return;
        try { updateActiveCue(await player.getCurrentTime()); } catch (_) { /* A loading iframe has no current time yet. */ }
    }
    function toggleTranscript() {
        transcriptOpen = !transcriptOpen;
        transcriptButton.setAttribute('aria-expanded', transcriptOpen ? 'true' : 'false');
        transcriptPanel.hidden = !transcriptOpen;
        transcriptDock.classList.toggle('is-open', transcriptOpen);
        syncActiveCue();
    }
    function transcriptText() {
        return cues.map(function (cue) { return String(cue.text || '').trim(); }).filter(Boolean).join('\n');
    }
    async function copyTranscript() {
        var text = transcriptText();
        if (!text) return;
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                var helper = document.createElement('textarea');
                helper.value = text;
                helper.setAttribute('readonly', '');
                helper.style.position = 'fixed';
                helper.style.opacity = '0';
                document.body.appendChild(helper);
                helper.select();
                if (!document.execCommand('copy')) throw new Error('Kopieren nicht verfügbar');
                helper.remove();
            }
            transcriptCopyButton.innerHTML = '<i class="fa fa-check" aria-hidden="true"></i> Kopiert';
            window.setTimeout(function () { transcriptCopyButton.innerHTML = '<i class="fa fa-clipboard" aria-hidden="true"></i> Kopieren'; }, 1800);
        } catch (_) {
            transcriptCopyButton.textContent = 'Kopieren nicht möglich';
        }
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
    if (transcriptButton) transcriptButton.addEventListener('click', toggleTranscript);
    if (transcriptCopyButton) transcriptCopyButton.addEventListener('click', copyTranscript);
    window.setInterval(syncActiveCue, 700);
    loadTranscript();
})(__PT_ID__, __PT_PASSWORD_URL__, __PT_EMBED_URL__, __PT_TRANSCRIPT_URL__, __PT_TITLE__);
JS;
$this->registerJs(strtr($js, [
    '__PT_ID__' => Json::htmlEncode($id),
    '__PT_PASSWORD_URL__' => Json::htmlEncode($passwordUrl),
    '__PT_EMBED_URL__' => Json::htmlEncode($embedUrl),
    '__PT_TRANSCRIPT_URL__' => Json::htmlEncode($transcriptUrl),
    '__PT_TITLE__' => Json::htmlEncode((string) $media->title),
]));
?>
