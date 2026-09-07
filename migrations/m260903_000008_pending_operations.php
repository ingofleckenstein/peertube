<?php

use yii\db\Migration;

class m260903_000008_pending_operations extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%peertube_pending_operation}}', [
            'id' => $this->primaryKey(),
            'operation_token' => $this->string(64)->notNull()->unique(),
            'operation_type' => $this->string(32)->notNull(),
            'space_id' => $this->integer(),
            'user_id' => $this->integer(),
            'status' => $this->string(32)->notNull(),
            'payload' => $this->text(),
            'last_error' => $this->text(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%peertube_pending_operation}}');
    }
}
