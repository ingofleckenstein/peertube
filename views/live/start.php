<?php
use humhub\widgets\form\ActiveForm; use yii\helpers\Html;
?>
<div class="panel panel-default"><div class="panel-heading"><strong><i class="fa fa-rss"></i> Livestream vorbereiten</strong></div><div class="panel-body">
<p class="text-muted">Lege Titel und Beschreibung fest. Danach erhältst du deine dauerhaft gültigen Zugangsdaten für OBS oder eine andere Streaming-App.</p>
<?php $form=ActiveForm::begin(['options'=>['enctype'=>'multipart/form-data']]); ?>
<?php if ($model->hasErrors()): ?>
<?= Html::errorSummary($model, ['class' => 'alert alert-danger', 'role' => 'alert']) ?>
<?php if (!empty($liveManagementUrl)): ?>
<div class="alert alert-info">
<?= Html::a('Vorhandene Livequellen auf PeerTube prüfen und entfernen', $liveManagementUrl, ['target' => '_blank', 'rel' => 'noopener noreferrer', 'class' => 'btn btn-default']) ?>
<p>Melde dich dort mit dem technischen PeerTube-Konto an, das in der Mediathek eingerichtet ist.
Auch vorbereitete, dauerhaft angelegte Livequellen können das Limit belegen, ohne dass gerade jemand sendet.
Prüfe die Liste und entferne ausschließlich nicht mehr benötigte Livequellen über deren Menü.
Dadurch werden deren bisherige Streaming-Zugangsdaten ungültig. Das Beenden in OBS allein entfernt keine dauerhafte Quelle.
Ohne Zugriff auf dieses Konto wende dich bitte mit Fehlercode und Referenz an die Administration.</p>
</div>
<?php endif; ?>
<?php endif; ?>
<p class="help-block"><small>Livestream-Diagnose:
Modul <?= Html::encode(Yii::$app->getModule('peertube')->getVersion()) ?> ·
Controller <?= Html::encode(defined('selfsein\\peertube\\controllers\\LiveController::DIAGNOSTICS_VERSION') ? \selfsein\peertube\controllers\LiveController::DIAGNOSTICS_VERSION : 'vor 2.8.9 / unbekannt') ?> ·
Fehlerbehandlung <?= Html::encode(defined('selfsein\\peertube\\components\\LiveError::VERSION') ? \selfsein\peertube\components\LiveError::VERSION : 'vor 2.8.9 / unbekannt') ?>
</small></p>
<?= $form->field($model,'title')->textInput(['maxlength'=>120])->label('Titel') ?>
<?= $form->field($model,'description')->textarea(['rows'=>5])->label('Beschreibung')->hint('Emojis sind erlaubt. Mit [artikelbild] bestimmst du, an welcher Stelle Bild beziehungsweise Livestream erscheint.') ?>
<?= $form->field($model,'announcementImage')->fileInput(['accept'=>'image/jpeg,image/png,image/webp'])->label('Ankündigungsbild')->hint('Optional: JPG, PNG oder WebP, maximal 10 MB. Es wird vor Beginn im Beitrag angezeigt und später als Vorschaubild der Aufzeichnung verwendet.') ?>
<?= $form->field($model,'schedule')->checkbox()->label('Livestream für später planen und jetzt ankündigen') ?>
<div id="pt-live-schedule-fields" style="display:none">
<?= $form->field($model,'scheduledAt')->input('datetime-local')->label('Geplanter Beginn') ?>
<?= $form->field($model,'durationMinutes')->input('number',['min'=>5,'max'=>1440])->label('Voraussichtliche Dauer in Minuten') ?>
<p class="help-block"><i class="fa fa-bullhorn"></i> Die Ankündigung erscheint sofort im Stream. Die Kalenderkopplung ist vorübergehend deaktiviert, bis die installierte Kalender-Version sicher erkannt wird.</p>
</div>
<?= $form->field($model,'consentConfirmed')->checkbox()->label('Ich darf diese Übertragung veröffentlichen und habe nötige Einwilligungen eingeholt.') ?>
<?= Html::submitButton('<i class="fa fa-arrow-right"></i> Livestream vorbereiten',['class'=>'btn btn-primary']) ?>
<?= Html::a('Abbrechen',$this->context->contentContainer->createUrl('/peertube/media/index'),['class'=>'btn btn-default']) ?>
<?php ActiveForm::end(); ?>
</div></div>
<?php $this->registerJs("(function(){var c=document.getElementById('liveform-schedule'),f=document.getElementById('pt-live-schedule-fields');if(!c||!f)return;function t(){f.style.display=c.checked?'block':'none'}c.addEventListener('change',t);t();})();"); ?>
