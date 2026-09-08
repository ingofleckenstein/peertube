<?php

namespace community\videolibrary\models;

use yii\db\ActiveRecord;

class PendingOperation extends ActiveRecord
{
    public static function tableName(): string { return '{{%peertube_pending_operation}}'; }
    public function rules(): array
    {
        return [
            [['operation_token', 'operation_type', 'status'], 'required'],
            [['space_id', 'user_id'], 'integer'],
            [['payload', 'last_error'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['operation_token'], 'string', 'max' => 64],
            [['operation_type', 'status'], 'string', 'max' => 32],
        ];
    }
}
