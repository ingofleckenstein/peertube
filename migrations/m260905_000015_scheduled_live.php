<?php

use yii\db\Migration;

class m260905_000015_scheduled_live extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%peertube_live_session}}', 'start_datetime', $this->dateTime()->null()->after('status'));
        $this->addColumn('{{%peertube_live_session}}', 'end_datetime', $this->dateTime()->null()->after('start_datetime'));
        $this->createIndex('ix_peertube_live_session_schedule', '{{%peertube_live_session}}', ['start_datetime', 'end_datetime']);
    }

    public function safeDown()
    {
        $this->dropIndex('ix_peertube_live_session_schedule', '{{%peertube_live_session}}');
        $this->dropColumn('{{%peertube_live_session}}', 'end_datetime');
        $this->dropColumn('{{%peertube_live_session}}', 'start_datetime');
    }
}
