<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use PDO;
use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\Database;
use ReleaseHub\Core\NotFoundException;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Domain\Catalog;
use ReleaseHub\Repositories\HistoryRepository;
use ReleaseHub\Repositories\MemberRepository;
use ReleaseHub\Repositories\UserRepository;

final class MemberService
{
    public function __construct(
        private PDO $pdo,
        private MemberRepository $members,
        private UserRepository $users,
        private HistoryRepository $history,
        private AuthorizationService $auth
    ) {
    }

    /** @param array<string,mixed> $actor @return array<int,array<string,mixed>> */
    public function list(array $actor, int $projectId): array
    {
        $this->auth->authorize($actor, 'project.view', $projectId);
        return $this->members->list($projectId);
    }

    /** @param array<string,mixed> $actor @return array<int,array<string,mixed>> người dùng có thể thêm */
    public function candidates(array $actor, int $projectId): array
    {
        $this->auth->authorize($actor, 'member.manage', $projectId);
        $current = array_column($this->members->list($projectId), 'user_id');
        return array_values(array_filter($this->users->activeList(), static fn ($u) => !in_array($u['id'], $current, false)));
    }

    /** @param array<string,mixed> $actor */
    public function add(array $actor, int $projectId, string $email, string $role, string $ip = ''): void
    {
        $this->auth->authorize($actor, 'member.manage', $projectId);
        if (!isset(Catalog::ROLES[$role])) {
            throw new ValidationException(['role' => 'Vai trò không hợp lệ.']);
        }
        $user = $this->users->findByEmail($email);
        if (!$user || (int) $user['is_active'] !== 1) {
            throw new ValidationException(['email' => 'Không tìm thấy người dùng với email này.']);
        }
        Database::transaction(function (PDO $pdo) use ($actor, $projectId, $user, $role, $ip) {
            Database::lockProject($pdo, $projectId);
            if ($this->members->roleOf($projectId, (int) $user['id']) !== null) {
                throw new ConflictException('Người dùng đã là thành viên của dự án.', 'already_member');
            }
            $this->members->add($projectId, (int) $user['id'], $role);
            $this->history->audit($projectId, (int) $actor['id'], 'member.add', 'user', (int) $user['id'], ['role' => $role], $ip);
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor */
    public function changeRole(array $actor, int $projectId, int $userId, string $role, string $ip = ''): void
    {
        $this->auth->authorize($actor, 'member.manage', $projectId);
        if (!isset(Catalog::ROLES[$role])) {
            throw new ValidationException(['role' => 'Vai trò không hợp lệ.']);
        }
        Database::transaction(function (PDO $pdo) use ($actor, $projectId, $userId, $role, $ip) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->members->roleOf($projectId, $userId) ?? throw new NotFoundException('Người dùng không thuộc dự án.');
            if ($cur === 'project_manager' && $role !== 'project_manager' && $this->members->countRole($projectId, 'project_manager') <= 1) {
                throw new ConflictException('Dự án phải còn ít nhất một Project Manager.', 'last_manager');
            }
            $this->members->setRole($projectId, $userId, $role);
            $this->history->audit($projectId, (int) $actor['id'], 'member.role', 'user', $userId, ['from' => $cur, 'to' => $role], $ip);
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor */
    public function remove(array $actor, int $projectId, int $userId, string $ip = ''): void
    {
        $this->auth->authorize($actor, 'member.manage', $projectId);
        Database::transaction(function (PDO $pdo) use ($actor, $projectId, $userId, $ip) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->members->roleOf($projectId, $userId) ?? throw new NotFoundException('Người dùng không thuộc dự án.');
            if ($cur === 'project_manager' && $this->members->countRole($projectId, 'project_manager') <= 1) {
                throw new ConflictException('Không thể gỡ Project Manager cuối cùng của dự án.', 'last_manager');
            }
            $this->members->remove($projectId, $userId);
            // Gỡ phân công các công việc chưa xong của người này trong dự án.
            $pdo->prepare('UPDATE issues SET assignee_id = NULL, version = version + 1, updated_at = ? WHERE project_id = ? AND assignee_id = ?')
                ->execute([gmdate('Y-m-d H:i:s'), $projectId, $userId]);
            $this->history->audit($projectId, (int) $actor['id'], 'member.remove', 'user', $userId, ['role' => $cur], $ip);
        }, $this->pdo);
    }
}
