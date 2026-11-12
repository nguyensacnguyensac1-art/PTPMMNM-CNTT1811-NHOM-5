<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

final class ReleaseRepository extends Repository
{
    private const BASE = 'SELECT r.*, p.code AS project_code, p.name AS project_name, o.full_name AS owner_name, rb.full_name AS released_by_name
          FROM releases r JOIN projects p ON p.id = r.project_id
          LEFT JOIN users o ON o.id = r.owner_id LEFT JOIN users rb ON rb.id = r.released_by';

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->one(self::BASE . ' WHERE r.id = ?', [$id]);
    }

    /** Khóa hàng release (dùng trong transaction sau khi đã khóa dự án). @return array<string,mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        return $this->one('SELECT * FROM releases WHERE id = ? FOR UPDATE', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function listByProject(int $projectId): array
    {
        return $this->all(
            "SELECT r.id, r.name, r.status, r.planned_date, r.released_at,
                    (SELECT COUNT(*) FROM release_items ri WHERE ri.release_id = r.id) AS item_count
               FROM releases r WHERE r.project_id = ? ORDER BY r.status = 'released', r.planned_date IS NULL, r.planned_date, r.id",
            [$projectId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listWithCounts(int $projectId): array
    {
        $complete = self::completeSql('i');
        return $this->all(
            "SELECT r.id, r.name, r.status, r.planned_date, r.released_at, r.description, o.full_name AS owner_name, rb.full_name AS released_by_name,
                    CASE WHEN r.status = 'released'
                         THEN (SELECT COUNT(*) FROM release_snapshot_items s WHERE s.release_id = r.id)
                         ELSE (SELECT COUNT(*) FROM release_items ri WHERE ri.release_id = r.id) END AS item_count,
                    CASE WHEN r.status = 'released'
                         THEN (SELECT COUNT(*) FROM release_snapshot_items s WHERE s.release_id = r.id)
                         ELSE (SELECT COUNT(*) FROM release_items ri JOIN issues i ON i.id = ri.issue_id WHERE ri.release_id = r.id AND {$complete}) END AS done_count
               FROM releases r LEFT JOIN users o ON o.id = r.owner_id LEFT JOIN users rb ON rb.id = r.released_by
              WHERE r.project_id = ? ORDER BY r.status = 'released', r.planned_date IS NULL, r.planned_date, r.id",
            [$projectId]
        );
    }

    /** @param array<string,mixed> $d */
    public function insert(array $d): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->run(
            'INSERT INTO releases (project_id, name, description, planned_date, owner_id, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
            [$d['project_id'], $d['name'], $d['description'], $d['planned_date'], $d['owner_id'], $d['created_by'], $now, $now]
        );
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $d */
    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE releases SET name = ?, description = ?, planned_date = ?, owner_id = ?, version = version + 1, updated_at = ? WHERE id = ?',
            [$d['name'], $d['description'], $d['planned_date'], $d['owner_id'], gmdate('Y-m-d H:i:s'), $id]
        );
    }

    public function markReleased(int $id, int $userId, string $checksum, string $reportJson, ?string $at = null): int
    {
        return $this->run(
            "UPDATE releases SET status = 'released', released_at = ?, released_by = ?, snapshot_checksum = ?, readiness_report = ?, version = version + 1, updated_at = ?
              WHERE id = ? AND status = 'planned'",
            [$at ?? gmdate('Y-m-d H:i:s'), $userId, $checksum, $reportJson, $at ?? gmdate('Y-m-d H:i:s'), $id]
        );
    }

    /** Công việc của release kèm dữ liệu hiện tại và kết quả kiểm thử mới nhất. @return array<int,array<string,mixed>> */
    public function items(int $releaseId): array
    {
        return $this->all(
            'SELECT ri.issue_id, ri.is_required, ri.project_id, i.code, i.title, i.type, i.status, i.severity, i.priority, i.description,
                    i.fix_revision, i.fix_ref, i.assignee_id, a.full_name AS assignee_name,
                    (SELECT t.id FROM test_results t WHERE t.issue_id = i.id ORDER BY t.id DESC LIMIT 1) AS last_test_id,
                    (SELECT t.result FROM test_results t WHERE t.issue_id = i.id ORDER BY t.id DESC LIMIT 1) AS last_test_result,
                    (SELECT t.fix_revision FROM test_results t WHERE t.issue_id = i.id ORDER BY t.id DESC LIMIT 1) AS last_test_revision
               FROM release_items ri JOIN issues i ON i.id = ri.issue_id LEFT JOIN users a ON a.id = i.assignee_id
              WHERE ri.release_id = ? ORDER BY i.number',
            [$releaseId]
        );
    }

    /** Phụ thuộc chưa hoàn thành của các công việc trong release (kể cả ngoài release). @return array<int,array<string,mixed>> */
    public function incompleteDependencies(int $releaseId): array
    {
        return $this->all(
            'SELECT a.id AS item_id, a.code AS item_code, b.id AS dep_id, b.code AS dep_code, b.title AS dep_title, b.status AS dep_status, b.type AS dep_type,
                    EXISTS (SELECT 1 FROM release_items r2 WHERE r2.release_id = ri.release_id AND r2.issue_id = b.id) AS in_release
               FROM release_items ri
               JOIN issues a ON a.id = ri.issue_id
               JOIN dependencies d ON d.issue_id = a.id
               JOIN issues b ON b.id = d.depends_on_id
              WHERE ri.release_id = ? AND ri.is_required = 1 AND NOT ' . self::completeSql('b') . ' ORDER BY a.number, b.number',
            [$releaseId]
        );
    }

    public function dependencyCodes(int $issueId): array
    {
        return array_map('strval', $this->pdo->query(
            'SELECT b.code FROM dependencies d JOIN issues b ON b.id = d.depends_on_id WHERE d.issue_id = ' . (int) $issueId . ' ORDER BY b.number'
        )->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function addItem(int $projectId, int $releaseId, int $issueId, bool $required, int $userId): void
    {
        $this->run(
            'INSERT INTO release_items (project_id, release_id, issue_id, is_required, added_by, added_at) VALUES (?,?,?,?,?,?)',
            [$projectId, $releaseId, $issueId, $required ? 1 : 0, $userId, gmdate('Y-m-d H:i:s')]
        );
    }

    public function removeItem(int $releaseId, int $issueId): int
    {
        return $this->run('DELETE FROM release_items WHERE release_id = ? AND issue_id = ?', [$releaseId, $issueId]);
    }

    public function setRequired(int $releaseId, int $issueId, bool $required): void
    {
        $this->run('UPDATE release_items SET is_required = ? WHERE release_id = ? AND issue_id = ?', [$required ? 1 : 0, $releaseId, $issueId]);
    }

    /** Release dự kiến (chưa chốt) đang chứa issue, trừ $exceptReleaseId. @return array<string,mixed>|null */
    public function otherPlannedRelease(int $issueId, int $exceptReleaseId): ?array
    {
        return $this->one(
            "SELECT r.id, r.name FROM release_items ri JOIN releases r ON r.id = ri.release_id
              WHERE ri.issue_id = ? AND r.status = 'planned' AND r.id <> ? LIMIT 1",
            [$issueId, $exceptReleaseId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function releasesOfIssue(int $issueId): array
    {
        return $this->all(
            'SELECT r.id, r.name, r.status FROM release_items ri JOIN releases r ON r.id = ri.release_id WHERE ri.issue_id = ? ORDER BY r.id',
            [$issueId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function candidateIssues(int $projectId, int $releaseId): array
    {
        return $this->all(
            "SELECT i.id, i.code, i.title, i.type, i.status, i.severity FROM issues i
              WHERE i.project_id = ?
                AND NOT EXISTS (SELECT 1 FROM release_items ri JOIN releases r ON r.id = ri.release_id
                                 WHERE ri.issue_id = i.id AND (ri.release_id = ? OR r.status = 'planned'))
              ORDER BY i.number DESC LIMIT 200",
            [$projectId, $releaseId]
        );
    }

    /** @param array<string,mixed> $row */
    public function insertSnapshot(array $row): void
    {
        $cols = [
            'release_id', 'project_id', 'issue_id', 'issue_code', 'title', 'type', 'category', 'change_summary', 'priority', 'severity',
            'status', 'is_required', 'assignee_name', 'fix_ref', 'fix_revision', 'dependency_codes', 'test_evidence', 'finalized_by', 'finalized_at',
        ];
        $this->run(
            'INSERT INTO release_snapshot_items (' . implode(',', $cols) . ') VALUES (' . self::in($cols) . ')',
            array_map(static fn ($c) => $row[$c], $cols)
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function snapshotItems(int $releaseId): array
    {
        return $this->all('SELECT * FROM release_snapshot_items WHERE release_id = ? ORDER BY issue_id', [$releaseId]);
    }

    public function snapshotCount(int $releaseId): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM release_snapshot_items WHERE release_id = ?', [$releaseId]);
    }
}
