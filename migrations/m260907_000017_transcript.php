<?php

use yii\db\Migration;

class m260907_000017_transcript extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%peertube_media}}', 'transcript_status', $this->string(16)->notNull()->defaultValue('pending')->after('last_sync_error'));
        $this->addColumn('{{%peertube_media}}', 'transcript_language', $this->string(16)->null()->after('transcript_status'));
        $this->addColumn('{{%peertube_media}}', 'transcript_cues', $this->db->driverName === 'mysql' ? 'MEDIUMTEXT NULL' : $this->text()->null());
        $this->addColumn('{{%peertube_media}}', 'transcript_text', $this->db->driverName === 'mysql' ? 'MEDIUMTEXT NULL' : $this->text()->null());
        $this->addColumn('{{%peertube_media}}', 'transcript_requested_at', $this->dateTime()->null());
        $this->addColumn('{{%peertube_media}}', 'transcript_checked_at', $this->dateTime()->null());
        $this->createIndex('idx-peertube-media-transcript-status', '{{%peertube_media}}', 'transcript_status');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-peertube-media-transcript-status', '{{%peertube_media}}');
        foreach (['transcript_checked_at', 'transcript_requested_at', 'transcript_text', 'transcript_cues', 'transcript_language', 'transcript_status'] as $column) {
            $this->dropColumn('{{%peertube_media}}', $column);
        }
    }
}
