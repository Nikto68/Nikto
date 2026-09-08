<?php

declare(strict_types=1);

namespace App\Database\Repositories;

use App\Signal\SignalCandidate;

/**
 * Persists the standard Signal object (spec #13) and its lifecycle events.
 * A `signals` row is the source of truth for status (new/active/closed/
 * cancelled/invalidated, spec #14) and for Signal History (spec #38).
 */
final class SignalRepository extends Repository
{
    /**
     * @return array{id: int, uuid: string}
     */
    public function create(SignalCandidate $candidate, string $mode): array
    {
        $uuid = $this->generateUuid();

        $id = $this->db->insert('signals', [
            'uuid' => $uuid,
            'exchange_id' => $candidate->exchangeId,
            'symbol_id' => $candidate->symbolId,
            'strategy_id' => $candidate->strategyId,
            'direction' => $candidate->direction,
            'timeframe' => $candidate->timeframe,
            'entry' => $candidate->entry,
            'stop_loss' => $candidate->stopLoss,
            'take_profit_1' => $candidate->takeProfits[0] ?? null,
            'take_profit_2' => $candidate->takeProfits[1] ?? null,
            'take_profit_3' => $candidate->takeProfits[2] ?? null,
            'risk_reward' => $candidate->riskReward(),
            'score' => $candidate->score,
            'confidence' => $this->confidenceLabel($candidate->score),
            'reasons' => json_encode($candidate->reasons, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'zones' => json_encode($candidate->zones, JSON_THROW_ON_ERROR),
            'indicators' => json_encode($candidate->indicators, JSON_THROW_ON_ERROR),
            'fingerprint' => $candidate->fingerprint(),
            'mode' => $mode,
            'status' => 'new',
        ]);

        $this->recordEvent((int) $id, 'created', ['score' => $candidate->score]);

        return ['id' => (int) $id, 'uuid' => $uuid];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->hydrate($this->db->selectOne('SELECT * FROM signals WHERE id = :id', ['id' => $id]));
    }

    /**
     * Most recent signal (any status) sharing this fingerprint, used by
     * SignalCooldown for deduplication.
     *
     * @return array<string, mixed>|null
     */
    public function latestByFingerprint(string $fingerprint): ?array
    {
        return $this->hydrate($this->db->selectOne(
            'SELECT * FROM signals WHERE fingerprint = :fp ORDER BY created_at DESC LIMIT 1',
            ['fp' => $fingerprint],
        ));
    }

    public function setStatus(int $id, string $status, ?string $closeReason = null, string $event = 'closed'): void
    {
        $data = ['status' => $status];
        if (in_array($status, ['closed', 'cancelled', 'invalidated'], true)) {
            $data['closed_at'] = date('Y-m-d H:i:s');
            $data['close_reason'] = $closeReason;
        }

        $this->db->update('signals', $data, ['id' => $id]);
        $this->recordEvent($id, $event, ['status' => $status, 'reason' => $closeReason]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordEvent(int $signalId, string $eventType, array $payload = []): void
    {
        $this->db->insert('signal_events', [
            'signal_id' => $signalId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function recordDelivery(int $signalId, int $channelId, ?int $telegramMessageId, string $status, ?string $error = null): void
    {
        $this->db->upsert('signal_channel_deliveries', [
            'signal_id' => $signalId,
            'channel_id' => $channelId,
            'telegram_message_id' => $telegramMessageId,
            'status' => $status,
            'error' => $error,
            'sent_at' => in_array($status, ['sent', 'edited'], true) ? date('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 20, ?string $status = null): array
    {
        $limit = max(1, $limit);
        $sql = 'SELECT s.*, sym.symbol, ex.code AS exchange_code FROM signals s
                JOIN symbols sym ON sym.id = s.symbol_id
                JOIN exchanges ex ON ex.id = s.exchange_id';
        $params = [];

        if ($status !== null) {
            $sql .= ' WHERE s.status = :status';
            $params['status'] = $status;
        }

        $sql .= " ORDER BY s.created_at DESC LIMIT {$limit}";

        return array_map(fn (array $row): array => $this->hydrate($row), $this->db->select($sql, $params));
    }

    public function activeCount(): int
    {
        $row = $this->db->selectOne("SELECT COUNT(*) AS c FROM signals WHERE status IN ('new', 'active')");

        return (int) ($row['c'] ?? 0);
    }

    public function todayCount(): int
    {
        $row = $this->db->selectOne('SELECT COUNT(*) AS c FROM signals WHERE created_at >= CURDATE()');

        return (int) ($row['c'] ?? 0);
    }

    private function confidenceLabel(int $score): string
    {
        return match (true) {
            $score >= 90 => 'very_high',
            $score >= 80 => 'high',
            $score >= 70 => 'medium',
            default => 'low',
        };
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    private function hydrate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        foreach (['reasons', 'zones', 'indicators'] as $jsonField) {
            $row[$jsonField] = json_decode($row[$jsonField], true, 512, JSON_THROW_ON_ERROR) ?? [];
        }

        return $row;
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        );
    }
}
