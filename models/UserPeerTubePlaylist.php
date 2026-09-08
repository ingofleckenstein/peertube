<?php

namespace community\videolibrary\models;

use humhub\components\ActiveRecord;

class UserPeerTubePlaylist extends ActiveRecord
{
    public static function tableName(): string { return '{{%peertube_user_playlist}}'; }
    public function rules(): array
    {
        return [
            [['user_id', 'peertube_playlist_id'], 'integer'],
            [['user_id', 'title'], 'required'],
            [['title'], 'string', 'max' => 120],
            [['peertube_playlist_uuid'], 'string', 'max' => 64],
            [['created_at', 'updated_at'], 'safe'],
            [['user_id'], 'unique'],
        ];
    }
}
