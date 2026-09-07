<?php
use yii\db\Migration;
class m260903_000005_topics_folders extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%peertube_folder}}', ['id'=>$this->primaryKey(),'space_id'=>$this->integer()->notNull(),'name'=>$this->string(120)->notNull(),'created_at'=>$this->dateTime()->notNull()]);
        $this->createIndex('uidx-peertube-folder-space-name','{{%peertube_folder}}',['space_id','name'],true);
        $this->addForeignKey('fk-peertube-folder-space','{{%peertube_folder}}','space_id','{{%space}}','id','CASCADE');
        $this->addColumn('{{%peertube_media}}','folder_id',$this->integer()->null());
        $this->addColumn('{{%peertube_media}}','topics',$this->text()->null());
        $this->createIndex('idx-peertube-media-folder','{{%peertube_media}}','folder_id');
        $this->addForeignKey('fk-peertube-media-folder','{{%peertube_media}}','folder_id','{{%peertube_folder}}','id','SET NULL');
    }
    public function safeDown()
    {
        $this->dropForeignKey('fk-peertube-media-folder','{{%peertube_media}}'); $this->dropIndex('idx-peertube-media-folder','{{%peertube_media}}');
        $this->dropColumn('{{%peertube_media}}','topics'); $this->dropColumn('{{%peertube_media}}','folder_id'); $this->dropTable('{{%peertube_folder}}');
    }
}
