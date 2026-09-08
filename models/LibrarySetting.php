<?php
namespace community\videolibrary\models;
use Yii;
use yii\db\ActiveRecord;
use yii\base\DynamicModel;
class LibrarySetting extends ActiveRecord
{
    public static function tableName(): string { return '{{%peertube_library_setting}}'; }
    public static function forSpace(int $spaceId)
    {
        if (Yii::$app->db->schema->getTableSchema(static::tableName(), true) === null) {
            return new DynamicModel(['space_id'=>$spaceId, 'unfiled_visibility'=>'members']);
        }
        return static::findOne($spaceId) ?: new static(['space_id'=>$spaceId,'unfiled_visibility'=>'members']);
    }
    public function rules(): array { return [['space_id','integer'],['unfiled_visibility','in','range'=>['members','public']]]; }
}
