<?php
use yii\db\Migration;
class m260903_000007_library_visibility extends Migration
{
    public function safeUp() { $this->createTable('{{%peertube_library_setting}}',['space_id'=>$this->integer()->notNull(),'unfiled_visibility'=>$this->string(16)->notNull()->defaultValue('members')]); $this->addPrimaryKey('pk-peertube-library-setting','{{%peertube_library_setting}}','space_id'); $this->addForeignKey('fk-peertube-library-setting-space','{{%peertube_library_setting}}','space_id','{{%space}}','id','CASCADE'); }
    public function safeDown() { $this->dropTable('{{%peertube_library_setting}}'); }
}
