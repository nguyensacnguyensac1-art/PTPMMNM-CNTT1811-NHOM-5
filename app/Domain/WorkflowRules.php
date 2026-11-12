<?php
declare(strict_types=1);

namespace ReleaseHub\Domain;

/**
 * Bảng chuyển trạng thái được công bố (xem docs/workflow.md).
 * via: transition = đổi trạng thái trực tiếp; fix = qua "Ghi nhận bản sửa"; test = qua "Ghi kết quả kiểm thử".
 */
final class WorkflowRules
{
    private const DEV = ['developer', 'project_manager', 'release_manager'];

    /** @return array<int,array{from:string,to:string,roles:string[],via:string,note:string}> */
    public static function rules(string $type): array
    {
        if ($type === 'bug') {
            return [
                self::r('open', 'in_progress', self::DEV, 'transition', 'Nhận xử lý lỗi'),
                self::r('reopened', 'in_progress', self::DEV, 'transition', 'Xử lý lại lỗi bị mở lại'),
                self::r('in_progress', 'open', ['developer', 'project_manager'], 'transition', 'Trả lại hàng đợi'),
                self::r('in_progress', 'resolved', ['developer', 'project_manager'], 'fix', 'Chỉ qua "Ghi nhận bản sửa" (bắt buộc mã commit/PR)'),
                self::r('resolved', 'in_progress', ['developer', 'project_manager'], 'transition', 'Rút lại bản sửa để làm tiếp'),
                self::r('resolved', 'verified', ['tester'], 'test', 'Chỉ qua kết quả kiểm thử Passed của bản sửa hiện tại'),
                self::r('resolved', 'reopened', ['tester'], 'test', 'Chỉ qua kết quả kiểm thử Failed'),
                self::r('verified', 'closed', ['tester', 'project_manager', 'release_manager'], 'transition', 'Đóng lỗi đã xác nhận'),
                self::r('verified', 'reopened', ['tester', 'project_manager', 'release_manager'], 'transition', 'Lỗi xuất hiện lại'),
                self::r('closed', 'reopened', ['tester', 'project_manager', 'release_manager'], 'transition', 'Lỗi tái phát'),
            ];
        }
        return [
            self::r('open', 'in_progress', self::DEV, 'transition', 'Bắt đầu thực hiện'),
            self::r('reopened', 'in_progress', self::DEV, 'transition', 'Làm lại'),
            self::r('in_progress', 'open', ['developer', 'project_manager'], 'transition', 'Trả lại hàng đợi'),
            self::r('in_progress', 'resolved', self::DEV, 'transition', 'Hoàn thành'),
            self::r('resolved', 'in_progress', self::DEV, 'transition', 'Làm lại phần chưa đạt'),
            self::r('resolved', 'closed', ['project_manager', 'release_manager'], 'transition', 'Nghiệm thu và đóng'),
            self::r('resolved', 'reopened', ['project_manager', 'release_manager', 'tester'], 'transition', 'Mở lại'),
            self::r('closed', 'reopened', ['project_manager', 'release_manager'], 'transition', 'Mở lại'),
        ];
    }

    /** @return array{from:string,to:string,roles:string[],via:string,note:string}|null */
    public static function find(string $type, string $from, string $to): ?array
    {
        foreach (self::rules($type) as $rule) {
            if ($rule['from'] === $from && $rule['to'] === $to) {
                return $rule;
            }
        }
        return null;
    }

    /** @return array<int,array{from:string,to:string,roles:string[],via:string,note:string}> */
    public static function allowedFor(string $type, string $from, string $role, bool $isAdmin = false): array
    {
        return array_values(array_filter(
            self::rules($type),
            static fn (array $r): bool => $r['from'] === $from && ($isAdmin || in_array($role, $r['roles'], true))
        ));
    }

    /** @param string[] $roles @return array{from:string,to:string,roles:string[],via:string,note:string} */
    private static function r(string $from, string $to, array $roles, string $via, string $note): array
    {
        return compact('from', 'to', 'roles', 'via', 'note');
    }
}
