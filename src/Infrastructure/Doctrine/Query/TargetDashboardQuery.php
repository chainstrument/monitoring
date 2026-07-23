<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Query;

use App\Application\Target\TargetSummary;
use App\Domain\Probe\ProbeResultStatus;
use App\Domain\Target\TargetType;
use Doctrine\DBAL\Connection;

/**
 * Requête native (pas de DQL/QueryBuilder ORM) : une seule requête SQL avec
 * un LATERAL JOIN pour récupérer, pour chaque Target, son dernier
 * ProbeResult (toutes sondes confondues) et si elle a un incident ouvert —
 * sans exécuter une requête par cible (US-07.1 : "pas de N+1").
 */
final readonly class TargetDashboardQuery
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<TargetSummary>
     */
    public function summaries(?string $tag = null, ?TargetType $type = null): array
    {
        $conditions = [];
        $parameters = [];

        if (null !== $tag && '' !== $tag) {
            $conditions[] = "CONCAT(',', t.tags, ',') LIKE :tagPattern";
            $parameters['tagPattern'] = '%,'.addcslashes($tag, '%_\\').',%';
        }

        if (null !== $type) {
            $conditions[] = 't.type = :type';
            $parameters['type'] = $type->value;
        }

        $where = [] === $conditions ? '' : 'WHERE '.implode(' AND ', $conditions);

        $sql = <<<SQL
            SELECT
                t.id, t.name, t.type, t.identifier, t.tags,
                last_result.status AS last_status,
                last_result.latency_ms,
                last_result.checked_at,
                EXISTS (
                    SELECT 1 FROM incident i
                    INNER JOIN probe p2 ON p2.id = i.probe_id
                    WHERE p2.target_id = t.id AND i.resolved_at IS NULL
                ) AS has_open_incident
            FROM target t
            LEFT JOIN LATERAL (
                SELECT pr.status, pr.latency_ms, pr.checked_at
                FROM probe_result pr
                INNER JOIN probe p ON p.id = pr.probe_id
                WHERE p.target_id = t.id
                ORDER BY pr.checked_at DESC
                LIMIT 1
            ) last_result ON true
            {$where}
            ORDER BY t.created_at DESC
            SQL;

        /** @var list<array{id: int, name: string, type: string, identifier: string, tags: string|null, last_status: string|null, latency_ms: int|null, checked_at: string|null, has_open_incident: bool}> $rows */
        $rows = $this->connection->executeQuery($sql, $parameters)->fetchAllAssociative();

        return array_map($this->toSummary(...), $rows);
    }

    /**
     * @param array{id: int, name: string, type: string, identifier: string, tags: string|null, last_status: string|null, latency_ms: int|null, checked_at: string|null, has_open_incident: bool} $row
     */
    private function toSummary(array $row): TargetSummary
    {
        return new TargetSummary(
            id: $row['id'],
            name: $row['name'],
            type: TargetType::from($row['type']),
            identifier: $row['identifier'],
            tags: null === $row['tags'] || '' === $row['tags'] ? [] : explode(',', $row['tags']),
            lastStatus: null === $row['last_status'] ? null : ProbeResultStatus::from($row['last_status']),
            lastLatencyMs: $row['latency_ms'],
            lastCheckedAt: null === $row['checked_at'] ? null : new \DateTimeImmutable($row['checked_at']),
            hasOpenIncident: (bool) $row['has_open_incident'],
        );
    }
}
