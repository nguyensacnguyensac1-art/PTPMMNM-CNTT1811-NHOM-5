<?php
declare(strict_types=1);

use ReleaseHub\Core\Csrf;
use ReleaseHub\Core\Request;
use ReleaseHub\Domain\Catalog;
use ReleaseHub\Support\Time;

if (!function_exists('e')) {
    /** Escape HTML (chống XSS) cho mọi nội dung hiển thị. */
    function e(mixed $v): string
    {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Thư mục con chứa front controller, ví dụ /releasehub/public (không hardcode theo máy). */
    function base_url(): string
    {
        $GLOBALS['__rh_base'] ??= Request::detectBasePath();
        return $GLOBALS['__rh_base'];
    }

    function url(string $path = ''): string
    {
        return base_url() . '/' . ltrim($path, '/');
    }

    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }

    function csrf_token(): string
    {
        return Csrf::token();
    }

    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
    }

    function vn_dt(?string $utc, string $fmt = 'd/m/Y H:i'): string
    {
        return Time::vn($utc, $fmt);
    }

    function vn_date(?string $d): string
    {
        return Time::vnDate($d);
    }

    function status_label(string $status, string $type = 'bug'): string
    {
        return Catalog::statusLabel($status, $type);
    }

    function status_badge(string $status, string $type = 'bug'): string
    {
        $icons = ['open' => 'bi-circle', 'in_progress' => 'bi-arrow-repeat', 'resolved' => 'bi-wrench-adjustable', 'verified' => 'bi-patch-check', 'closed' => 'bi-lock', 'reopened' => 'bi-arrow-counterclockwise'];
        return '<span class="rh-badge st-' . e($status) . '"><i class="bi ' . ($icons[$status] ?? 'bi-circle') . '"></i> ' . e(Catalog::statusLabel($status, $type)) . '</span>';
    }

    function severity_badge(?string $sev): string
    {
        if (!$sev) {
            return '<span class="text-muted">—</span>';
        }
        return '<span class="rh-badge sv-' . e($sev) . '">' . e(Catalog::SEVERITIES[$sev] ?? $sev) . '</span>';
    }

    function priority_badge(string $p): string
    {
        $icons = ['low' => 'bi-chevron-down', 'medium' => 'bi-dash', 'high' => 'bi-chevron-up', 'urgent' => 'bi-chevron-double-up'];
        return '<span class="rh-badge pr-' . e($p) . '"><i class="bi ' . ($icons[$p] ?? 'bi-dash') . '"></i> ' . e(Catalog::PRIORITIES[$p] ?? $p) . '</span>';
    }

    function type_badge(string $t): string
    {
        return '<span class="rh-type ty-' . e($t) . '"><i class="bi ' . (Catalog::TYPE_ICONS[$t] ?? 'bi-circle') . '"></i> ' . e(Catalog::TYPES[$t] ?? $t) . '</span>';
    }

    /** Đăng ký script riêng cho trang (layout sẽ in ra cuối trang). */
    function add_script(string $file): void
    {
        $GLOBALS['__rh_scripts'][$file] = true;
    }

    /** @return string[] */
    function page_scripts(): array
    {
        return array_keys($GLOBALS['__rh_scripts'] ?? []);
    }

    /** Render một partial (không layout). @param array<string,mixed> $vars */
    function partial(string $name, array $vars = []): string
    {
        return \ReleaseHub\Core\View::render('partials/' . $name, $vars, null);
    }

    /** Query string hiện tại có thay đổi một số tham số (giữ bộ lọc khi sắp xếp/phân trang). @param array<string,mixed> $over */
    function qs(array $over = []): string
    {
        $q = array_merge($_GET, $over);
        foreach ($q as $k => $v) {
            if ($v === null || $v === '') {
                unset($q[$k]);
            }
        }
        return $q ? '?' . http_build_query($q) : '';
    }

    function role_label(?string $role): string
    {
        return $role === 'system_admin' ? 'System Admin' : (Catalog::ROLES[$role ?? ''] ?? '—');
    }

    /** Tên gọi (từ cuối) của họ tên tiếng Việt. */
    function last_word(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return (string) end($parts);
    }

    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $last = end($parts) ?: '?';
        $first = $parts[0] ?? '';
        return mb_strtoupper(mb_substr($first, 0, 1) . (count($parts) > 1 ? mb_substr($last, 0, 1) : ''));
    }

    /** Mô tả một dòng lịch sử thay đổi bằng tiếng Việt. @param array<string,mixed> $h */
    function history_text(array $h): string
    {
        $c = $h['changes'] ? (json_decode((string) $h['changes'], true) ?: []) : [];
        $fieldNames = ['title' => 'tiêu đề', 'description' => 'mô tả', 'priority' => 'mức ưu tiên', 'severity' => 'mức nghiêm trọng', 'due_date' => 'thời hạn',
            'environment' => 'môi trường', 'steps_to_reproduce' => 'bước tái hiện', 'expected_result' => 'kết quả mong đợi', 'actual_result' => 'kết quả thực tế',
            'commit_url' => 'liên kết commit', 'pr_url' => 'liên kết PR', 'assignee_id' => 'người xử lý'];
        return match ($h['action']) {
            'created' => 'đã tạo công việc',
            'updated' => 'đã sửa ' . implode(', ', array_map(fn ($k) => $fieldNames[$k] ?? $k, array_keys($c))),
            'status_changed' => 'đã chuyển trạng thái: ' . Catalog::STATUSES[$c['status'][0] ?? ''] . ' → ' . Catalog::STATUSES[$c['status'][1] ?? ''],
            'fix_recorded' => 'đã ghi nhận bản sửa #' . ($c['fix_revision'] ?? '?') . ' (' . ($c['fix_ref'] ?? '') . ')',
            'test_recorded' => 'đã ghi kết quả kiểm thử ' . (($c['result'] ?? '') === 'passed' ? 'Passed' : 'Failed') . ' cho bản sửa #' . ($c['fix_revision'] ?? '?'),
            'commented' => 'đã bình luận',
            'dependency_added' => 'đã thêm phụ thuộc vào ' . ($c['depends_on'] ?? ''),
            'dependency_removed' => 'đã gỡ phụ thuộc vào ' . ($c['depends_on'] ?? ''),
            'dependent_added' => 'được ' . ($c['blocks'] ?? '') . ' phụ thuộc vào',
            'dependent_removed' => 'không còn được ' . ($c['blocks'] ?? '') . ' phụ thuộc',
            'release_attached' => 'được gắn vào phiên bản ' . ($c['release'] ?? ''),
            'release_detached' => 'được gỡ khỏi phiên bản ' . ($c['release'] ?? ''),
            default => (string) $h['action'],
        };
    }
}
