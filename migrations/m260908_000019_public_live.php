<?php

use yii\db\Migration;

class m260908_000019_public_live extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%peertube_live_session}}', 'is_public', $this->boolean()->notNull()->defaultValue(false)->after('status'));
        $this->addColumn('{{%peertube_live_session}}', 'public_categories', $this->text()->null()->after('description'));
        $this->createIndex('ix_peertube_live_session_public_schedule', '{{%peertube_live_session}}', ['is_public', 'start_datetime', 'end_datetime']);
        $this->createTable('{{%peertube_public_live_category}}', [
            'id' => $this->primaryKey(), 'title' => $this->string(120)->notNull(), 'slug' => $this->string(120)->notNull(),
            'peertube_playlist_id' => $this->integer()->null(), 'peertube_playlist_uuid' => $this->string(64)->null(),
            'created_at' => $this->dateTime()->notNull(), 'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_peertube_public_live_category_slug', '{{%peertube_public_live_category}}', 'slug', true);
        $this->createTable('{{%peertube_public_live_session_category}}', [
            'session_id' => $this->integer()->notNull(), 'category_id' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_peertube_public_live_session_category', '{{%peertube_public_live_session_category}}', ['session_id', 'category_id']);
        $this->addForeignKey('fk_peertube_public_live_session_category_session', '{{%peertube_public_live_session_category}}', 'session_id', '{{%peertube_live_session}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_peertube_public_live_session_category_category', '{{%peertube_public_live_session_category}}', 'category_id', '{{%peertube_public_live_category}}', 'id', 'CASCADE');
        $this->createTable('{{%peertube_user_playlist}}', [
            'id' => $this->primaryKey(), 'user_id' => $this->integer()->notNull(), 'title' => $this->string(120)->notNull(),
            'peertube_playlist_id' => $this->integer()->null(), 'peertube_playlist_uuid' => $this->string(64)->null(),
            'created_at' => $this->dateTime()->notNull(), 'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_peertube_user_playlist_user', '{{%peertube_user_playlist}}', 'user_id', true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%peertube_user_playlist}}');
        $this->dropTable('{{%peertube_public_live_session_category}}');
        $this->dropTable('{{%peertube_public_live_category}}');
        $this->dropIndex('ix_peertube_live_session_public_schedule', '{{%peertube_live_session}}');
        $this->dropColumn('{{%peertube_live_session}}', 'public_categories');
        $this->dropColumn('{{%peertube_live_session}}', 'is_public');
    }
}
