<?php

use yii\db\Migration;

class m260903_000004_management extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%peertube_media}}', 'upload_token', $this->string(64)->null()->after('publish_to_stream'));
        $this->addColumn('{{%peertube_media}}', 'sync_status', $this->string(20)->notNull()->defaultValue('synced')->after('upload_token'));
        $this->addColumn('{{%peertube_media}}', 'last_sync_error', $this->text()->null()->after('sync_status'));
        $this->createIndex('uidx-peertube-media-upload-token', '{{%peertube_media}}', 'upload_token', true);
    }

    public function safeDown()
    {
        $this->dropIndex('uidx-peertube-media-upload-token', '{{%peertube_media}}');
        $this->dropColumn('{{%peertube_media}}', 'last_sync_error');
        $this->dropColumn('{{%peertube_media}}', 'sync_status');
        $this->dropColumn('{{%peertube_media}}', 'upload_token');
    }
}
