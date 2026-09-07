<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->delete('{{%content}}', ['object_model' => 'selfsein\\peertube\\models\\LiveSession']);
        if ($this->db->schema->getTableSchema('{{%peertube_live_session}}', true) !== null) $this->dropTable('{{%peertube_live_session}}');
        if ($this->db->schema->getTableSchema('{{%peertube_live_source}}', true) !== null) $this->dropTable('{{%peertube_live_source}}');
        $this->delete('{{%content}}', ['object_model' => 'selfsein\\peertube\\models\\Media']);
        if ($this->db->schema->getTableSchema('{{%peertube_pending_operation}}', true) !== null) $this->dropTable('{{%peertube_pending_operation}}');
        if ($this->db->schema->getTableSchema('{{%peertube_library_setting}}', true) !== null) $this->dropTable('{{%peertube_library_setting}}');
        $this->dropTable('{{%peertube_media}}');
        if ($this->db->schema->getTableSchema('{{%peertube_folder}}', true) !== null) {
            $this->dropTable('{{%peertube_folder}}');
        }
        return true;
    }
}
