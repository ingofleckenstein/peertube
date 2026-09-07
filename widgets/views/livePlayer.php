<?php
use yii\helpers\Html; use yii\helpers\Json;
$base=rtrim(Yii::$app->getModule('peertube')->settings->get('baseUrl',''),'/');
$id='pt-live-'.$session->id.'-'.substr(md5($this->context->id),0,7);
$url=$base.'/videos/embed/'.rawurlencode($session->peertube_uuid).'?'.http_build_query(['api'=>1,'waitPasswordFromEmbedAPI'=>1,'p2p'=>0,'peertubeLink'=>0]);
$passwordUrl=$session->content->container->createUrl('/peertube/live/password',['id'=>$session->id]);
?>
<div class="ratio ratio-16x9 bg-light"><iframe id="<?= Html::encode($id) ?>" src="about:blank" data-src="<?= Html::encode($url) ?>" title="<?= Html::encode($session->title) ?>" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen sandbox="allow-same-origin allow-scripts allow-popups allow-forms"></iframe></div>
<?php $this->registerJs(sprintf("(async function(){var f=document.getElementById(%s);if(!f)return;try{var r=await fetch(%s,{credentials:'same-origin',headers:{Accept:'application/json'}});if(!r.ok)throw 0;var d=await r.json();f.src=f.dataset.src;var p=new window.PeerTubePlayer(f);await p.setVideoPassword(d.password);}catch(e){f.outerHTML='<div class=\"alert alert-warning\">Der Livestream konnte nicht geladen werden.</div>';}})();",Json::htmlEncode($id),Json::htmlEncode($passwordUrl))); ?>
