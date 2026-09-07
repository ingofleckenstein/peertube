<?php

use yii\helpers\Html;
use selfsein\peertube\widgets\Player;
use selfsein\peertube\permissions\ManageMedia;
use yii\widgets\LinkPager;
use selfsein\peertube\components\AccessPolicy;
use humhub\modules\space\models\Space;

$canUpload = AccessPolicy::canUpload($this->context->contentContainer);
$canLive = AccessPolicy::canStartLive($this->context->contentContainer);
$currentUser = Yii::$app->user->isGuest ? null : Yii::$app->user->identity;
$canManageGlobally = $currentUser && ($currentUser->isSystemAdmin() || $currentUser->canManageAllContent());
$isSpace = $this->context->contentContainer instanceof Space;
$canManageFolders = $isSpace && ($canManageGlobally || $this->context->contentContainer->can(ManageMedia::class));
$hasFolderMenu = $canManageFolders;
if (!$hasFolderMenu && $currentUser) foreach ($folders as $folder) if ((int)$folder->created_by === (int)$currentUser->id) $hasFolderMenu = true;
$activeFolder = null;
foreach ($folders as $folder) if ((string)$folder->id === (string)$folderId) $activeFolder = $folder;
?>
<style>
.pt-header-actions{float:right;display:flex;gap:5px;align-items:center}.pt-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}.pt-popup{display:none;position:absolute;z-index:50;width:min(680px,85vw);padding:16px;background:#fff;border:1px solid #dfe3e7;border-radius:7px;box-shadow:0 5px 18px rgba(0,0,0,.18);margin-top:7px}.pt-popup.is-open{display:block}.pt-popup-close{position:absolute;right:8px;top:7px;border:0;background:transparent;font-size:22px;line-height:22px;color:#667}.pt-popup h4{margin:0 30px 14px 0}.pt-popup .form-control{margin-bottom:8px}.pt-folders{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px}.pt-folder{display:flex;align-items:center;gap:11px;width:230px;padding:14px 16px;border:1px solid #dfe3e7;border-radius:8px;background:#fff;color:#263746;box-shadow:0 1px 3px rgba(0,0,0,.06)}.pt-folder:hover{text-decoration:none;background:#f5f8fa}.pt-folder>.fa{width:34px;text-align:center;font-size:30px;color:#e8a317}.pt-folder strong,.pt-folder small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pt-folder-visibility .fa{width:14px;color:#667}.pt-admin-row{padding:16px 0;border-top:1px solid #e8ecef}.pt-admin-row:first-of-type{border-top:0}.pt-admin-grid{display:grid;grid-template-columns:1fr 220px;gap:10px}.pt-admin-actions{position:sticky;bottom:-16px;margin:12px -16px -16px;padding:12px 16px;background:#f7f9fa;border-top:1px solid #dfe3e7}.pt-icon-picker{position:relative}.pt-icon-picker-panel{display:none;grid-template-columns:repeat(7,1fr);gap:6px;margin:7px 0 10px;padding:9px;border:1px solid #dfe3e7;border-radius:6px;background:#f8fafb}.pt-icon-picker-panel.is-open{display:grid}.pt-icon-option input{position:absolute;opacity:0}.pt-icon-option span{display:flex;align-items:center;justify-content:center;height:40px;border:1px solid #dfe3e7;border-radius:5px;background:#fff;cursor:pointer;font-size:19px}.pt-icon-option input:checked+span{color:#fff;background:#337ab7;border-color:#2e6da4}.pt-danger-zone{margin-top:12px;padding:12px;border:1px solid #f0c5c5;border-radius:6px;background:#fff8f8}.pt-danger-zone .btn{margin-top:8px}.pt-section-title{margin:4px 0 14px}.peertube-card{overflow:hidden;border-radius:7px}.peertube-load-button{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;width:100%;height:100%;border:0;background:#111 center/cover no-repeat;cursor:pointer}.peertube-load-button:focus{outline:3px solid #4aa3df;outline-offset:-3px}.peertube-load-play{display:flex;align-items:center;justify-content:center;width:72px;height:72px;margin:auto;border-radius:50%;background:rgba(0,0,0,.78);color:#fff;font-size:28px;padding-left:4px;box-shadow:0 2px 12px rgba(0,0,0,.4)}
.pt-popup{max-height:80vh;overflow-y:auto}
.pt-media-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.peertube-card{height:100%;margin:0;border:1px solid #e2e7eb;border-radius:12px;box-shadow:0 2px 10px rgba(38,55,70,.07);transition:box-shadow .18s ease,transform .18s ease}.peertube-card:hover{box-shadow:0 7px 22px rgba(38,55,70,.13);transform:translateY(-1px)}.peertube-card .panel-body{display:flex;flex-direction:column;gap:8px;padding:16px}.peertube-card h4{margin:0;font-size:18px;line-height:1.35}.pt-card-description{margin:0;color:#52616d;line-height:1.5}.pt-card-topics{min-height:20px}.pt-card-meta{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:auto;padding-top:5px;border-top:1px solid #eef1f3}.pt-card-links{display:flex;align-items:center;gap:4px;margin-left:auto}.pt-card-links .btn{margin:0}.pt-empty-state{padding:34px 18px;text-align:center;background:#f8fafb;border:1px dashed #cfd8df;border-radius:10px}.pt-empty-state .fa{display:block;margin-bottom:10px;font-size:32px;color:#9aa8b3}
@media(max-width:767px){.pt-admin-grid{grid-template-columns:1fr}.pt-icon-picker-panel{grid-template-columns:repeat(5,1fr)}.pt-media-grid{grid-template-columns:1fr}}
</style>
<div class="panel panel-default">
    <div class="panel-heading">
        <strong><i class="fa fa-video-camera"></i> Mediathek</strong>
        <span class="pt-header-actions"><?php if ($canUpload): ?><?= Html::a('<i class="fa fa-upload"></i> Hochladen', $this->context->contentContainer->createUrl('/peertube/media/upload'), ['class' => 'btn btn-primary btn-sm']) ?><?php endif; ?><?php if ($canLive): ?><?= Html::a('<i class="fa fa-circle text-danger"></i> Live gehen', $this->context->contentContainer->createUrl('/peertube/live/start'), ['class' => 'btn btn-default btn-sm']) ?><?php endif; ?><?php if($hasFolderMenu): ?><button type="button" class="btn btn-default btn-sm pt-popup-trigger" data-popup="pt-admin" aria-label="Mediathek verwalten"><i class="fa fa-ellipsis-h"></i></button><?php endif; ?></span>
    </div>
    <div class="panel-body">
        <?php if (!empty($invalidMediaCount)): ?>
            <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i>
                <?php if ($canManageGlobally): ?>
                    <?= (int) $invalidMediaCount ?> Medieneintrag<?= (int) $invalidMediaCount === 1 ? '' : 'e' ?> konnte<?= (int) $invalidMediaCount === 1 ? '' : 'n' ?> wegen einer unvollständigen HumHub-Verknüpfung nicht angezeigt werden. Details stehen im Administrationsprotokoll unter der Kategorie „peertube“.
                <?php else: ?>
                    Einzelne Medien sind momentan nicht verfügbar. Die Administration kann die Ursache im Systemprotokoll prüfen.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="pt-toolbar">
            <div><button type="button" class="btn btn-default btn-sm pt-popup-trigger" data-popup="pt-search"><i class="fa fa-search"></i> Suchen & filtern</button><div id="pt-search" class="pt-popup<?= ($q !== '' || $topic !== '' || $uploaderId !== null) ? ' is-open' : '' ?>"><button type="button" class="pt-popup-close" aria-label="Schließen">&times;</button><h4>Suchen & filtern</h4>
                <?= Html::beginForm($this->context->contentContainer->createUrl('/peertube/media/index'), 'get') ?>
                <?= Html::textInput('q', $q, ['class'=>'form-control','placeholder'=>'Titel, Beschreibung oder Thema suchen']) ?>
                <div class="row"><div class="col-md-6"><?= Html::dropDownList('topic', $topic, $topics, ['class'=>'form-control','prompt'=>'Alle Themen']) ?></div><div class="col-md-6"><?= Html::dropDownList('uploader', $uploaderId, \yii\helpers\ArrayHelper::map($uploaders,'id','displayName'), ['class'=>'form-control','prompt'=>'Alle Uploader']) ?></div></div>
                <?= Html::submitButton('<i class="fa fa-search"></i> Suchen', ['class'=>'btn btn-primary btn-sm']) ?> <?= Html::a('Zurücksetzen', $this->context->contentContainer->createUrl('/peertube/media/index'), ['class'=>'btn btn-link btn-sm']) ?><?= Html::endForm() ?>
            </div></div>
            <?php if ($canManageFolders): ?><div><button type="button" class="btn btn-default btn-sm pt-popup-trigger" data-popup="pt-new-folder"><i class="fa fa-folder"></i> Neuer Ordner</button><div id="pt-new-folder" class="pt-popup"><button type="button" class="pt-popup-close" aria-label="Schließen">&times;</button><h4>Neuen Ordner erstellen</h4><?= Html::beginForm($this->context->contentContainer->createUrl('/peertube/media/create-folder'), 'post') ?><?= Html::textInput('name', '', ['class'=>'form-control','maxlength'=>120,'required'=>true,'placeholder'=>'Name der Videoreihe']) ?> <?= Html::submitButton('Ordner erstellen', ['class'=>'btn btn-primary btn-sm']) ?><?= Html::endForm() ?></div></div><?php endif; ?>
        </div>

        <?php if($hasFolderMenu): ?><div id="pt-admin" class="pt-popup" style="right:18px"><button type="button" class="pt-popup-close" aria-label="Schließen">&times;</button><h4><i class="fa fa-sliders"></i> Mediathek verwalten</h4>
            <?= Html::beginForm($this->context->contentContainer->createUrl('/peertube/media/manage-library'),'post') ?>
            <?php if($canManageFolders): ?><div class="pt-admin-row"><label for="pt-unfiled-visibility">Videos ohne Ordner</label><?= Html::dropDownList('unfiled_visibility',$librarySetting->unfiled_visibility,['members'=>'Nur Raummitglieder','public'=>'Alle Community-Mitglieder'],['id'=>'pt-unfiled-visibility','class'=>'form-control']) ?></div><?php endif; ?>
            <?php foreach($folders as $folder): if(!$canManageFolders && (!$currentUser || (int)$folder->created_by!==(int)$currentUser->id)) continue; $safeIcon=$folder->getSafeIcon(); ?><section class="pt-admin-row">
                <strong><i class="fa fa-<?= Html::encode($safeIcon) ?>"></i> <?= Html::encode($folder->name) ?></strong>
                <div class="pt-admin-grid"><div><label for="pt-folder-name-<?= (int)$folder->id ?>">Name</label><?= Html::textInput('folders['.$folder->id.'][name]',$folder->name,['id'=>'pt-folder-name-'.$folder->id,'class'=>'form-control','required'=>true,'maxlength'=>120]) ?></div><div><label for="pt-folder-visibility-<?= (int)$folder->id ?>">Sichtbarkeit</label><?= Html::dropDownList('folders['.$folder->id.'][visibility]',$folder->visibility,['members'=>'Nur Raummitglieder','public'=>'Alle Community-Mitglieder'],['id'=>'pt-folder-visibility-'.$folder->id,'class'=>'form-control']) ?></div></div>
                <div class="pt-icon-picker"><button type="button" class="btn btn-default btn-sm pt-icon-trigger"><i class="fa fa-<?= Html::encode($safeIcon) ?>"></i> Ordnersymbol wählen</button><div class="pt-icon-picker-panel"><?php foreach(\selfsein\peertube\models\Folder::iconOptions() as $icon=>$iconLabel): ?><label class="pt-icon-option" title="<?= Html::encode($iconLabel) ?>"><input type="radio" name="folders[<?= (int)$folder->id ?>][icon]" value="<?= Html::encode($icon) ?>"<?= $safeIcon===$icon?' checked':'' ?>><span><i class="fa fa-<?= Html::encode($icon) ?>"></i></span></label><?php endforeach; ?></div></div>
                <div class="pt-danger-zone"><strong>Gefahrenzone</strong><div class="text-muted">Der Ordner wird entfernt. Seine Videos bleiben erhalten und erscheinen danach unter „Videos ohne Ordner“.</div><?= Html::a('<i class="fa fa-trash"></i> Ordner löschen',$this->context->contentContainer->createUrl('/peertube/media/delete-folder',['id'=>$folder->id]),['class'=>'btn btn-danger btn-sm','data-method'=>'post','data-confirm'=>'Ordner „'.$folder->name.'“ wirklich löschen? Die enthaltenen Videos bleiben erhalten.']) ?></div>
            </section><?php endforeach; ?>
            <div class="pt-admin-actions"><?= Html::submitButton('<i class="fa fa-check"></i> Änderungen speichern',['class'=>'btn btn-primary']) ?></div><?= Html::endForm() ?>
        </div><?php endif; ?>

        <?php if ($folders): ?><h4 class="pt-section-title"><i class="fa fa-folder-open"></i> Videoreihen</h4><div class="pt-folders">
            <?php foreach ($folders as $folder): ?><div>
                <?= Html::a('<i class="fa fa-'.Html::encode($folder->getSafeIcon()).'"></i><span><strong>'.Html::encode($folder->name).'</strong><small>'.(int)($folderCounts[$folder->id] ?? 0).' Medien</small><small class="pt-folder-visibility"><i class="fa fa-'.($folder->visibility==='public'?'users':'lock').'"></i> '.($folder->visibility==='public'?'Alle Community-Mitglieder':'Nur Raummitglieder').'</small></span>', $this->context->contentContainer->createUrl('/peertube/media/index',['folder'=>$folder->id]), ['class'=>'pt-folder']) ?>
            </div><?php endforeach; ?>
        </div><?php endif; ?>

        <h4 class="pt-section-title"><?= $activeFolder ? '<i class="fa fa-folder-open"></i> '.Html::encode($activeFolder->name) : (($q !== '' || $topic !== '' || $uploaderId !== null) ? '<i class="fa fa-search"></i> Suchergebnisse' : '<i class="fa fa-film"></i> '.($isSpace ? 'Videos ohne Ordner' : 'Videos')) ?></h4>
        <?php if ($activeFolder): ?><?= Html::a('<i class="fa fa-arrow-left"></i> Zur Mediathek', $this->context->contentContainer->createUrl('/peertube/media/index'), ['class'=>'btn btn-link btn-sm','style'=>'margin-bottom:10px']) ?><?php endif; ?>
        <?php if (!$media): ?>
            <div class="pt-empty-state text-muted"><i class="fa fa-film"></i>In diesem Bereich gibt es keine Medien.</div>
        <?php endif; ?>
        <div class="pt-media-grid">
            <?php foreach ($media as $item): ?>
                <div>
                    <div class="panel panel-default peertube-card">
                        <?= Player::widget(['media' => $item, 'clickToLoad' => true]) ?>
                        <div class="panel-body">
                            <h4><?= Html::encode($item->title) ?></h4>
                            <?php if ($item->folder): ?><p><i class="fa fa-folder-open"></i> <?= Html::encode($item->folder->name) ?></p><?php endif; ?>
                            <div class="pt-card-topics"><?php $itemTopicIds = $item->getTopicIds(); foreach ($item->getTopicNames() as $topicIndex => $topicName): $topicKey = $itemTopicIds[$topicIndex] ?? ('legacy:' . $topicName); ?><?= Html::a(Html::encode($topicName), $this->context->contentContainer->createUrl('/peertube/media/index',['topic'=>$topicKey]), ['class'=>'label label-info','style'=>'margin-right:4px']) ?><?php endforeach; ?></div>
                            <?php if ($item->description): ?><p class="pt-card-description"><?= nl2br(Html::encode($item->description)) ?></p><?php endif; ?>
                            <?php if ($item->hasAttribute('sync_status') && $item->sync_status === 'error'): ?><p class="text-danger"><i class="fa fa-exclamation-triangle"></i> Video-Synchronisierung prüfen</p><?php endif; ?>
                            <div class="pt-card-meta"><small class="text-muted"><i class="fa fa-user"></i> <?= Html::encode($item->user ? $item->user->displayName : 'Unbekannt') ?></small><div class="pt-card-links">
                                <?php if ((bool) $item->publish_to_stream && $item->content !== null && !$item->content->isNewRecord): ?><?= Html::a('<i class="fa fa-external-link"></i> Post anzeigen', $item->getPermalink(), ['class' => 'btn btn-link btn-sm']) ?><?php endif; ?>
                                <?php if ((int) $item->user_id === (int) Yii::$app->user->id || $canManageGlobally || ($isSpace && $this->context->contentContainer->can(ManageMedia::class))): ?><?= Html::a('<i class="fa fa-pencil"></i> Bearbeiten', $this->context->contentContainer->createUrl('/peertube/media/edit', ['id' => $item->id]), ['class' => 'btn btn-default btn-sm']) ?><?php endif; ?>
                            </div></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?= LinkPager::widget(['pagination' => $pagination]) ?>
    </div>
</div>
<?php $this->registerJs(<<<'JS'
(function () {
    function closePopups(except) {
        document.querySelectorAll('.pt-popup.is-open').forEach(function (popup) {
            if (popup !== except) popup.classList.remove('is-open');
        });
    }
    document.querySelectorAll('.pt-popup-trigger').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            var popup = document.getElementById(button.getAttribute('data-popup'));
            var open = popup.classList.contains('is-open');
            closePopups();
            if (!open) popup.classList.add('is-open');
        });
    });
    document.querySelectorAll('.pt-popup').forEach(function (popup) {
        popup.addEventListener('click', function (event) { event.stopPropagation(); });
    });
    document.querySelectorAll('.pt-popup-close').forEach(function (button) {
        button.addEventListener('click', function () { button.closest('.pt-popup').classList.remove('is-open'); });
    });
    document.querySelectorAll('.pt-icon-trigger').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = button.nextElementSibling;
            document.querySelectorAll('.pt-icon-picker-panel.is-open').forEach(function (other) {
                if (other !== panel) other.classList.remove('is-open');
            });
            panel.classList.toggle('is-open');
        });
    });
    document.querySelectorAll('.pt-icon-option input').forEach(function (input) {
        input.addEventListener('change', function () {
            var picker = input.closest('.pt-icon-picker');
            var preview = picker.querySelector('.pt-icon-trigger i');
            preview.className = 'fa fa-' + input.value;
            input.closest('.pt-icon-picker-panel').classList.remove('is-open');
        });
    });
    document.addEventListener('click', function () { closePopups(); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closePopups(); });
})();
JS
); ?>
