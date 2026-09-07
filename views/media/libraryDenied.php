<?php

use yii\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-body text-center" style="padding:42px 24px">
        <i class="fa fa-lock" style="font-size:42px;color:#8a98a5;margin-bottom:16px"></i>
        <h3>Mediathek nur für Space-Mitglieder</h3>
        <p class="text-muted" style="max-width:620px;margin:10px auto 22px">
            In diesem Space sind derzeit keine Videobereiche für alle Community-Mitglieder freigegeben.
            Als Space-Mitglied kannst du die vollständige Mediathek sehen.
        </p>
        <?= Html::a(
            '<i class="fa fa-arrow-left"></i> Zurück zum Space',
            $this->context->contentContainer->getUrl(),
            ['class' => 'btn btn-primary']
        ) ?>
    </div>
</div>
