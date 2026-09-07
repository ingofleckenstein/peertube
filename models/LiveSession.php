<?php
namespace selfsein\peertube\models;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\user\helpers\UserHelper;
use humhub\modules\user\models\User;
use selfsein\peertube\widgets\LiveWallEntry;
use selfsein\peertube\permissions\StartLive;
use yii\helpers\Url;
class LiveSession extends ContentActiveRecord
{
    public $wallEntryClass = LiveWallEntry::class;
    protected $moduleId = 'peertube';
    protected $streamChannel = 'default';
    protected $canMove = false;
    protected $createPermission = StartLive::class;
    public static function tableName(): string { return '{{%peertube_live_session}}'; }
    public function rules(): array { return [[['source_id','user_id','space_id','media_id','poll_generation'],'integer'],[['source_id','user_id','peertube_uuid','title','status'],'required'],[['description','last_error'],'string'],[['peertube_uuid','replay_uuid'],'string','max'=>64],[['title'],'string','max'=>120],[['status'],'in','range'=>['scheduled','preparing','live','ending','completed','failed','cancelled']],[['created_at','updated_at','started_at','ended_at','start_datetime','end_datetime'],'safe']]; }
    public function getContentName(){ return 'Livestream'; }
    public function getContentDescription(){ return trim($this->title."\n".(string)$this->description); }
    public function getIcon(){ return 'fa-rss'; }
    public function getUrl($scheme=false){ return Url::to(['/peertube/live/view','cguid'=>$this->content->container->guid,'id'=>$this->id],$scheme); }
    public function canBeViewedBy($user=null): bool
    {
        $user=UserHelper::getUserByParam($user); if(!$user instanceof User) return false;
        if($this->content->canView($user)) return true;
        $service='humhub\\modules\\sharebetween\\services\\ShareAccessService';
        return class_exists($service) && $service::canViewThroughShare($this,$user);
    }
    public function beforeSave($insert): bool { if($insert) $this->content->visibility=Content::VISIBILITY_PUBLIC; return parent::beforeSave($insert); }
    public function getSource(){ return $this->hasOne(LiveSource::class,['id'=>'source_id']); }
}
