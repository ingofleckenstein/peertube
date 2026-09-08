<?php
use humhub\widgets\form\ActiveForm;
use yii\helpers\Html;
use community\videolibrary\models\SettingsForm;
use humhub\modules\topic\widgets\TopicPicker;
$folderOptions = \yii\helpers\ArrayHelper::map($folders, 'id', 'name');
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Video bearbeiten</strong></div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
        <?= $form->field($model, 'title')->textInput()->label('Titel') ?>
        <?= $form->field($model, 'description')->textarea(['rows' => 5])->label('Beschreibung')->hint('Emojis sind erlaubt. Mit [artikelbild] bestimmst du die Position des Videos im Beitrag.') ?>
        <?= $form->field($model, 'publishToStream')->checkbox()->label('Im Stream veröffentlichen') ?>
        <div class="pt-form-options">
            <details<?= $model->hasErrors('topics') ? ' open' : '' ?>><summary><i class="fa fa-tags"></i> Themen</summary><div class="pt-option-body"><?= $form->field($model, 'topics')->widget(TopicPicker::class, ['contentContainer' => $this->context->contentContainer])->label(false)->hint('Die Auswahl wird auch am HumHub-Streameintrag angezeigt.') ?></div></details>
            <?php if ($folderOptions): ?><details<?= $model->hasErrors('folderId') ? ' open' : '' ?>><summary><i class="fa fa-folder"></i> Ordner / Videoreihe</summary><div class="pt-option-body"><?= $form->field($model, 'folderId')->dropDownList($folderOptions, ['prompt'=>'Ohne Ordner'])->label(false) ?></div></details><?php endif; ?>
            <details<?= $model->hasErrors('contentWarnings') ? ' open' : '' ?>><summary><i class="fa fa-exclamation-triangle"></i> Content Warning</summary><div class="pt-option-body"><?= $form->field($model, 'contentWarnings')->checkboxList(SettingsForm::getWarningCategoryOptions())->label(false) ?></div></details>
            <details<?= $model->hasErrors('thumbnailFile') ? ' open' : '' ?>><summary><i class="fa fa-image"></i> Vorschaubild</summary><div class="pt-option-body"><?= $form->field($model, 'thumbnailFile')->fileInput(['accept'=>'image/jpeg,image/png,image/webp'])->label('Neues Vorschaubild hochladen')->hint('JPG, PNG oder WebP, maximal 10 MB. Ohne Auswahl bleibt das aktuelle Bild erhalten.') ?></div></details>
        </div>
        <?= Html::submitButton('Speichern', ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Abbrechen', $this->context->contentContainer->createUrl('/peertube/media/index'), ['class' => 'btn btn-light']) ?>
        <?php ActiveForm::end(); ?>
        <details class="pt-danger-zone">
            <summary><i class="fa fa-ellipsis-h"></i> Weitere Aktionen</summary>
            <div class="pt-danger-content">
                <strong>Video löschen</strong>
                <p class="text-muted">Das Video wird aus der Mediathek entfernt<?= Yii::$app->getModule('peertube')->settings->get('deleteRemote', true) ? ' und auch auf dem Videoserver gelöscht' : '' ?>.</p>
                <?= Html::a('<i class="fa fa-trash"></i> Video endgültig löschen', $this->context->contentContainer->createUrl('/peertube/media/delete', ['id' => $media->id]), ['class' => 'btn btn-danger', 'data-method' => 'post', 'data-confirm' => 'Video „'.$media->title.'“ wirklich endgültig löschen?']) ?>
            </div>
        </details>
    </div>
</div>
<style>.pt-form-options{margin:16px 0}.pt-form-options details{border:1px solid #dfe3e7;border-radius:6px;margin-bottom:8px;background:#fff}.pt-form-options summary{cursor:pointer;padding:11px 13px;font-weight:600;list-style:none}.pt-form-options summary::-webkit-details-marker{display:none}.pt-form-options summary:after{content:'\f078';font-family:FontAwesome;float:right;color:#71808e}.pt-form-options details[open] summary:after{content:'\f077'}.pt-option-body{padding:4px 14px 12px;border-top:1px solid #edf0f2}.pt-option-body .form-group:last-child{margin-bottom:0}.pt-danger-zone{margin-top:28px;border:1px solid #edcccc;border-radius:7px;background:#fffafa}.pt-danger-zone summary{padding:12px 14px;cursor:pointer;color:#8d4545;font-weight:600}.pt-danger-content{padding:14px;border-top:1px solid #edcccc}</style>
<?php $this->registerJs("document.querySelectorAll('.pt-form-options details').forEach(function(section){section.addEventListener('toggle',function(){if(!section.open)return;document.querySelectorAll('.pt-form-options details').forEach(function(other){if(other!==section)other.open=false;});});});"); ?>
