<?php

use yii\db\Migration;

class m260904_000010_user_containers extends Migration
{
    public function safeUp()
    {
        // Space media keep their foreign key. A NULL space_id marks media whose
        // HumHub content container is a user profile instead of a Space.
        $this->alterColumn('{{%peertube_media}}', 'space_id', $this->integer()->null());
    }

    public function safeDown()
    {
        if ((new \yii\db\Query())->from('{{%peertube_media}}')->where(['space_id' => null])->exists()) {
            echo "Profile media exist; space_id cannot safely be changed back to NOT NULL.\n";
            return false;
        }
        $this->alterColumn('{{%peertube_media}}', 'space_id', $this->integer()->notNull());
    }
}
