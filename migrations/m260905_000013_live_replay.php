<?php
use yii\db\Migration;
class m260905_000013_live_replay extends Migration
{
    public function safeUp() { $this->addColumn('{{%peertube_live_session}}','media_id',$this->integer()->null()->after('replay_uuid')); $this->createIndex('ix_peertube_live_session_media','{{%peertube_live_session}}','media_id',true); }
    public function safeDown() { $this->dropColumn('{{%peertube_live_session}}','media_id'); }
}
