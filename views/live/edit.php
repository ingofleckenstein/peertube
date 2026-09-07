<?php
use humhub\widgets\form\ActiveForm;
use yii\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><i class="fa fa-pencil"></i> Livestream-Ankündigung bearbeiten</strong></div>
    <div class="panel-body">
        <p class="text-muted">Titel, Text, Bild und geplanter Zeitpunkt werden im bestehenden Beitrag aktualisiert. Kommentare und Teilungen bleiben erhalten.</p>
        <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
        <?= $form->field($model, 'title')->textInput(['maxlength' => 120])->label('Titel') ?>
        <?= $form->field($model, 'description')->textarea(['rows' => 7])->label('Beschreibung')->hint('Emojis sind erlaubt. Schreibe [artikelbild] an die Stelle, an der das Bild und später der Livestream erscheinen soll.') ?>
        <?= $form->field($model, 'announcementImage')->fileInput(['accept' => 'image/jpeg,image/png,image/webp'])->label('Neues Ankündigungsbild')->hint('Optional. Ohne neue Datei bleibt das vorhandene Bild erhalten.') ?>
        <?= $form->field($model, 'schedule')->checkbox()->label('Livestream für später planen') ?>
        <div id="pt-live-schedule-fields">
            <?= $form->field($model, 'scheduledAt')->input('datetime-local')->label('Geplanter Beginn') ?>
            <?= $form->field($model, 'durationMinutes')->input('number', ['min' => 5, 'max' => 1440])->label('Voraussichtliche Dauer in Minuten') ?>
        </div>
        <?= Html::submitButton('<i class="fa fa-check"></i> Änderungen speichern', ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Abbrechen', $this->context->contentContainer->createUrl('/peertube/live/view', ['id' => $session->id]), ['class' => 'btn btn-default']) ?>
        <?php ActiveForm::end(); ?>
    </div>
</div>
<?php $this->registerJs("(function(){var c=document.getElementById('liveform-schedule'),f=document.getElementById('pt-live-schedule-fields');if(!c||!f)return;function t(){f.style.display=c.checked?'block':'none'}c.addEventListener('change',t);t();})();"); ?>
