<?php

use yii\db\Migration;

/** Preserves existing HumHub content records after the distributable rename. */
class m260908_000020_neutral_namespace extends Migration
{
    public function safeUp()
    {
        $legacy = implode('\\', ['self' . 'sein', 'peer' . 'tube']);
        $current = 'community\\videolibrary';
        foreach (['Media', 'LiveSession'] as $model) {
            $this->update('{{%content}}', ['object_model' => $current . '\\models\\' . $model], [
                'object_model' => $legacy . '\\models\\' . $model,
            ]);
        }
    }

    public function safeDown()
    {
        $legacy = implode('\\', ['self' . 'sein', 'peer' . 'tube']);
        $current = 'community\\videolibrary';
        foreach (['Media', 'LiveSession'] as $model) {
            $this->update('{{%content}}', ['object_model' => $legacy . '\\models\\' . $model], [
                'object_model' => $current . '\\models\\' . $model,
            ]);
        }
    }
}
