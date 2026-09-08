<?php

namespace community\videolibrary\components;

use community\videolibrary\models\LiveSession;
use Yii;
use yii\db\Transaction;

/**
 * Serializes the creation of live sessions while a configured capacity limit
 * is in effect. The one-row table is a portable database lock for PostgreSQL
 * and MySQL; it is deliberately not a process-local mutex.
 */
final class LiveCapacity
{
    public static function reserve(?string $plannedStart = null, ?string $plannedEnd = null, bool $forceLock = false): ?LiveCapacityReservation
    {
        $limit = (int) Yii::$app->getModule('peertube')->settings->get('maxConcurrentLiveStreams', 0);
        if ($limit <= 0 && !$forceLock) {
            return new LiveCapacityReservation();
        }

        $db = Yii::$app->db;
        if ($db->schema->getTableSchema('{{%peertube_live_capacity}}', true) === null) {
            throw new \RuntimeException('Die Datenbankaktualisierung für das Livestream-Limit fehlt. Bitte in der Modulverwaltung ausführen.');
        }

        $transaction = $db->beginTransaction();
        try {
            $lockId = $db->createCommand('SELECT id FROM {{%peertube_live_capacity}} WHERE id = 1 FOR UPDATE')->queryScalar();
            if ((int) $lockId !== 1) {
                throw new \RuntimeException('Die Sperre für das Livestream-Limit ist nicht verfügbar.');
            }

            $reservedQuery = LiveSession::find()->where([
                'or',
                ['status' => ['preparing', 'live']],
                ['and', ['status' => 'ending'], ['ended_at' => null]],
            ]);
            if ($plannedStart && $plannedEnd) {
                $reservedQuery->orWhere(['and', ['status' => 'scheduled'], ['<', 'start_datetime', $plannedEnd], ['>', 'end_datetime', $plannedStart]]);
            }
            $reserved = (int) $reservedQuery->count();
            if ($limit > 0 && $reserved >= $limit) {
                $transaction->rollBack();
                return null;
            }

            return new LiveCapacityReservation($transaction);
        } catch (\Throwable $exception) {
            if ($transaction->getIsActive()) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }
}

final class LiveCapacityReservation
{
    private ?Transaction $transaction;

    public function __construct(?Transaction $transaction = null)
    {
        $this->transaction = $transaction;
    }

    public function commit(): void
    {
        if ($this->transaction?->getIsActive()) {
            $this->transaction->commit();
        }
        $this->transaction = null;
    }

    public function rollback(): void
    {
        if ($this->transaction?->getIsActive()) {
            $this->transaction->rollBack();
        }
        $this->transaction = null;
    }
}
