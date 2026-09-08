<?php
namespace community\videolibrary\models;
use yii\db\ActiveRecord;
class Folder extends ActiveRecord
{
    public static function tableName(): string { return '{{%peertube_folder}}'; }
    public function rules(): array
    {
        $rules = [
            [['space_id', 'name'], 'required'],
            [['space_id', 'created_by'], 'integer'],
            ['name', 'string', 'max' => 120],
            ['visibility', 'in', 'range' => ['members', 'public']],
            ['name', 'unique', 'targetAttribute' => ['space_id', 'name']],
        ];
        if ($this->hasAttribute('icon')) {
            $rules[] = ['icon', 'in', 'range' => array_keys(self::iconOptions())];
        }
        return $rules;
    }

    public static function iconOptions(): array
    {
        return [
            'folder' => 'Ordner', 'film' => 'Film', 'video-camera' => 'Videokamera',
            'play-circle' => 'Wiedergabe', 'music' => 'Musik', 'graduation-cap' => 'Lernen',
            'book' => 'Buch', 'heart' => 'Herz', 'users' => 'Gruppe', 'calendar' => 'Kalender',
            'star' => 'Stern', 'lightbulb-o' => 'Idee', 'archive' => 'Archiv',
        ];
    }

    public function getSafeIcon(): string
    {
        if (!$this->hasAttribute('icon')) {
            return 'folder';
        }
        return isset(self::iconOptions()[$this->icon]) ? $this->icon : 'folder';
    }
}
