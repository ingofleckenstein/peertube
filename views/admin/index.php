<?php

use humhub\widgets\form\ActiveForm;
use yii\helpers\Html;

$this->title = 'Videoverwaltung';
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Html::encode($this->title) ?></div>
    <div class="panel-body">
        <?php if ($adminAlert !== ''): ?>
            <div class="alert alert-danger"><strong>Video-Synchronisierung erfordert Aufmerksamkeit:</strong><br><?= Html::encode($adminAlert) ?><br><?= Html::a('Hinweis bestätigen', ['clear-alert'], ['class' => 'btn btn-danger btn-sm mt-2', 'data-method' => 'post']) ?></div>
        <?php endif; ?>
        <?php if (!$databaseCurrent): ?>
            <div class="alert alert-warning">
                <strong>Datenbankaktualisierung erforderlich.</strong>
                Die neuen Spalten für Passwortschutz und Stream-Veröffentlichung fehlen noch.
                <?= Html::a('Datenbank jetzt aktualisieren', ['migrate'], ['class' => 'btn btn-warning btn-sm ms-2', 'data-method' => 'post', 'data-confirm' => 'Videomodul-Datenbank jetzt aktualisieren?']) ?>
            </div>
        <?php endif; ?>
        <?php if ($databaseCurrent && $unprotectedCount > 0): ?>
            <div class="alert alert-info">
                <?= (int) $unprotectedCount ?> bestehende Medien sind noch nicht mit einem individuellen Passwort geschützt.
                <?= Html::a('Bestehende Videos jetzt schützen', ['protect-existing'], ['class' => 'btn btn-info btn-sm ms-2', 'data-method' => 'post', 'data-confirm' => 'Alle noch ungeschützten Videos jetzt umstellen?']) ?>
            </div>
        <?php endif; ?>
        <?php $form = ActiveForm::begin(); ?>
        <?= $form->field($model, 'baseUrl')->label('Video-Adresse')->hint('Zum Beispiel: https://video.example.org') ?>
        <?= $form->field($model, 'username')->label('Technischer Benutzername des Video-Dienstes') ?>
        <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'new-password'])->label('Passwort')->hint('Leer lassen, um das bereits gespeicherte Passwort beizubehalten.') ?>
        <?= $form->field($model, 'channelId')->label('Video-Kanal-ID') ?>
        <hr>
        <h4>Öffentlicher Live-Kanal</h4>
        <p class="help-block">Dieses getrennte Videokonto wird ausschließlich für öffentlich angekündigte, geplante Events verwendet. Die Zugangsdaten werden verschlüsselt gespeichert und niemals in der öffentlichen Terminliste ausgegeben.</p>
        <?= $form->field($model, 'publicLiveUsername')->label('Benutzername des öffentlichen Live-Kanals') ?>
        <?= $form->field($model, 'publicLivePassword')->passwordInput(['autocomplete' => 'new-password'])->label('Passwort des öffentlichen Live-Kanals')->hint('Leer lassen, um das bereits gespeicherte Passwort beizubehalten.') ?>
        <?= $form->field($model, 'publicLiveChannelId')->label('Kanal-ID des öffentlichen Live-Kanals') ?>
        <?= $form->field($model, 'publicLiveWaitingTitle')->label('Titel des dauerhaften Wartekanals') ?>
        <?= $form->field($model, 'publicLiveWaitingDescription')->textarea(['rows' => 3])->label('Text des dauerhaften Wartekanals')->hint('Hier kann auf den Kalender und das feste Wartebild mit QR-Code verwiesen werden. Das Bild selbst wird später direkt am Livevideo gepflegt.') ?>
        <?= $form->field($model, 'publicCalendarUrl')->label('Öffentliche Kalender-URL') ?>
        <p class="help-block">Öffentliche Terminliste: <code><?= Html::encode(rtrim(Yii::$app->request->hostInfo, '/') . '/videos/public-events') ?></code><br>JSON: <code><?= Html::encode(rtrim(Yii::$app->request->hostInfo, '/') . '/videos/public-events.json') ?></code></p>
        <?= $form->field($model, 'maxConcurrentLiveStreams')->input('number', ['min' => 0, 'max' => 1000])->label('Maximale gleichzeitige Livestreams')->hint('0 bedeutet: kein HumHub-Limit. Geplante Termine dürfen parallel vorbereitet werden, sofern ihre Zeitfenster sich nicht überschneiden. Tatsächlich parallele Sendungen und sich überschneidende geplante Zeitfenster zählen gegen dieses Limit. Das Serverlimit bleibt zusätzlich aktiv.') ?>
        <?= $form->field($model, 'directUploadSecret')->passwordInput(['autocomplete' => 'new-password'])->label('Gemeinsamer Schlüssel für Direktuploads')->hint('Mindestens 32 zufällige Zeichen. Derselbe Wert muss im zugehörigen Video-Dienst-Plugin eingetragen werden. Leer lassen, um den gespeicherten Schlüssel beizubehalten.') ?>
        <?= $form->field($model, 'embedDomains')->textarea(['rows' => 3, 'spellcheck' => 'false'])->label('Erlaubte Embed-Domains')->hint('Eine Domain pro Zeile, ohne https:// und ohne Pfad. Nach Änderungen speichern und anschließend auf alle bestehenden Videos anwenden.') ?>
        <?= $form->field($model, 'deleteRemote')->checkbox()->label('Beim Löschen auch vom Video-Dienst entfernen') ?>
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
        <?= Html::a('Domains auf alle Videos anwenden', ['sync-embed-domains'], ['class' => 'btn btn-info', 'data-method' => 'post', 'data-confirm' => 'Die eingetragenen Embed-Domains jetzt bei allen vorhandenen Videos hinterlegen?']) ?>
        <?= Html::a('Bestehende Videos in Nutzer-Playlisten einsortieren', ['sync-user-playlists'], ['class' => 'btn btn-info', 'data-method' => 'post', 'data-confirm' => 'Alle bestehenden internen Videos den persönlichen Playlisten ihrer hochladenden Personen zuordnen?']) ?>
        <?php ActiveForm::end(); ?>
        <hr>
        <p><strong>Sicherheit:</strong> Neue Uploads werden mit einem individuellen Zufallspasswort geschützt. Das Passwort wird verschlüsselt in HumHub gespeichert und nur nach einer erneuten HumHub-/Space-Berechtigungsprüfung an den eingebetteten Player geliefert.</p>
        <p><strong>Gruppenberechtigung:</strong> Unter <em>Administration → Benutzer → Gruppen → Berechtigungen</em> kann „Videos in die Community hochladen“ pro Benutzergruppe erlaubt oder verweigert werden. Zusätzlich gelten weiterhin die Berechtigungen des jeweiligen Spaces.</p>
    </div>
</div>
