<?php

use community\videolibrary\models\Media;
use yii\db\Migration;
use yii\db\Query;

class m260904_000011_stream_content_visibility extends Migration
{
    public function safeUp()
    {
        $streamMediaIds = (new Query())
            ->select('id')
            ->from('{{%peertube_media}}')
            ->where(['publish_to_stream' => 1]);

        $this->update('{{%content}}', ['visibility' => 1], [
            'object_model' => Media::class,
            'object_id' => $streamMediaIds,
        ]);
    }

    public function safeDown()
    {
        echo "Stream visibility cannot be reverted safely.\n";
        return false;
    }
}
