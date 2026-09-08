<?php

use humhub\widgets\form\ActiveForm;
use yii\helpers\Html;
use community\videolibrary\models\SettingsForm;
use humhub\modules\topic\widgets\TopicPicker;
$folderOptions = \yii\helpers\ArrayHelper::map($folders, 'id', 'name');
$videoAddress = rtrim((string) Yii::$app->getModule('peertube')->settings->get('baseUrl', ''), '/');
$uploadTarget = $videoAddress !== '' ? $videoAddress : 'dem Videoserver';

?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Video oder Audio hochladen</strong></div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin(['id' => 'peertube-upload-form', 'options' => [
            'enctype' => 'multipart/form-data',
            'data-direct-init-url' => $this->context->contentContainer->createUrl('/peertube/media/direct-init'),
            'data-direct-finalize-url' => $this->context->contentContainer->createUrl('/peertube/media/direct-finalize'),
            'data-direct-cancel-url' => $this->context->contentContainer->createUrl('/peertube/media/direct-cancel'),
        ]]); ?>
        <?= Html::activeHiddenInput($model, 'uploadToken') ?>
        <?= Html::activeHiddenInput($model, 'thumbnailData', ['id' => 'peertube-thumbnail-data']) ?>
        <?= $form->field($model, 'title')->textInput()->label('Titel') ?>
        <?= $form->field($model, 'description')->textarea(['rows' => 4])->label('Beschreibung')->hint('Emojis sind erlaubt. Mit [artikelbild] bestimmst du die Position des Videos im Beitrag.') ?>
        <?= $form->field($model, 'mediaFile')->fileInput(['accept' => 'video/*,audio/*'])->label('Datei') ?>
        <?= $form->field($model, 'publishToStream')->checkbox()->label('Im Stream veröffentlichen')->hint('Standardmäßig aktiviert. Deaktivieren, wenn das Video nur in der Mediathek erscheinen soll.') ?>
        <div class="pt-form-options">
            <details<?= $model->hasErrors('topics') ? ' open' : '' ?>><summary><i class="fa fa-tags"></i> Themen</summary><div class="pt-option-body"><?= $form->field($model, 'topics')->widget(TopicPicker::class, ['contentContainer' => $this->context->contentContainer])->label(false)->hint('Dieselben globalen und Space-Themen wie bei HumHub-Streambeiträgen.') ?></div></details>
            <?php if ($folderOptions): ?><details<?= $model->hasErrors('folderId') ? ' open' : '' ?>><summary><i class="fa fa-folder"></i> Ordner / Videoreihe</summary><div class="pt-option-body"><?= $form->field($model, 'folderId')->dropDownList($folderOptions, ['prompt'=>'Ohne Ordner'])->label(false) ?></div></details><?php endif; ?>
            <details<?= $model->hasErrors('contentWarnings') ? ' open' : '' ?>><summary><i class="fa fa-exclamation-triangle"></i> Content Warning</summary><div class="pt-option-body"><?= $form->field($model, 'contentWarnings')->checkboxList(SettingsForm::getWarningCategoryOptions())->label(false)->hint('Keine Auswahl bedeutet: keine vorgeschaltete Warnung.') ?></div></details>
            <details<?= ($model->hasErrors('thumbnailFile') || $model->hasErrors('thumbnailTimestamp') || $model->hasErrors('thumbnailData')) ? ' open' : '' ?>><summary><i class="fa fa-image"></i> Vorschaubild</summary><div class="pt-option-body">
                <?= $form->field($model, 'thumbnailFile')->fileInput(['accept'=>'image/jpeg,image/png,image/webp'])->label('Eigenes Bild hochladen')->hint('JPG, PNG oder WebP, maximal 10 MB. Diese Auswahl hat Vorrang vor dem Zeitpunkt.') ?>
                <?= $form->field($model, 'thumbnailTimestamp')->textInput(['placeholder'=>'z. B. 01:25 oder 85'])->label('Oder Bild aus dem Video')->hint('Zeitpunkt als Minuten:Sekunden oder Sekunden. Das Bild wird vor dem Upload in deinem Browser erzeugt.') ?>
                <div id="peertube-thumbnail-error" class="text-danger" role="alert"></div><img id="peertube-thumbnail-preview" alt="Vorschau" style="display:none;max-width:320px;width:100%;margin-top:8px;border-radius:6px">
            </div></details>
        </div>
        <?= $form->field($model, 'consentConfirmed')->checkbox()->label('Ich bestätige, dass ich berechtigt bin, diese Aufnahme in der Community zu veröffentlichen und dass abgebildete oder hörbare Personen zugestimmt haben.') ?>
        <p class="help-block">Die Datei wird direkt und in mehreren Blöcken an den Videoserver übertragen. Das Browserfenster bitte geöffnet lassen.</p>
        <div id="direct-upload-progress" class="pt-upload-progress" hidden aria-live="polite">
            <div class="pt-upload-status"><strong id="direct-upload-status">Upload wird vorbereitet …</strong><span id="direct-upload-percent">0 %</span></div>
            <div class="progress"><div id="direct-upload-bar" class="progress-bar progress-bar-striped active" role="progressbar" style="width:0%"></div></div>
            <div id="direct-upload-details" class="help-block"></div>
            <button type="button" id="direct-upload-abort" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Upload abbrechen</button>
        </div>
        <div id="direct-upload-error" class="alert alert-danger" hidden role="alert"></div>
        <?= Html::submitButton('<i class="fa fa-upload"></i> Auf ' . Html::encode($uploadTarget) . ' hochladen', ['class' => 'btn btn-primary', 'id' => 'peertube-upload-submit']) ?>
        <?= Html::a('Abbrechen', $this->context->contentContainer->createUrl('/peertube/media/index'), ['class' => 'btn btn-light']) ?>
        <?php ActiveForm::end(); ?>
    </div>
