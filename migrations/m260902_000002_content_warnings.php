<?php

use yii\db\Migration;

class m260902_000002_content_warnings extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%peertube_media}}', 'content_warnings', $this->text()->null()->after('description'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%peertube_media}}', 'content_warnings');
    }
}
