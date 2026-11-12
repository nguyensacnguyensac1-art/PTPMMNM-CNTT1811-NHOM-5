<?php
declare(strict_types=1);

namespace ReleaseHub\Domain;

/** Danh mục giá trị (loại, trạng thái, mức độ...) kèm nhãn tiếng Việt. */
final class Catalog
{
    public const TYPES = ['bug' => 'Lỗi', 'task' => 'Công việc', 'improvement' => 'Cải tiến'];
    public const TYPE_ICONS = ['bug' => 'bi-bug', 'task' => 'bi-check2-square', 'improvement' => 'bi-arrow-up-circle'];

    public const STATUSES = [
        'open' => 'Mới mở',
        'in_progress' => 'Đang xử lý',
        'resolved' => 'Đã sửa – chờ kiểm thử',
        'verified' => 'Đã xác nhận',
        'closed' => 'Đã đóng',
        'reopened' => 'Mở lại',
    ];
    public const TASK_STATUS_OVERRIDES = ['resolved' => 'Hoàn thành'];
    public const STATUS_ORDER = ['open', 'reopened', 'in_progress', 'resolved', 'verified', 'closed'];

    public const PRIORITIES = ['low' => 'Thấp', 'medium' => 'Trung bình', 'high' => 'Cao', 'urgent' => 'Khẩn cấp'];
    public const SEVERITIES = [
        'trivial' => 'Rất nhẹ', 'minor' => 'Nhẹ', 'major' => 'Vừa',
        'critical' => 'Nghiêm trọng', 'blocker' => 'Blocker (chặn)',
    ];

    public const ROLES = [
        'project_manager' => 'Project Manager',
        'release_manager' => 'Release Manager',
        'developer' => 'Developer',
        'tester' => 'Tester',
        'viewer' => 'Viewer',
    ];

    public const RELEASE_STATUSES = ['planned' => 'Dự kiến', 'released' => 'Đã phát hành'];

    public const SNAPSHOT_CATEGORIES = [
        'bugfix' => 'Sửa lỗi',
        'improvement' => 'Cải tiến',
        'task' => 'Công việc / tính năng',
    ];

    public static function statusLabel(string $status, string $type = 'bug'): string
    {
        if ($type !== 'bug' && isset(self::TASK_STATUS_OVERRIDES[$status])) {
            return self::TASK_STATUS_OVERRIDES[$status];
        }
        return self::STATUSES[$status] ?? $status;
    }

    public static function categoryFor(string $type): string
    {
        return match ($type) {
            'bug' => 'bugfix',
            'improvement' => 'improvement',
            default => 'task',
        };
    }

    /** Hoàn thành = Bug: Verified/Closed; Task & Cải tiến: Resolved(Hoàn thành)/Closed. */
    public static function isComplete(string $type, string $status): bool
    {
        if ($type === 'bug') {
            return in_array($status, ['verified', 'closed'], true);
        }
        return in_array($status, ['resolved', 'closed'], true);
    }

    /** @return string[] danh sách trạng thái được coi là hoàn thành của loại này */
    public static function completeStatuses(string $type): array
    {
        return $type === 'bug' ? ['verified', 'closed'] : ['resolved', 'closed'];
    }
}