</div>
<style>.pt-form-options{margin:16px 0}.pt-form-options details{border:1px solid #dfe3e7;border-radius:6px;margin-bottom:8px;background:#fff}.pt-form-options summary{cursor:pointer;padding:11px 13px;font-weight:600;list-style:none}.pt-form-options summary::-webkit-details-marker{display:none}.pt-form-options summary:after{content:'\f078';font-family:FontAwesome;float:right;color:#71808e}.pt-form-options details[open] summary:after{content:'\f077'}.pt-option-body{padding:4px 14px 12px;border-top:1px solid #edf0f2}.pt-option-body .form-group:last-child{margin-bottom:0}.pt-upload-progress{margin:16px 0;padding:14px;border:1px solid #dfe3e7;border-radius:6px;background:#f8fafb}.pt-upload-status{display:flex;justify-content:space-between;margin-bottom:8px}.pt-upload-progress .progress{height:14px;margin-bottom:6px}.pt-upload-progress .help-block{margin:4px 0 10px}</style>
<?php $this->registerJs(<<<'JS'
(function () {
    var form = $('#peertube-upload-form');
    var generating = false, uploading = false, cancelled = false, currentRequest = null, uploadSession = null;
    document.querySelectorAll('.pt-form-options details').forEach(function (section) {
        section.addEventListener('toggle', function () {
            if (!section.open) return;
            document.querySelectorAll('.pt-form-options details').forEach(function (other) { if (other !== section) other.open = false; });
        });
    });
    function seconds(value) {
        if (!value) return null;
        if (/^\d+(\.\d+)?$/.test(value)) return parseFloat(value);
        var parts = value.split(':').map(Number), result = 0;
        if (parts.some(isNaN)) return NaN;
        parts.forEach(function (part) { result = result * 60 + part; });
        return result;
    }
    form.on('beforeSubmit', function () {
        if (uploading || generating) return false;
        var timeValue = $('#uploadform-thumbnailtimestamp').val().trim();
        var imageFile = $('#uploadform-thumbnailfile')[0].files[0];
        var videoFile = $('#uploadform-mediafile')[0].files[0];
        var target = seconds(timeValue);
        if (!timeValue || imageFile || $('#peertube-thumbnail-data').val()) {
            startDirectUpload();
            return false;
        }
        if (!videoFile || !videoFile.type.match(/^video\//) || !isFinite(target) || target < 0) {
            $('#peertube-thumbnail-error').text('Für diesen Zeitpunkt muss eine Videodatei ausgewählt und eine gültige Zeit eingegeben sein.');
            return false;
        }
        generating = true;
        var video = document.createElement('video');
        var url = URL.createObjectURL(videoFile);
        video.preload = 'metadata';
        video.muted = true;
        video.onloadedmetadata = function () {
            if (target > video.duration) { fail('Der Zeitpunkt liegt hinter dem Ende des Videos.'); return; }
            video.currentTime = target;
        };
        video.onseeked = function () {
            var scale = Math.min(1, 1280 / video.videoWidth);
            var canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
            canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            var data = canvas.toDataURL('image/jpeg', 0.88);
            $('#peertube-thumbnail-data').val(data);
            $('#peertube-thumbnail-preview').attr('src', data).show();
            URL.revokeObjectURL(url);
            generating = false;
            startDirectUpload();
        };
        video.onerror = function () { fail('Aus dieser Datei konnte kein Vorschaubild erzeugt werden. Bitte lade stattdessen ein Bild hoch.'); };
        function fail(message) { URL.revokeObjectURL(url); generating = false; $('#peertube-thumbnail-error').text(message); }
        video.src = url;
        return false;
    });
    $('#uploadform-thumbnailtimestamp,#uploadform-mediafile').on('change input', function () { $('#peertube-thumbnail-data').val(''); $('#peertube-thumbnail-preview').hide(); $('#peertube-thumbnail-error').text(''); });

    function startDirectUpload() {
        var fileInput = $('#uploadform-mediafile')[0];
        var file = fileInput && fileInput.files[0];
        if (!file) return showError('Bitte wähle eine Video- oder Audiodatei aus.');
        uploading = true;
        cancelled = false;
        $('#direct-upload-error').prop('hidden', true).text('');
        $('#direct-upload-progress').prop('hidden', false);
        $('#peertube-upload-submit').prop('disabled', true).text('Upload läuft …');
        updateProgress(0, file.size, 'Upload wird vorbereitet …');

        var initData = new FormData(form[0]);
        initData.delete('UploadForm[mediaFile]');
        initData.delete('UploadForm[thumbnailFile]');
        initData.delete('UploadForm[thumbnailData]');
        initData.append('fileName', file.name);
        initData.append('fileType', file.type || 'application/octet-stream');
        initData.append('fileSize', String(file.size));

        postForm(form.data('direct-init-url'), initData).then(function (session) {
            if (cancelled) return;
            uploadSession = session;
            return uploadChunks(file, session.uploadUrl, Number(session.chunkSize) || 8388608);
        }).then(function (video) {
            if (!video || cancelled) return;
            return finalizeUpload(video);
        }).catch(failUpload);
    }

    function uploadChunks(file, url, chunkSize) {
        var offset = 0, startedAt = Date.now();
        function next() {
            if (cancelled) return Promise.reject(new Error('Upload abgebrochen.'));
            if (offset >= file.size) return Promise.reject(new Error('Der Videoserver hat den Abschluss des Uploads nicht bestätigt.'));
            var end = Math.min(file.size, offset + chunkSize) - 1;
            return sendChunk(file.slice(offset, end + 1), url, offset, end, file.size, startedAt, 0).then(function (result) {
                if (result.video) return result.video;
                offset = result.nextOffset;
                return next();
            });
        }
        return next();
    }

    function sendChunk(blob, url, start, end, total, startedAt, attempt) {
        return new Promise(function (resolve, reject) {
            var xhr = currentRequest = new XMLHttpRequest();
            var idleTimer = null, idleTimedOut = false;
            function clearIdleTimer() {
                if (idleTimer !== null) window.clearTimeout(idleTimer);
                idleTimer = null;
            }
            function resetIdleTimer() {
                clearIdleTimer();
                idleTimer = window.setTimeout(function () {
                    idleTimedOut = true;
                    xhr.abort();
                }, 60000);
            }
            xhr.open('PUT', url, true);
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');
            xhr.setRequestHeader('Content-Range', 'bytes ' + start + '-' + end + '/' + total);
            xhr.upload.onprogress = function (event) {
                resetIdleTimer();
                if (event.lengthComputable) updateProgress(start + event.loaded, total, 'Datei wird übertragen …', startedAt);
            };
            xhr.onload = function () {
                clearIdleTimer();
                currentRequest = null;
                if (xhr.status === 200) {
                    try { resolve({ video: JSON.parse(xhr.responseText) }); }
                    catch (e) { reject(new Error('Der Videoserver lieferte nach dem Upload keine gültige Antwort.')); }
                    return;
                }
                if (xhr.status === 308) {
                    var range = xhr.getResponseHeader('Range');
                    var match = range && /bytes=0-(\d+)/.exec(range);
                    resolve({ nextOffset: match ? Number(match[1]) + 1 : end + 1 });
                    return;
                }
                if ((xhr.status === 408 || xhr.status === 429 || xhr.status >= 500) && attempt < 4) {
                    return retryChunk(blob, url, start, end, total, startedAt, attempt, resolve, reject, xhr.getResponseHeader('Retry-After'));
                }
                reject(new Error(responseMessage(xhr) || 'Upload fehlgeschlagen (HTTP ' + xhr.status + ').'));
            };
            xhr.onerror = function () {
                clearIdleTimer();
                currentRequest = null;
                if (attempt < 4 && !cancelled) return retryChunk(blob, url, start, end, total, startedAt, attempt, resolve, reject);
                reject(new Error('Die Verbindung zum Videoserver wurde unterbrochen.'));
            };
            xhr.onabort = function () {
                clearIdleTimer();
                currentRequest = null;
                if (idleTimedOut && attempt < 4 && !cancelled) {
                    updateProgress(start, total, 'Seit 60 Sekunden keine Daten – Block wird erneut gesendet …', startedAt);
                    return retryChunk(blob, url, start, end, total, startedAt, attempt, resolve, reject);
                }
                reject(new Error(idleTimedOut ? 'Der Upload hat seit 60 Sekunden keine Daten übertragen.' : 'Upload abgebrochen.'));
            };
            resetIdleTimer();
            xhr.send(blob);
        });
    }

    function retryChunk(blob, url, start, end, total, startedAt, attempt, resolve, reject, retryAfter) {
        var delay = retryAfter ? Math.min(30000, Number(retryAfter) * 1000) : Math.min(16000, 1000 * Math.pow(2, attempt));
        updateProgress(start, total, 'Verbindung unterbrochen – neuer Versuch in ' + Math.ceil(delay / 1000) + ' Sekunden …', startedAt);
        window.setTimeout(function () {
            if (cancelled) return reject(new Error('Upload abgebrochen.'));
            sendChunk(blob, url, start, end, total, startedAt, attempt + 1).then(resolve, reject);
        }, delay);
    }

    function finalizeUpload(video) {
        updateProgress(1, 1, 'Upload abgeschlossen – Video wird verarbeitet …');
        var data = new FormData(form[0]);
        data.delete('UploadForm[mediaFile]');
        data.append('uploadToken', uploadSession.uploadToken);
        var actualVideo = video.video || video;
        data.append('video[id]', String(actualVideo.id || ''));
        data.append('video[uuid]', String(actualVideo.uuid || ''));
        data.append('completionProof', String(video._humhubProof || ''));
        return postForm(form.data('direct-finalize-url'), data).then(function (result) {
            $('#direct-upload-bar').removeClass('active progress-bar-striped');
            $('#direct-upload-status').text('Fertig');
            window.location.assign(result.redirectUrl);
        });
    }

    $('#direct-upload-abort').on('click', function () {
        if (!uploading || cancelled) return;
        cancelled = true;
        if (currentRequest) currentRequest.abort();
        if (uploadSession && uploadSession.uploadUrl) fetch(uploadSession.uploadUrl, { method: 'DELETE', mode: 'cors' }).catch(function () {});
        if (uploadSession && uploadSession.uploadToken) {
            var data = new FormData();
            data.append(yii.getCsrfParam(), yii.getCsrfToken());
            data.append('uploadToken', uploadSession.uploadToken);
            postForm(form.data('direct-cancel-url'), data).catch(function () {});
        }
        failUpload(new Error('Upload abgebrochen.'));
    });

    function postForm(url, data) {
        return fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (response) {
            return response.text().then(function (text) {
                var result;
                try { result = JSON.parse(text); } catch (e) { result = null; }
                if (!response.ok || !result) throw new Error(result && (result.error || result.message) ? (result.error || result.message) : 'Der Server lieferte keine gültige Antwort.');
                return result;
            });
        });
    }

    function updateProgress(loaded, total, status, startedAt) {
        var percent = total > 0 ? Math.min(100, Math.round(loaded / total * 100)) : 0;
        $('#direct-upload-status').text(status);
        $('#direct-upload-percent').text(percent + ' %');
        $('#direct-upload-bar').css('width', percent + '%').attr('aria-valuenow', percent);
        var details = humanBytes(loaded) + ' von ' + humanBytes(total);
        if (startedAt && loaded > 0) {
            var secondsElapsed = Math.max(1, (Date.now() - startedAt) / 1000);
            var speed = loaded / secondsElapsed;
            var remaining = speed > 0 ? Math.ceil((total - loaded) / speed) : 0;
            details += ' · ' + humanBytes(speed) + '/s' + (remaining > 0 ? ' · noch ca. ' + humanTime(remaining) : '');
        }
        $('#direct-upload-details').text(details);
    }

    function humanBytes(value) {
        var units = ['B', 'KB', 'MB', 'GB', 'TB'], index = 0, number = Number(value) || 0;
        while (number >= 1024 && index < units.length - 1) { number /= 1024; index++; }
        return (index === 0 ? Math.round(number) : number.toFixed(1)) + ' ' + units[index];
    }
    function humanTime(value) { return value < 60 ? value + ' Sek.' : Math.ceil(value / 60) + ' Min.'; }
    function responseMessage(xhr) {
        try { var data = JSON.parse(xhr.responseText); return data.error || data.message || ''; } catch (e) { return ''; }
    }
    function showError(message) { $('#direct-upload-error').prop('hidden', false).text(message); }
    function failUpload(error) {
        if (!uploading && !cancelled) return;
        uploading = false;
        $('#peertube-upload-submit').prop('disabled', false).html('<i class="fa fa-upload"></i> Erneut versuchen');
        $('#direct-upload-status').text(cancelled ? 'Upload abgebrochen' : 'Upload fehlgeschlagen');
        showError(error && error.message ? error.message : 'Der Upload ist fehlgeschlagen.');
    }
})();
JS
); ?>
