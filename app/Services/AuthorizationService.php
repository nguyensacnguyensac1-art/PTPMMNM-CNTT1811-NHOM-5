<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use ReleaseHub\Core\ForbiddenException;
use ReleaseHub\Domain\Permissions;
use ReleaseHub\Repositories\MemberRepository;

/**
 * Kiểm tra quyền ở backend cho MỌI thao tác. Quy tắc:
 *  - Mỗi người có đúng MỘT vai trò trong mỗi dự án (vai trò có thể khác nhau giữa các dự án).
 *  - System Admin (users.is_admin) có mọi quyền ở mọi dự án.
 *  - Không phải thành viên => bị từ chối với mọi thao tác đọc/ghi.
 */
final class AuthorizationService
{
    public const ADMIN_ROLE = 'system_admin';

    public function __construct(private MemberRepository $members)
    {
    }

    /** @param array<string,mixed> $user */
    public function roleOf(array $user, int $projectId): ?string
    {
        if (!empty($user['is_admin'])) {
            return self::ADMIN_ROLE;
        }
        return $this->members->roleOf($projectId, (int) $user['id']);
    }

    /** @param array<string,mixed> $user */
    public function can(array $user, string $permission, int $projectId): bool
    {
        $role = $this->roleOf($user, $projectId);
        if ($role === null) {
            return false;
        }
        return $role === self::ADMIN_ROLE || Permissions::roleHas($role, $permission);
    }

    /**
     * @param array<string,mixed> $user
     * @return string vai trò hiện hành của người dùng trong dự án
     */
    public function authorize(array $user, string $permission, int $projectId): string
    {
        $role = $this->roleOf($user, $projectId);
        if ($role === null) {
            throw new ForbiddenException('Bạn không thuộc dự án này nên không thể truy cập.');
        }
        if ($role !== self::ADMIN_ROLE && !Permissions::roleHas($role, $permission)) {
            throw new ForbiddenException('Vai trò hiện tại của bạn không được phép thực hiện thao tác này.');
        }
        return $role;
    }

    /** @param array<string,mixed> $user @return int[]|null null = mọi dự án (System Admin) */
    public function visibleProjectIds(array $user): ?array
    {
        return !empty($user['is_admin']) ? null : $this->members->projectIdsOf((int) $user['id']);
    }
}
