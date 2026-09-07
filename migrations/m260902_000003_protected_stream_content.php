<?php

use yii\db\Migration;

class m260902_000003_protected_stream_content extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%peertube_media}}', 'password_encrypted', $this->text()->null()->after('content_warnings'));
        $this->addColumn('{{%peertube_media}}', 'publish_to_stream', $this->boolean()->notNull()->defaultValue(true)->after('media_type'));
        $this->addColumn('{{%peertube_media}}', 'thumbnail_url', $this->string(1024)->null()->after('peertube_uuid'));
        $this->createIndex('idx-peertube-media-space-created', '{{%peertube_media}}', ['space_id', 'created_at']);
    }

    public function safeDown()
    {
        $this->dropIndex('idx-peertube-media-space-created', '{{%peertube_media}}');
        $this->dropColumn('{{%peertube_media}}', 'thumbnail_url');
        $this->dropColumn('{{%peertube_media}}', 'publish_to_stream');
        $this->dropColumn('{{%peertube_media}}', 'password_encrypted');
    }
}
