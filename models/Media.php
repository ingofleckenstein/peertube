<?php

namespace community\videolibrary\models;

use humhub\modules\space\models\Space;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\topic\models\Topic;
use humhub\modules\user\models\User;
use humhub\modules\user\helpers\UserHelper;
use community\videolibrary\permissions\UploadMedia;
use community\videolibrary\permissions\ManageMedia;
use community\videolibrary\widgets\WallEntry;
use Yii;
use yii\helpers\Url;

class Media extends ContentActiveRecord
{
    public $wallEntryClass = WallEntry::class;
    protected $moduleId = 'peertube';
    protected $canMove = false;
    protected $createPermission = UploadMedia::class;
    protected $managePermission = ManageMedia::class;

    public static function tableName(): string
    {
        return '{{%peertube_media}}';
    }

    public function rules(): array
    {
        return [
            [['space_id', 'user_id', 'peertube_id', 'folder_id'], 'integer'],
            [['user_id', 'peertube_id', 'peertube_uuid', 'title', 'media_type'], 'required'],
            [['description', 'content_warnings', 'password_encrypted', 'last_sync_error', 'topics'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['peertube_uuid'], 'string', 'max' => 64],
            [['title'], 'string', 'max' => 255],
            [['thumbnail_url'], 'string', 'max' => 1024],
            [['publish_to_stream'], 'boolean'],
            [['media_type'], 'in', 'range' => ['video', 'audio']],
            [['upload_token'], 'string', 'max' => 64],
            [['sync_status'], 'in', 'range' => ['pending', 'synced', 'error']],
        ];
    }

    public function setPublishToStream(bool $publish): void
    {
        $this->publish_to_stream = $publish ? 1 : 0;
        $this->streamChannel = $publish ? 'default' : null;
        // HumHub's optional "Share content" module deliberately offers its
        // share action only for published content with public visibility.
        // Keep the HumHub visibility in sync with the explicit stream choice;
        // media that only lives in the library remains private content.
        $this->content->visibility = $publish
            ? Content::VISIBILITY_PUBLIC
            : Content::VISIBILITY_PRIVATE;
    }

    public function getContentName()
    {
        return 'Video';
    }

    public function getContentDescription()
    {
        return trim($this->title . "\n" . (string) $this->description);
    }

    public function getIcon()
    {
        return 'fa-video-camera';
    }

    public function canBeViewedBy($user = null): bool
    {
        $user = UserHelper::getUserByParam($user);
        if (!$user instanceof User) {
            return false;
        }

        if ($this->isCommunityVisible()
            || $this->content->isNewRecord
            || $this->content->canView($user)) {
            return true;
        }

        // Optional integration: Share content may grant access through a
        // readable share in a Space without making the original profile item
        // public. Keep the module optional and let it own this authorization.
        $shareAccessService = 'humhub\\modules\\sharebetween\\services\\ShareAccessService';
        return class_exists($shareAccessService)
            && $shareAccessService::canViewThroughShare($this, $user);
    }

    public function getUrl($scheme = false)
    {
        $container = $this->content->container;
        $parameters = [
            '/videos/media/view',
            'cguid' => $container->guid,
            'id' => $this->id,
        ];

        $request = Yii::$app->request;
        $start = $request instanceof \yii\web\Request
            ? min(604800, max(0, (int) $request->get('t', 0)))
            : 0;
        if ($start > 0) {
            $parameters['t'] = $start;
        }

        return Url::to($parameters, $scheme);
    }

    public function getPermalink($scheme = false)
    {
        return Url::to(['/content/perma', 'id' => $this->content->id], $scheme);
    }

    public function getSearchAttributes()
    {
        $attributes = [
            'title' => $this->title,
            'description' => $this->description,
            // HumHub already indexes native content tags. Keep the legacy
            // topic representation searchable as well.
            'topics' => implode(' ', $this->getTopicNames()),
        ];
        if ($this->hasAttribute('transcript_text') && (string) $this->transcript_status === 'ready') {
            $attributes['transcript'] = (string) $this->transcript_text;
        }
        return $attributes;
    }

    public function beforeSoftDelete(): bool
    {
        if (Yii::$app->getModule('peertube')->settings->get('deleteRemote', true)) {
            try {
                (new \community\videolibrary\components\PeerTubeClient())->delete($this->peertube_uuid);
            } catch (\Throwable $exception) {
                Yii::error($exception, 'peertube');
                $this->addError('peertube_uuid', 'PeerTube konnte das Medium nicht löschen. Bitte erneut versuchen.');
                return false;
            }
        }
        return parent::beforeSoftDelete();
    }

    public function getSpace()
    {
        return $this->hasOne(Space::class, ['id' => 'space_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getFolder() { return $this->hasOne(Folder::class, ['id' => 'folder_id']); }
    public function getDisplayThumbnailUrl(): string
    {
        return Url::to(['/peertube/media/thumbnail',
            'cguid' => $this->content->container->guid, 'id' => $this->id,
            'v' => (string) $this->updated_at,
        ]);
    }
    public function isCommunityVisible(): bool
    {
        if (!($this->content->container instanceof Space)) {
            return false;
        }
        if ($this->folder_id) {
            return $this->folder && $this->folder->visibility === 'public';
        }
        return LibrarySetting::forSpace((int) $this->space_id)->unfiled_visibility === 'public';
    }
    public function getTopicNames(): array
    {
        if (!$this->content->isNewRecord) {
            $topics = Topic::findByContent($this->content)->all();
            if ($topics) {
                return array_values(array_map(static fn(Topic $topic) => $topic->name, $topics));
            }
        }
        $decoded = json_decode((string) $this->topics, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    public function getTopicIds(): array
    {
        if ($this->content->isNewRecord) {
            return [];
        }

        return array_map('intval', Topic::findByContent($this->content)->select('content_tag.id')->column());
    }

    public function syncTopics($topics): void
    {
        Topic::attach($this->content, $topics ?: []);
        $names = array_map(static fn(Topic $topic) => $topic->name, Topic::findByContent($this->content)->all());
        $this->updateAttributes(['topics' => json_encode(array_values($names), JSON_UNESCAPED_UNICODE)]);
    }
    public static function normalizeTopics($value): array
    {
        $parts = preg_split('/[,;\r\n]+/u', (string) $value);
        $parts = array_map(static fn($v) => trim($v), $parts);
        return array_values(array_unique(array_filter($parts, static fn($v) => $v !== '')));
    }

    public function getContentWarningKeys(): array
    {
        $decoded = json_decode((string) $this->content_warnings, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
