<?php
use yii\db\Migration;
class m260903_000006_folder_management extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%peertube_folder}}','created_by',$this->integer()->null());
        $this->addColumn('{{%peertube_folder}}','visibility',$this->string(16)->notNull()->defaultValue('members'));
        $this->addForeignKey('fk-peertube-folder-creator','{{%peertube_folder}}','created_by','{{%user}}','id','SET NULL');
    }
    public function safeDown()
    {
        $this->dropForeignKey('fk-peertube-folder-creator','{{%peertube_folder}}');
        $this->dropColumn('{{%peertube_folder}}','visibility');
        $this->dropColumn('{{%peertube_folder}}','created_by');
    }
}
