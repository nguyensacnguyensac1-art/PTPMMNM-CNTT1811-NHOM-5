<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

final class HistoryRepository extends Repository
{
    /** @param array<string,mixed> $changes */
    public function add(int $projectId, int $issueId, ?int $actorId, string $action, array $changes = []): void
    {
        $this->run(
            'INSERT INTO issue_histories (project_id, issue_id, actor_id, action, changes, created_at) VALUES (?,?,?,?,?,?)',
            [$projectId, $issueId, $actorId, $action, $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null, gmdate('Y-m-d H:i:s')]
        );
    }

    /** @param array<string,mixed> $details */
    public function audit(?int $projectId, ?int $userId, string $action, string $entityType, ?int $entityId, array $details = [], string $ip = '', ?string $at = null): void
    {
        $this->run(
            'INSERT INTO audit_logs (project_id, user_id, action, entity_type, entity_id, ip_address, details, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$projectId, $userId, $action, $entityType, $entityId, $ip ?: null, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, $at ?? gmdate('Y-m-d H:i:s')]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forIssue(int $issueId): array
    {
        return $this->all(
            'SELECT h.id, h.action, h.changes, h.created_at, u.full_name AS actor_name
               FROM issue_histories h LEFT JOIN users u ON u.id = h.actor_id WHERE h.issue_id = ? ORDER BY h.id DESC',
            [$issueId]
        );
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int} */
    public function forProject(int $projectId, int $page, int $perPage): array
    {
        $total = (int) $this->scalar('SELECT COUNT(*) FROM issue_histories WHERE project_id = ?', [$projectId]);
        $perPage = max(1, min(100, $perPage));
        $rows = $this->all(
            'SELECT h.id, h.issue_id, h.action, h.changes, h.created_at, u.full_name AS actor_name, i.code AS issue_code, i.title AS issue_title
               FROM issue_histories h JOIN issues i ON i.id = h.issue_id LEFT JOIN users u ON u.id = h.actor_id
              WHERE h.project_id = ? ORDER BY h.id DESC LIMIT ' . $perPage . ' OFFSET ' . ((max(1, $page) - 1) * $perPage),
            [$projectId]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @param int[] $projectIds @return array<int,array<string,mixed>> */
    public function recent(array $projectIds, int $limit = 12): array
    {
        if (!$projectIds) {
            return [];
        }
        return $this->all(
            'SELECT h.id, h.action, h.changes, h.created_at, h.issue_id, u.full_name AS actor_name, i.code AS issue_code, i.title AS issue_title, p.code AS project_code
               FROM issue_histories h JOIN issues i ON i.id = h.issue_id JOIN projects p ON p.id = h.project_id LEFT JOIN users u ON u.id = h.actor_id
              WHERE h.project_id IN (' . self::in($projectIds) . ') ORDER BY h.id DESC LIMIT ' . (int) $limit,
            $projectIds
        );
    }
}
