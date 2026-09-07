<?php use yii\helpers\Html; use selfsein\peertube\widgets\LivePlayer;
$image=$session->fileManager->find()->andWhere(['like','mime_type','image/%',false])->orderBy(['id'=>SORT_DESC])->one();
$imageUrl=$image ? $image->getUrl([], false) : null;
$showPlayer=in_array($session->status,['live','ending'],true);
$description=(string)$session->description;
$parts=preg_split('/\[artikelbild\]/iu',$description,2);
$hasPlacement=count($parts)===2;
$renderText=static function(string $text): string { return trim($text)==='' ? '' : '<p style="margin:10px 0 0">'.nl2br(Html::encode(trim($text))).'</p>'; };
$visual=static function() use ($session,$imageUrl,$showPlayer): string {
    if($showPlayer) return LivePlayer::widget(['session'=>$session]);
    ob_start(); ?>
    <div style="position:relative;min-height:300px;background:<?= $imageUrl ? "linear-gradient(180deg,rgba(0,0,0,.08),rgba(0,0,0,.78)),url('".Html::encode($imageUrl)."') center/cover no-repeat" : 'linear-gradient(135deg,#29323c,#485563)' ?>;color:#fff;display:flex;align-items:flex-end;padding:28px">
      <div style="position:relative;z-index:1;max-width:760px">
        <span class="label <?= $session->status === 'scheduled' ? 'label-info' : 'label-danger' ?>" style="font-size:12px;padding:7px 10px"><i class="fa <?= $session->status === 'scheduled' ? 'fa-calendar' : 'fa-circle' ?>"></i> <?= $session->status === 'scheduled' ? 'LIVESTREAM GEPLANT' : 'STARTET GLEICH' ?></span>
        <h2 style="color:#fff;margin:14px 0 8px;text-shadow:0 1px 3px #000"><?= Html::encode($session->title) ?></h2>
        <?php if ($session->start_datetime): ?><div style="font-size:16px"><i class="fa fa-clock-o"></i> <?= Yii::$app->formatter->asDatetime($session->start_datetime) ?></div><?php endif; ?>
      </div>
    </div>
    <?php return (string)ob_get_clean();
};
?>
<div class="peertube-live-entry" style="border-radius:10px;overflow:hidden;background:#fff">
  <div style="padding:18px 20px">
    <?php if ($showPlayer): ?><span class="label label-danger"><i class="fa fa-circle"></i> <?= $session->status === 'live' ? 'LIVE' : 'WIRD VERARBEITET' ?></span><?php endif; ?>
    <?php if($hasPlacement): ?>
      <?= $renderText($parts[0]) ?>
      <div style="margin:14px -20px"><?= $visual() ?></div>
      <?= $renderText($parts[1]) ?>
    <?php else: ?>
      <div style="margin:-18px -20px 14px"><?= $visual() ?></div>
      <?= $renderText($description) ?>
    <?php endif; ?>
  </div>
</div>
