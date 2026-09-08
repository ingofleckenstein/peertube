<?php

use yii\helpers\Html;
?>
<div class="container" style="max-width: 1000px; margin: 30px auto;">
    <h1>Öffentliche Live-Termine</h1>
    <?php if (!$events): ?>
        <p>Zurzeit sind keine öffentlichen Livestream-Termine geplant.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead><tr><th>Datum</th><th>Uhrzeit</th><th>Titel</th><th>Kategorien</th><th>Livestream</th></tr></thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td><?= Html::encode($event['start']->format('d.m.Y')) ?></td>
                    <td><?= Html::encode($event['start']->format('H:i')) ?>–<?= Html::encode($event['end']->format('H:i')) ?> Uhr</td>
                    <td><strong><?= Html::encode($event['session']->title) ?></strong><?php if ($event['session']->description): ?><br><small><?= nl2br(Html::encode($event['session']->description)) ?></small><?php endif; ?></td>
                    <td><?= Html::encode(implode(', ', $event['categories'])) ?></td>
                    <td><?= $event['liveUrl'] ? Html::a('Zum Live-Kanal', $event['liveUrl'], ['target' => '_blank', 'rel' => 'noopener noreferrer']) : '–' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
