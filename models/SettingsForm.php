<?php

namespace community\videolibrary\models;

use Yii;
use yii\base\Model;
use community\videolibrary\components\CredentialVault;

class SettingsForm extends Model
{
    public const DEFAULT_EMBED_DOMAINS = 'community.example.org';
    public const DEFAULT_WARNING_CATEGORIES = "sexuality_nudity|Sexualität und Nacktheit\nsexual_violence|Sexualisierte Gewalt und Übergriffe\nviolence|Gewalt und körperliche Übergriffe\nmental_health|Psychische Krisen und belastende Themen\nself_harm_suicide|Selbstverletzung und Suizid\ndiscrimination|Diskriminierung und menschenfeindliche Sprache\nsubstances|Alkohol und andere Substanzen\neating_disorders|Essstörungen und Körperbild\ndeath_grief|Tod und Trauer\nmedical|Medizinische Eingriffe, Verletzungen und Blut\nflashing_lights|Blinkende Lichter und schnelle Bildfolgen";

    public $baseUrl = 'https://video.example.org';
    public $username = '';
    public $password = '';
    public $channelId = '';
    public $deleteRemote = true;
    public $warningCategories = self::DEFAULT_WARNING_CATEGORIES;
    public $embedDomains = self::DEFAULT_EMBED_DOMAINS;
    public $directUploadSecret = '';
    public $allowDataDeletionOnDisable = false;
    /**
     * Zero keeps the legacy behaviour: HumHub does not reserve live slots.
     */
    public $maxConcurrentLiveStreams = 0;
    public $publicLiveUsername = '';
    public $publicLivePassword = '';
    public $publicLiveChannelId = '';
    public $publicLiveWaitingTitle = 'Live-Kanal';
    public $publicLiveWaitingDescription = 'Hier erscheinen öffentliche Livestreams. Termine: https://example.org';
    public $publicCalendarUrl = 'https://example.org';
    private $hasSavedPassword = false;
    private $hasSavedDirectUploadSecret = false;
    private $hasSavedPublicLivePassword = false;

    public function rules(): array
    {
        return [
            [['baseUrl', 'username', 'channelId'], 'required'],
            ['baseUrl', 'url', 'validSchemes' => ['https']],
            [['username', 'password'], 'string', 'max' => 255],
            ['warningCategories', 'required'],
            ['warningCategories', 'validateWarningCategories'],
            ['embedDomains', 'required'],
            ['embedDomains', 'validateEmbedDomains'],
            ['password', 'validatePassword'],
            ['directUploadSecret', 'validateDirectUploadSecret'],
            ['channelId', 'integer', 'min' => 1],
            ['maxConcurrentLiveStreams', 'integer', 'min' => 0, 'max' => 1000],
            [['publicLiveUsername', 'publicLivePassword', 'publicLiveWaitingTitle', 'publicLiveWaitingDescription', 'publicCalendarUrl'], 'string', 'max' => 10000],
            ['publicLiveChannelId', 'integer', 'min' => 1],
            ['publicCalendarUrl', 'url', 'validSchemes' => ['https']],
            ['deleteRemote', 'boolean'],
            ['allowDataDeletionOnDisable', 'boolean'],
        ];
    }

    public function loadSettings(): void
    {
        $settings = Yii::$app->getModule('peertube')->settings;
        $this->baseUrl = $settings->get('baseUrl', $this->baseUrl);
        $this->username = $settings->get('username', '');
        $savedPassword = (string) $settings->get('password', '');
        $this->hasSavedPassword = $savedPassword !== '';
        if ($savedPassword !== '' && !CredentialVault::isEncrypted($savedPassword)) {
            $settings->set('password', CredentialVault::encrypt($savedPassword));
        }
        $this->password = '';
        $this->channelId = $settings->get('channelId', '');
        $this->deleteRemote = (bool) $settings->get('deleteRemote', true);
        $this->allowDataDeletionOnDisable = (bool) $settings->get('allowDataDeletionOnDisable', false);
        $this->maxConcurrentLiveStreams = (int) $settings->get('maxConcurrentLiveStreams', 0);
        $this->warningCategories = $settings->get('warningCategories', self::DEFAULT_WARNING_CATEGORIES);
        $this->embedDomains = $settings->get('embedDomains', self::DEFAULT_EMBED_DOMAINS);
        $savedDirectSecret = (string) $settings->get('directUploadSecret', '');
        $this->hasSavedDirectUploadSecret = $savedDirectSecret !== '';
        if ($savedDirectSecret !== '' && !CredentialVault::isEncrypted($savedDirectSecret)) {
            $settings->set('directUploadSecret', CredentialVault::encrypt($savedDirectSecret));
        }
        $this->directUploadSecret = '';
        $this->publicLiveUsername = (string) $settings->get('publicLiveUsername', '');
        $savedPublicPassword = (string) $settings->get('publicLivePassword', '');
        $this->hasSavedPublicLivePassword = $savedPublicPassword !== '';
        if ($savedPublicPassword !== '' && !CredentialVault::isEncrypted($savedPublicPassword)) {
            $settings->set('publicLivePassword', CredentialVault::encrypt($savedPublicPassword));
        }
        $this->publicLivePassword = '';
        $this->publicLiveChannelId = (string) $settings->get('publicLiveChannelId', '');
        $this->publicLiveWaitingTitle = (string) $settings->get('publicLiveWaitingTitle', $this->publicLiveWaitingTitle);
        $this->publicLiveWaitingDescription = (string) $settings->get('publicLiveWaitingDescription', $this->publicLiveWaitingDescription);
        $this->publicCalendarUrl = (string) $settings->get('publicCalendarUrl', $this->publicCalendarUrl);
    }

