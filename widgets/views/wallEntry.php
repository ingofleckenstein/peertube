<?php

use community\videolibrary\widgets\Player;
use yii\helpers\Html;

$description = (string) $media->description;
$parts = preg_split('/\[artikelbild\]/iu', $description, 2);
$hasPlacement = count($parts) === 2;
$renderText = static function (string $text): string {
    return trim($text) === '' ? '' : '<p>' . nl2br(Html::encode(trim($text))) . '</p>';
};
$player = Player::widget(['media' => $media]);
?>
<div class="peertube-wall-entry">
    <h4><?= Html::encode($media->title) ?></h4>
    <?php if ($hasPlacement): ?>
        <?= $renderText($parts[0]) ?>
        <?= $player ?>
        <?= $renderText($parts[1]) ?>
    <?php else: ?>
        <?= $renderText($description) ?>
        <?= $player ?>
    <?php endif; ?>
</div>
