<?php

namespace community\videolibrary\models;

use humhub\components\ActiveRecord;

class PublicLiveCategory extends ActiveRecord
{
    public static function tableName(): string { return '{{%peertube_public_live_category}}'; }

    public function rules(): array
    {
        return [
            [['title', 'slug'], 'required'],
            [['title'], 'string', 'max' => 120],
            [['slug'], 'string', 'max' => 120],
            [['peertube_playlist_id'], 'integer'],
            [['peertube_playlist_uuid'], 'string', 'max' => 64],
            [['created_at', 'updated_at'], 'safe'],
            [['slug'], 'unique'],
        ];
    }

    public static function options(): array
    {
        return static::find()->orderBy(['title' => SORT_ASC])->select(['title', 'id'])->indexBy('id')->column();
    }
}
