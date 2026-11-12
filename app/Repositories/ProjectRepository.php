<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

final class ProjectRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->one('SELECT p.*, u.full_name AS owner_name FROM projects p LEFT JOIN users u ON u.id = p.owner_id WHERE p.id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM projects WHERE code = ?', [strtoupper($code)]);
    }

    /**
     * Danh sách dự án + tiến độ thật. $userId = null: tất cả dự án (System Admin).
     *
     * @param array{q?:string,status?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function listVisible(?int $userId, array $filters = []): array
    {
        $complete = self::completeSql('i');
        $sql = "SELECT p.id, p.code, p.name, p.description, p.color, p.icon, p.archived_at, p.owner_id, u.full_name AS owner_name,"
            . ($userId === null ? " NULL AS my_role," : " pm.role_code AS my_role,")
            . " (SELECT COUNT(*) FROM issues i WHERE i.project_id = p.id) AS issue_total,
                (SELECT COUNT(*) FROM issues i WHERE i.project_id = p.id AND {$complete}) AS issue_done,
                (SELECT COUNT(*) FROM issues i WHERE i.project_id = p.id AND i.type = 'bug' AND NOT {$complete}) AS open_bugs,
                (SELECT COUNT(*) FROM releases r WHERE r.project_id = p.id AND r.status = 'planned') AS planned_releases,
                (SELECT COUNT(*) FROM project_members m WHERE m.project_id = p.id) AS member_count
              FROM projects p LEFT JOIN users u ON u.id = p.owner_id ";
        $params = [];
        $where = [];
        if ($userId !== null) {
            $sql .= ' JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ? ';
            $params[] = $userId;
        }
        if (($filters['status'] ?? '') === 'archived') {
            $where[] = 'p.archived_at IS NOT NULL';
        } elseif (($filters['status'] ?? '') !== 'all') {
            $where[] = 'p.archived_at IS NULL';
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?)';
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        return $this->all($sql . ' ORDER BY p.code', $params);
    }

    /** @param array<string,mixed> $d */
    public function insert(array $d): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->run(
            'INSERT INTO projects (code, name, description, color, icon, owner_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
            [$d['code'], $d['name'], $d['description'], $d['color'], $d['icon'], $d['owner_id'], $now, $now]
        );
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $d */
    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE projects SET name = ?, description = ?, color = ?, icon = ?, owner_id = ?, version = version + 1, updated_at = ? WHERE id = ?',
            [$d['name'], $d['description'], $d['color'], $d['icon'], $d['owner_id'], gmdate('Y-m-d H:i:s'), $id]
        );
    }

    public function setArchived(int $id, bool $archived): void
    {
        $this->run('UPDATE projects SET archived_at = ?, updated_at = ?, version = version + 1 WHERE id = ?', [$archived ? gmdate('Y-m-d H:i:s') : null, gmdate('Y-m-d H:i:s'), $id]);
    }

    public function nextIssueNumber(int $projectId): int
    {
        $this->run('UPDATE projects SET issue_counter = LAST_INSERT_ID(issue_counter + 1) WHERE id = ?', [$projectId]);
        return (int) $this->scalar('SELECT LAST_INSERT_ID()');
    }

    /** @return int[] */
    public function allIds(): array
    {
        return array_map('intval', $this->pdo->query('SELECT id FROM projects ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Danh sách gọn cho sidebar. @return array<int,array<string,mixed>> */
    public function simpleList(?int $userId): array
    {
        if ($userId === null) {
            return $this->all('SELECT id, code, name, color, icon FROM projects WHERE archived_at IS NULL ORDER BY code');
        }
        return $this->all(
            'SELECT p.id, p.code, p.name, p.color, p.icon FROM projects p JOIN project_members m ON m.project_id = p.id AND m.user_id = ?
              WHERE p.archived_at IS NULL ORDER BY p.code',
            [$userId]
        );
    }
}
