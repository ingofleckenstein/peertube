<?php

use humhub\widgets\form\ActiveForm;
use yii\helpers\Html;

$this->title = 'PeerTube Mediathek';
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Html::encode($this->title) ?></div>
    <div class="panel-body">
        <?php if ($adminAlert !== ''): ?>
            <div class="alert alert-danger"><strong>PeerTube-Synchronisierung erfordert Aufmerksamkeit:</strong><br><?= Html::encode($adminAlert) ?><br><?= Html::a('Hinweis bestätigen', ['clear-alert'], ['class' => 'btn btn-danger btn-sm mt-2', 'data-method' => 'post']) ?></div>
        <?php endif; ?>
        <?php if (!$databaseCurrent): ?>
            <div class="alert alert-warning">
                <strong>Datenbankaktualisierung erforderlich.</strong>
                Die neuen Spalten für Passwortschutz und Stream-Veröffentlichung fehlen noch.
                <?= Html::a('Datenbank jetzt aktualisieren', ['migrate'], ['class' => 'btn btn-warning btn-sm ms-2', 'data-method' => 'post', 'data-confirm' => 'PeerTube-Moduldatenbank jetzt aktualisieren?']) ?>
            </div>
        <?php endif; ?>
        <?php if ($databaseCurrent && $unprotectedCount > 0): ?>
            <div class="alert alert-info">
                <?= (int) $unprotectedCount ?> bestehende Medien sind noch nicht mit einem individuellen Passwort geschützt.
                <?= Html::a('Bestehende Videos jetzt schützen', ['protect-existing'], ['class' => 'btn btn-info btn-sm ms-2', 'data-method' => 'post', 'data-confirm' => 'Alle noch ungeschützten PeerTube-Videos jetzt umstellen?']) ?>
            </div>
        <?php endif; ?>
        <?php $form = ActiveForm::begin(); ?>
        <?= $form->field($model, 'baseUrl')->label('PeerTube-URL') ?>
        <?= $form->field($model, 'username')->label('Technischer Benutzername') ?>
        <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'new-password'])->label('Passwort')->hint('Leer lassen, um das bereits gespeicherte Passwort beizubehalten.') ?>
        <?= $form->field($model, 'channelId')->label('PeerTube-Kanal-ID') ?>
        <?= $form->field($model, 'directUploadSecret')->passwordInput(['autocomplete' => 'new-password'])->label('Gemeinsamer Schlüssel für Direktuploads')->hint('Mindestens 32 zufällige Zeichen. Derselbe Wert muss in den Einstellungen des PeerTube-Plugins „HumHub Permalinks“ eingetragen werden. Leer lassen, um den gespeicherten Schlüssel beizubehalten.') ?>
        <?= $form->field($model, 'embedDomains')->textarea(['rows' => 3, 'spellcheck' => 'false'])->label('Erlaubte Embed-Domains')->hint('Eine Domain pro Zeile, ohne https:// und ohne Pfad. Nach Änderungen speichern und anschließend auf alle bestehenden Videos anwenden.') ?>
        <?= $form->field($model, 'deleteRemote')->checkbox()->label('Beim Löschen auch aus PeerTube entfernen') ?>
        <?= $form->field($model, 'warningCategories')->textarea(['rows' => 9, 'spellcheck' => 'false'])->label('Content-Warning-Kategorien')->hint('Eine Kategorie pro Zeile im Format schluessel|Anzeigename. Schlüssel nach der ersten Verwendung nicht mehr ändern.') ?>
        <hr>
        <div class="panel panel-danger">
            <div class="panel-heading"><strong><i class="fa fa-exclamation-triangle"></i> Danger Zone</strong></div>
            <div class="panel-body">
                <p><strong>Normalerweise bleiben beim Deaktivieren des Moduls alle Mediathek-Daten, Zuordnungen und Einstellungen erhalten.</strong></p>
                <?= $form->field($model, 'allowDataDeletionOnDisable')->checkbox()->label('Ich möchte beim nächsten Deaktivieren/Entfernen alle Datenbanktabellen und Moduldaten unwiderruflich löschen.') ?>
                <p class="text-danger">Nur unmittelbar vor einer wirklich gewünschten vollständigen Deinstallation aktivieren und speichern.</p>
            </div>
        </div>
        <?= Html::submitButton('Speichern', ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Verbindung testen', ['test'], ['class' => 'btn btn-light', 'data-method' => 'post']) ?>
        <?= Html::a('Domains auf alle Videos anwenden', ['sync-embed-domains'], ['class' => 'btn btn-info', 'data-method' => 'post', 'data-confirm' => 'Die eingetragenen Embed-Domains jetzt bei allen vorhandenen PeerTube-Videos hinterlegen?']) ?>
        <?php ActiveForm::end(); ?>
        <hr>
        <p><strong>Sicherheit:</strong> Neue Uploads werden mit einem individuellen Zufallspasswort geschützt. Das Passwort wird verschlüsselt in HumHub gespeichert und nur nach einer erneuten HumHub-/Space-Berechtigungsprüfung an den eingebetteten Player geliefert.</p>
        <p><strong>Gruppenberechtigung:</strong> Unter <em>Administration → Benutzer → Gruppen → Berechtigungen</em> kann „Videos in die Community hochladen“ pro Benutzergruppe erlaubt oder verweigert werden. Zusätzlich gelten weiterhin die Berechtigungen des jeweiligen Spaces.</p>
    </div>
</div>
