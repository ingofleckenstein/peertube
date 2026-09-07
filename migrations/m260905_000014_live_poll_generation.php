<?php
use yii\db\Migration;
class m260905_000014_live_poll_generation extends Migration
{
    public function safeUp() { $this->addColumn('{{%peertube_live_session}}','poll_generation',$this->integer()->notNull()->defaultValue(0)->after('media_id')); }
    public function safeDown() { $this->dropColumn('{{%peertube_live_session}}','poll_generation'); }
}
