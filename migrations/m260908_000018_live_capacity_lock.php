<?php

use yii\db\Migration;

class m260908_000018_live_capacity_lock extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%peertube_live_capacity}}', [
            'id' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk_peertube_live_capacity', '{{%peertube_live_capacity}}', 'id');
        $this->insert('{{%peertube_live_capacity}}', ['id' => 1]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%peertube_live_capacity}}');
    }
}
