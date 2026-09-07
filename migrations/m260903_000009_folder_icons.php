<?php

use yii\db\Migration;

class m260903_000009_folder_icons extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%peertube_folder}}', 'icon', $this->string(40)->notNull()->defaultValue('folder'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%peertube_folder}}', 'icon');
    }
}
