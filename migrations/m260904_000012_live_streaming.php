<?php

use yii\db\Migration;

class m260904_000012_live_streaming extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%peertube_live_source}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'peertube_id' => $this->integer()->notNull(),
            'peertube_uuid' => $this->string(64)->notNull(),
            'rtmp_url_encrypted' => $this->text()->notNull(),
            'stream_key_encrypted' => $this->text()->notNull(),
            'password_encrypted' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_peertube_live_source_user', '{{%peertube_live_source}}', 'user_id', true);
        $this->createIndex('ux_peertube_live_source_uuid', '{{%peertube_live_source}}', 'peertube_uuid', true);

        $this->createTable('{{%peertube_live_session}}', [
            'id' => $this->primaryKey(),
            'source_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'space_id' => $this->integer()->null(),
            'peertube_uuid' => $this->string(64)->notNull(),
            'replay_uuid' => $this->string(64)->null(),
            'title' => $this->string(120)->notNull(),
            'description' => $this->text()->null(),
            'status' => $this->string(20)->notNull()->defaultValue('preparing'),
            'started_at' => $this->dateTime()->null(),
            'ended_at' => $this->dateTime()->null(),
            'last_error' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ix_peertube_live_session_source', '{{%peertube_live_session}}', 'source_id');
        $this->createIndex('ix_peertube_live_session_user_status', '{{%peertube_live_session}}', ['user_id', 'status']);
        $this->addForeignKey('fk_peertube_live_session_source', '{{%peertube_live_session}}', 'source_id', '{{%peertube_live_source}}', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        $this->delete('{{%content}}', ['object_model' => 'community\\videolibrary\\models\\LiveSession']);
        $this->dropTable('{{%peertube_live_session}}');
        $this->dropTable('{{%peertube_live_source}}');
    }
}
