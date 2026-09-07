<?php
namespace selfsein\peertube\models;
use humhub\components\ActiveRecord;
use selfsein\peertube\components\VideoPasswordVault;
class LiveSource extends ActiveRecord
{
    public static function tableName(): string { return '{{%peertube_live_source}}'; }
    public function rules(): array { return [[['user_id','peertube_id'],'integer'],[['user_id','peertube_uuid','rtmp_url_encrypted','stream_key_encrypted','password_encrypted'],'required'],[['rtmp_url_encrypted','stream_key_encrypted','password_encrypted'],'string'],[['peertube_uuid'],'string','max'=>64],[['created_at','updated_at'],'safe'],[['user_id'],'unique']]; }
    public function getRtmpUrl(): string { return VideoPasswordVault::decrypt((string)$this->rtmp_url_encrypted); }
    public function getStreamKey(): string { return VideoPasswordVault::decrypt((string)$this->stream_key_encrypted); }
    public function getVideoPassword(): string { return VideoPasswordVault::decrypt((string)$this->password_encrypted); }
}
