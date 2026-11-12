<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

final class DependencyRepository extends Repository
{
    /** @return array<int,array{issue_id:int,depends_on_id:int}> */
    public function edges(int $projectId): array
    {
        $rows = $this->all('SELECT issue_id, depends_on_id FROM dependencies WHERE project_id = ?', [$projectId]);
        return array_map(static fn ($r) => ['issue_id' => (int) $r['issue_id'], 'depends_on_id' => (int) $r['depends_on_id']], $rows);
    }

    public function exists(int $issueId, int $dependsOnId): bool
    {
        return $this->scalar('SELECT 1 FROM dependencies WHERE issue_id = ? AND depends_on_id = ?', [$issueId, $dependsOnId]) !== null;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM dependencies WHERE id = ?', [$id]);
    }

    public function insert(int $projectId, int $issueId, int $dependsOnId, int $userId): int
    {
        $this->run(
            'INSERT INTO dependencies (project_id, issue_id, depends_on_id, created_by, created_at) VALUES (?,?,?,?,?)',
            [$projectId, $issueId, $dependsOnId, $userId, gmdate('Y-m-d H:i:s')]
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->run('DELETE FROM dependencies WHERE id = ?', [$id]);
    }

    /** B mà issue này phụ thuộc vào (phải xong trước). @return array<int,array<string,mixed>> */
    public function dependenciesOf(int $issueId): array
    {
        return $this->all(
            'SELECT d.id AS dependency_id, b.id, b.code, b.title, b.type, b.status, b.severity, ' . self::completeSql('b') . ' AS is_complete
               FROM dependencies d JOIN issues b ON b.id = d.depends_on_id WHERE d.issue_id = ? ORDER BY b.number',
            [$issueId]
        );
    }

    /** Các công việc đang chờ issue này. @return array<int,array<string,mixed>> */
    public function dependentsOf(int $issueId): array
    {
        return $this->all(
            'SELECT d.id AS dependency_id, a.id, a.code, a.title, a.type, a.status, a.severity, ' . self::completeSql('a') . ' AS is_complete
               FROM dependencies d JOIN issues a ON a.id = d.issue_id WHERE d.depends_on_id = ? ORDER BY a.number',
            [$issueId]
        );
    }

    /** Nút của đồ thị = mọi công việc có ít nhất một cạnh. @return array<int,array<string,mixed>> */
    public function graphNodes(int $projectId): array
    {
        return $this->all(
            'SELECT i.id, i.code, i.title, i.type, i.status, i.severity, ' . self::completeSql('i') . ' AS is_complete
               FROM issues i
              WHERE i.project_id = ? AND (EXISTS (SELECT 1 FROM dependencies d WHERE d.issue_id = i.id)
                 OR EXISTS (SELECT 1 FROM dependencies d WHERE d.depends_on_id = i.id))
           ORDER BY i.number',
            [$projectId]
        );
    }

    /** Công việc CHƯA xong và đang chặn ít nhất một công việc chưa xong khác. @return array<int,array<string,mixed>> */
    public function blockers(int $projectId): array
    {
        return $this->all(
            'SELECT b.id, b.code, b.title, b.status, b.severity, b.type, COUNT(DISTINCT a.id) AS blocking_count,
                    GROUP_CONCAT(DISTINCT a.code ORDER BY a.number SEPARATOR \', \') AS blocking_codes
               FROM dependencies d
               JOIN issues b ON b.id = d.depends_on_id
               JOIN issues a ON a.id = d.issue_id
              WHERE d.project_id = ? AND NOT ' . self::completeSql('b') . ' AND NOT ' . self::completeSql('a') . '
           GROUP BY b.id, b.code, b.title, b.status, b.severity, b.type, b.number ORDER BY blocking_count DESC, b.number',
            [$projectId]
        );
    }
}