    public function saveSettings(): void
    {
        $settings = Yii::$app->getModule('peertube')->settings;
        $settings->set('baseUrl', rtrim($this->baseUrl, '/'));
        $settings->set('username', trim($this->username));
        if ($this->password !== '') {
            $settings->set('password', CredentialVault::encrypt($this->password));
        }
        $settings->set('channelId', (string) $this->channelId);
        $settings->set('deleteRemote', (bool) $this->deleteRemote);
        $settings->set('allowDataDeletionOnDisable', (bool) $this->allowDataDeletionOnDisable);
        $settings->set('maxConcurrentLiveStreams', (int) $this->maxConcurrentLiveStreams);
        $settings->set('warningCategories', trim($this->warningCategories));
        $settings->set('embedDomains', implode("\n", self::normalizeEmbedDomains($this->embedDomains)));
        if ($this->directUploadSecret !== '') {
            $settings->set('directUploadSecret', CredentialVault::encrypt($this->directUploadSecret));
        }
        $settings->set('publicLiveUsername', trim($this->publicLiveUsername));
        if ($this->publicLivePassword !== '') {
            $settings->set('publicLivePassword', CredentialVault::encrypt($this->publicLivePassword));
        }
        $settings->set('publicLiveChannelId', (string) $this->publicLiveChannelId);
        $settings->set('publicLiveWaitingTitle', trim($this->publicLiveWaitingTitle));
        $settings->set('publicLiveWaitingDescription', trim($this->publicLiveWaitingDescription));
        $settings->set('publicCalendarUrl', trim($this->publicCalendarUrl));
    }

    public function validatePassword($attribute): void
    {
        if (!$this->hasSavedPassword && trim((string) $this->$attribute) === '') {
            $this->addError($attribute, 'Bitte hinterlege das Passwort des technischen Videokontos.');
        }
    }

    public function validateDirectUploadSecret($attribute): void
    {
        if (!$this->hasSavedDirectUploadSecret && strlen((string) $this->$attribute) < 32) {
            $this->addError($attribute, 'Bitte einen gemeinsamen Schlüssel mit mindestens 32 Zeichen hinterlegen.');
        } elseif ((string) $this->$attribute !== '' && strlen((string) $this->$attribute) < 32) {
            $this->addError($attribute, 'Der gemeinsame Schlüssel muss mindestens 32 Zeichen lang sein.');
        }
    }

    public static function getDirectUploadSecret(): string
    {
        return CredentialVault::decrypt((string) Yii::$app->getModule('peertube')->settings->get('directUploadSecret', ''));
    }

    public static function publicLiveProfile(): array
    {
        $settings = Yii::$app->getModule('peertube')->settings;
        $profile = [
            'baseUrl' => rtrim((string) $settings->get('baseUrl', ''), '/'),
            'username' => trim((string) $settings->get('publicLiveUsername', '')),
            'password' => CredentialVault::decrypt((string) $settings->get('publicLivePassword', '')),
            'channelId' => (int) $settings->get('publicLiveChannelId', 0),
        ];
        if ($profile['baseUrl'] === '' || $profile['username'] === '' || $profile['password'] === '' || $profile['channelId'] < 1) {
            throw new \RuntimeException('Der öffentliche Live-Kanal ist noch nicht vollständig eingerichtet. Bitte hinterlege im Backend Benutzername, Passwort und Kanal-ID des öffentlichen Videokontos.');
        }
        return $profile;
    }

    public function validateWarningCategories($attribute): void
    {
        foreach (preg_split('/\R/', trim((string) $this->$attribute)) as $line) {
            if (!preg_match('/^[a-z0-9_-]+\|[^|]+$/i', trim($line))) {
                $this->addError($attribute, 'Jede Zeile muss das Format schluessel|Anzeigename verwenden.');
                return;
            }
        }
    }

    public function validateEmbedDomains($attribute): void
    {
        $domains = self::normalizeEmbedDomains($this->$attribute);
        if (!$domains) {
            $this->addError($attribute, 'Bitte mindestens eine Domain eintragen.');
            return;
        }
        foreach ($domains as $domain) {
            if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)) {
                $this->addError($attribute, 'Ungültige Domain: ' . $domain . '. Bitte ohne https:// und ohne Pfad eintragen.');
                return;
            }
        }
    }

    public static function normalizeEmbedDomains($value): array
    {
        $domains = preg_split('/[\s,;]+/', strtolower(trim((string) $value)));
        $domains = array_map(static fn($domain) => trim($domain, " \t\n\r\0\x0B./"), $domains);
        return array_values(array_unique(array_filter($domains)));
    }

    public static function getWarningCategoryOptions(): array
    {
        $raw = Yii::$app->getModule('peertube')->settings->get('warningCategories', self::DEFAULT_WARNING_CATEGORIES);
        $options = [];
        foreach (preg_split('/\R/', trim((string) $raw)) as $line) {
            $parts = explode('|', trim($line), 2);
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $options[$parts[0]] = $parts[1];
            }
        }
        return $options;
    }
}
