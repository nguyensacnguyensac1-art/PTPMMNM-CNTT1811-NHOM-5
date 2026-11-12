<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

final class MemberRepository extends Repository
{
    public function roleOf(int $projectId, int $userId): ?string
    {
        $r = $this->scalar('SELECT role_code FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $userId]);
        return $r === null ? null : (string) $r;
    }

    /** @return array<int,array<string,mixed>> */
    public function list(int $projectId): array
    {
        return $this->all(
            'SELECT m.user_id, m.role_code, m.created_at, u.full_name, u.email
               FROM project_members m JOIN users u ON u.id = m.user_id
              WHERE m.project_id = ?
           ORDER BY FIELD(m.role_code, \'project_manager\',\'release_manager\',\'developer\',\'tester\',\'viewer\'), u.full_name',
            [$projectId]
        );
    }

    public function add(int $projectId, int $userId, string $role): void
    {
        $this->run('INSERT INTO project_members (project_id, user_id, role_code, created_at) VALUES (?,?,?,?)', [$projectId, $userId, $role, gmdate('Y-m-d H:i:s')]);
    }

    public function setRole(int $projectId, int $userId, string $role): void
    {
        $this->run('UPDATE project_members SET role_code = ? WHERE project_id = ? AND user_id = ?', [$role, $projectId, $userId]);
    }

    public function remove(int $projectId, int $userId): void
    {
        $this->run('DELETE FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $userId]);
    }

    public function countRole(int $projectId, string $role): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM project_members WHERE project_id = ? AND role_code = ?', [$projectId, $role]);
    }

    /** @return array<int,int> id các dự án người dùng là thành viên */
    public function projectIdsOf(int $userId): array
    {
        return array_map('intval', $this->pdo->query('SELECT project_id FROM project_members WHERE user_id = ' . (int) $userId)->fetchAll(\PDO::FETCH_COLUMN));
    }
}
