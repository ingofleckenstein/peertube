<?php

namespace community\videolibrary\controllers;

use community\videolibrary\models\LiveSession;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/** Anonymous, read-only publication feed for public live events. */
class PublicEventsController extends Controller
{
    /** The calendar must remain accessible even if the community disables guest access. */
    public $layout = false;

    public function actionIndex()
    {
        $events = $this->events();
        Yii::$app->response->headers->set('Cache-Control', 'public, max-age=300');
        return $this->render('index', ['events' => $events]);
    }

    public function actionJson(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'public, max-age=300');
        // This feed deliberately contains public event data only and may be
        // embedded on external websites without project-specific allowlists.
        Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        return [
            'generatedAt' => gmdate('c'),
            'timeZone' => 'Europe/Berlin',
            'events' => array_map(static fn(array $event) => $event['json'], $this->events()),
        ];
    }

    private function events(): array
    {
        $rows = LiveSession::find()->where(['is_public' => 1, 'status' => 'scheduled'])
            ->andWhere(['>', 'end_datetime', date('Y-m-d H:i:s')])
            ->orderBy(['start_datetime' => SORT_ASC])->all();
        $baseUrl = rtrim((string) Yii::$app->getModule('peertube')->settings->get('baseUrl', ''), '/');
        $result = [];
        foreach ($rows as $session) {
            $start = new \DateTimeImmutable((string) $session->start_datetime, new \DateTimeZone('Europe/Berlin'));
            $end = new \DateTimeImmutable((string) $session->end_datetime, new \DateTimeZone('Europe/Berlin'));
            $categories = json_decode((string) $session->public_categories, true);
            $categories = is_array($categories) ? array_values(array_filter($categories, 'is_string')) : [];
            $liveUrl = $baseUrl !== '' && $session->peertube_uuid ? $baseUrl . '/w/' . rawurlencode((string) $session->peertube_uuid) : null;
            $result[] = [
                'session' => $session,
                'start' => $start,
                'end' => $end,
                'categories' => $categories,
                'liveUrl' => $liveUrl,
                'json' => [
                    'title' => (string) $session->title,
                    'description' => (string) $session->description,
                    'startsAt' => $start->format(DATE_ATOM),
                    'endsAt' => $end->format(DATE_ATOM),
                    'categories' => $categories,
                    'liveUrl' => $liveUrl,
                ],
            ];
        }
        return $result;
    }
}
