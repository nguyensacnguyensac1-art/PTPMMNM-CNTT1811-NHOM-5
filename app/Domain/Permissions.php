<?php
declare(strict_types=1);

namespace ReleaseHub\Domain;

/** Ma trận quyền theo vai trò dự án. System Admin có mọi quyền trên mọi dự án. */
final class Permissions
{
    public const ALL = [
        'project.view', 'project.manage', 'member.manage',
        'issue.create', 'issue.edit', 'issue.assign', 'issue.transition', 'issue.fix',
        'comment.create', 'test.record', 'dependency.manage',
        'release.view', 'release.plan', 'release.finalize',
    ];

    public const MATRIX = [
        'project_manager' => [
            'project.view', 'project.manage', 'member.manage',
            'issue.create', 'issue.edit', 'issue.assign', 'issue.transition', 'issue.fix',
            'comment.create', 'dependency.manage', 'release.view', 'release.plan',
        ],
        'release_manager' => [
            'project.view', 'issue.create', 'issue.edit', 'issue.assign', 'issue.transition',
            'comment.create', 'dependency.manage', 'release.view', 'release.plan', 'release.finalize',
        ],
        'developer' => [
            'project.view', 'issue.create', 'issue.edit', 'issue.transition', 'issue.fix',
            'comment.create', 'dependency.manage', 'release.view',
        ],
        'tester' => [
            'project.view', 'issue.create', 'issue.transition',
            'comment.create', 'test.record', 'release.view',
        ],
        'viewer' => ['project.view', 'release.view'],
    ];

    public const LABELS = [
        'project.view' => 'Xem dự án, công việc, phiên bản',
        'project.manage' => 'Sửa / lưu trữ dự án',
        'member.manage' => 'Quản lý thành viên và vai trò',
        'issue.create' => 'Tạo lỗi / công việc',
        'issue.edit' => 'Sửa nội dung công việc',
        'issue.assign' => 'Phân công người xử lý',
        'issue.transition' => 'Chuyển trạng thái (theo bảng workflow)',
        'issue.fix' => 'Ghi nhận bản sửa',
        'comment.create' => 'Bình luận',
        'test.record' => 'Ghi kết quả kiểm thử',
        'dependency.manage' => 'Thêm / gỡ phụ thuộc',
        'release.view' => 'Xem & xuất ghi chú phát hành',
        'release.plan' => 'Lập kế hoạch phiên bản, gắn / gỡ công việc',
        'release.finalize' => 'Chốt phát hành',
    ];

    public static function roleHas(string $role, string $permission): bool
    {
        return in_array($permission, self::MATRIX[$role] ?? [], true);
    }
}
