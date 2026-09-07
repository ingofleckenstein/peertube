<?php

use yii\db\Migration;

class m260902_000001_initial extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%peertube_media}}', [
            'id' => $this->primaryKey(),
            'space_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'peertube_id' => $this->integer()->notNull(),
            'peertube_uuid' => $this->string(64)->notNull()->unique(),
            'title' => $this->string(255)->notNull(),
            'description' => $this->text(),
            'media_type' => $this->string(16)->notNull()->defaultValue('video'),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx-peertube-media-space', '{{%peertube_media}}', 'space_id');
        $this->addForeignKey('fk-peertube-media-space', '{{%peertube_media}}', 'space_id', '{{%space}}', 'id', 'CASCADE');
        $this->addForeignKey('fk-peertube-media-user', '{{%peertube_media}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%peertube_media}}');
    }
}
