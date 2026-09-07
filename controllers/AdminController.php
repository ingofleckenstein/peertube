<?php

namespace selfsein\peertube\controllers;

use humhub\modules\admin\components\Controller;
use selfsein\peertube\components\PeerTubeClient;
use selfsein\peertube\models\SettingsForm;
use Yii;
use yii\filters\VerbFilter;
use selfsein\peertube\models\Media;
use selfsein\peertube\jobs\ProtectExistingJob;
use selfsein\peertube\jobs\SyncEmbedDomainsJob;

class AdminController extends Controller
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => ['test' => ['post'], 'migrate' => ['post'], 'protect-existing' => ['post'], 'sync-embed-domains' => ['post'], 'clear-alert' => ['post']]];
        return $behaviors;
    }

    public function actionIndex()
    {
        $model = new SettingsForm();
        $model->loadSettings();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $model->saveSettings();
            $this->view->success('Einstellungen gespeichert.');
            return $this->redirect(['index']);
        }
        $columns = Yii::$app->db->schema->getTableSchema('{{%peertube_media}}', true)?->columnNames ?? [];
        $databaseCurrent = in_array('password_encrypted', $columns, true)
            && in_array('publish_to_stream', $columns, true)
            && in_array('thumbnail_url', $columns, true)
            && in_array('upload_token', $columns, true)
            && in_array('sync_status', $columns, true)
            && in_array('folder_id', $columns, true)
            && in_array('topics', $columns, true)
            && ($folderSchema = Yii::$app->db->schema->getTableSchema('{{%peertube_folder}}', true)) !== null
            && in_array('created_by', $folderSchema->columnNames, true)
            && in_array('visibility', $folderSchema->columnNames, true)
            && in_array('icon', $folderSchema->columnNames, true);
        $databaseCurrent = $databaseCurrent && Yii::$app->db->schema->getTableSchema('{{%peertube_library_setting}}', true) !== null;
        $databaseCurrent = $databaseCurrent && Yii::$app->db->schema->getTableSchema('{{%peertube_pending_operation}}', true) !== null;
        $liveSchema = Yii::$app->db->schema->getTableSchema('{{%peertube_live_session}}', true);
        $databaseCurrent = $databaseCurrent && $liveSchema !== null
            && in_array('poll_generation', $liveSchema->columnNames, true)
            && in_array('start_datetime', $liveSchema->columnNames, true)
            && in_array('end_datetime', $liveSchema->columnNames, true);
        $unprotectedCount = $databaseCurrent ? Media::find()->where(['or', ['password_encrypted' => null], ['password_encrypted' => '']])->count() : 0;
        $adminAlert = (string) Yii::$app->getModule('peertube')->settings->get('adminAlert', '');
        return $this->render('index', ['model' => $model, 'databaseCurrent' => $databaseCurrent, 'unprotectedCount' => $unprotectedCount, 'adminAlert' => $adminAlert]);
    }

    public function actionTest()
    {
        try {
            $config = (new PeerTubeClient())->testConnection();
            $this->view->success('PeerTube erreichbar: Version ' . ($config['serverVersion'] ?? 'unbekannt'));
        } catch (\Throwable $exception) {
            $this->view->error($exception->getMessage());
        }
        return $this->redirect(['index']);
    }

    public function actionMigrate()
    {
        try {
            $result = Yii::$app->getModule('peertube')->getMigrationService()->migrateUp();
            if ($result === false) {
                throw new \RuntimeException('Die Datenbankmigration ist fehlgeschlagen. Bitte das HumHub-Protokoll prüfen.');
            }
            Yii::$app->db->schema->refresh();
            $this->view->success('Die PeerTube-Moduldatenbank wurde aktualisiert.');
        } catch (\Throwable $exception) {
            $this->view->error($exception->getMessage());
        }
        return $this->redirect(['index']);
    }

    public function actionProtectExisting()
    {
        Yii::$app->queue->push(new ProtectExistingJob());
        $this->view->success('Der Passwortschutz wurde als Hintergrundauftrag eingeplant.');
        return $this->redirect(['index']);
    }

    public function actionSyncEmbedDomains()
    {
        Yii::$app->queue->push(new SyncEmbedDomainsJob());
        $this->view->success('Die Domainfreigabe wurde als Hintergrundauftrag eingeplant.');
        return $this->redirect(['index']);
    }

    public function actionClearAlert()
    {
        Yii::$app->getModule('peertube')->settings->delete('adminAlert');
        $this->view->success('Der Synchronisationshinweis wurde bestätigt.');
        return $this->redirect(['index']);
    }
}
