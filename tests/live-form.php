<?php
$vendor=getenv('TEST_VENDOR');
if (!$vendor) { fwrite(STDERR,"Set TEST_VENDOR to a HumHub vendor directory.\n"); exit(2); }
require $vendor.'/autoload.php';
require $vendor.'/yiisoft/yii2/Yii.php';
new \yii\console\Application(['id'=>'peertube-form-test','basePath'=>__DIR__,'vendorPath'=>$vendor,'runtimePath'=>sys_get_temp_dir().'/peertube-form-test']);
require dirname(__DIR__).'/models/LiveForm.php';
$count=0;
foreach ([''=>true,'  '=>true,'d'=>false,'ab'=>false,'abc'=>true,'ÄÖÜ'=>true,str_repeat('x',10000)=>true,str_repeat('x',10001)=>false] as $value=>$valid) {
 $form=new \selfsein\peertube\models\LiveForm();$form->description=$value;
 if ($form->validate(['description']) !== $valid) throw new \RuntimeException('Unexpected description validation');
 ++$count;
}
echo "$count real Yii form checks passed\n";
