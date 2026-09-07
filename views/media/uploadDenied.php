<?php

use yii\helpers\Html;

$isProfile = $this->context->contentContainer instanceof \humhub\modules\user\models\User;
?>
<div class="panel panel-default">
    <div class="panel-body text-center" style="padding:42px 24px">
        <i class="fa fa-lock" style="font-size:42px;color:#8a98a5;margin-bottom:16px"></i>
        <h3>Video-Upload nicht freigeschaltet</h3>
        <p class="text-muted" style="max-width:620px;margin:10px auto 22px">
            <?= $isProfile
                ? 'Deine Benutzergruppe darf derzeit keine Videos in die Community hochladen.'
                : 'Deine Benutzergruppe oder deine Rolle in diesem Space darf derzeit keine Videos veröffentlichen.' ?>
            Wenn du glaubst, dass dies nicht beabsichtigt ist, wende dich bitte an die Community-Administration.
        </p>
        <?= Html::a(
            '<i class="fa fa-arrow-left"></i> Zurück',
            $this->context->contentContainer->getUrl(),
            ['class' => 'btn btn-primary']
        ) ?>
    </div>
</div>
