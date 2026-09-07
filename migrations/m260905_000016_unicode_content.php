<?php

use yii\db\Migration;

class m260905_000016_unicode_content extends Migration
{
    public function safeUp()
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }

        foreach (['{{%peertube_media}}', '{{%peertube_live_session}}'] as $table) {
            $rawName = $this->db->schema->getRawTableName($table);
            if ($this->db->schema->getTableSchema($rawName, true) !== null) {
                $quotedName = $this->db->quoteTableName($rawName);
                $this->execute("ALTER TABLE {$quotedName} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
    }

    public function safeDown()
    {
        echo "Unicode-Unterstützung wird nicht zurückgesetzt.\n";
        return false;
    }
}
