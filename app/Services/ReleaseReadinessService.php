<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use ReleaseHub\Core\NotFoundException;
use ReleaseHub\Domain\Catalog;
use ReleaseHub\Repositories\ReleaseRepository;

/**
 * Điều kiện phát hành (tự viết). Một phiên bản chỉ "sẵn sàng" khi TẤT CẢ điều kiện đạt:
 *  1. release_open        – phiên bản chưa được chốt
 *  2. has_items           – có ít nhất một công việc
 *  3. same_project        – mọi công việc thuộc đúng dự án của phiên bản
 *  4. required_complete   – công việc bắt buộc đã hoàn thành (Bug: Verified/Closed; Task/Cải tiến: Hoàn thành/Closed)
 *  5. dependencies_done   – mọi phụ thuộc của công việc bắt buộc đã hoàn thành, KỂ CẢ phụ thuộc ngoài phiên bản
 *  6. blockers_retested   – mỗi lỗi Blocker có kết quả Passed MỚI NHẤT đúng trên bản sửa HIỆN TẠI (Resolved != Passed)
 */
final class ReleaseReadinessService
{
    public function __construct(private ReleaseRepository $releases)
    {
    }

    /** @return array<string,mixed> */
    public function evaluate(int $releaseId): array
    {
        $release = $this->releases->find($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
        $items = $this->releases->items($releaseId);
        $deps = $this->releases->incompleteDependencies($releaseId);

        $conditions = [];

        $open = $release['status'] === 'planned';
        $conditions[] = $this->cond('release_open', 'Phiên bản chưa được chốt', $open,
            $open ? 'Phiên bản đang ở trạng thái dự kiến.' : 'Phiên bản đã được chốt, không thể chốt lại.', []);

        $has = count($items) > 0;
        $conditions[] = $this->cond('has_items', 'Có công việc trong phiên bản', $has,
            $has ? count($items) . ' công việc đã được gắn.' : 'Chưa gắn công việc nào.', []);

        $foreign = array_values(array_filter($items, static fn ($i) => (int) $i['project_id'] !== (int) $release['project_id']));
        $conditions[] = $this->cond('same_project', 'Mọi công việc thuộc đúng dự án', !$foreign,
            $foreign ? 'Có công việc thuộc dự án khác.' : 'Tất cả công việc cùng dự án.',
            array_map(fn ($i) => $this->item($i, 'Thuộc dự án khác'), $foreign));

        $reqIncomplete = [];
        foreach ($items as $i) {
            if ((int) $i['is_required'] === 1 && !Catalog::isComplete((string) $i['type'], (string) $i['status'])) {
                $reqIncomplete[] = $this->item($i, 'Đang ở trạng thái "' . Catalog::statusLabel((string) $i['status'], (string) $i['type']) . '"');
            }
        }
        $conditions[] = $this->cond('required_complete', 'Công việc bắt buộc đã hoàn thành', !$reqIncomplete,
            $reqIncomplete ? count($reqIncomplete) . ' công việc bắt buộc chưa hoàn thành.' : 'Mọi công việc bắt buộc đã hoàn thành.', $reqIncomplete);

        $depItems = [];
        foreach ($deps as $d) {
            $depItems[] = [
                'id' => (int) $d['dep_id'], 'code' => $d['dep_code'], 'title' => $d['dep_title'],
                'status' => $d['dep_status'],
                'reason' => $d['item_code'] . ' phụ thuộc ' . $d['dep_code'] . ' (' . Catalog::statusLabel((string) $d['dep_status'], (string) $d['dep_type']) . ')'
                    . ((int) $d['in_release'] === 1 ? '' : ' – nằm ngoài phiên bản'),
                'path' => '/issues/' . (int) $d['dep_id'],
            ];
        }
        $conditions[] = $this->cond('dependencies_done', 'Phụ thuộc cần thiết đã hoàn thành', !$depItems,
            $depItems ? count($depItems) . ' phụ thuộc chưa hoàn thành.' : 'Không có phụ thuộc nào đang chặn.', $depItems);

        $blockers = [];
        foreach ($items as $i) {
            if ($i['type'] !== 'bug' || $i['severity'] !== 'blocker') {
                continue;
            }
            $reason = $this->blockerProblem($i);
            if ($reason !== null) {
                $blockers[] = $this->item($i, $reason);
            }
        }
        $conditions[] = $this->cond('blockers_retested', 'Lỗi Blocker đã kiểm thử lại thành công trên bản sửa hiện tại', !$blockers,
            $blockers ? count($blockers) . ' lỗi Blocker chưa được kiểm thử lại hợp lệ.' : 'Mọi lỗi Blocker đã được kiểm thử lại thành công.', $blockers);

        $ready = true;
        foreach ($conditions as $c) {
            $ready = $ready && $c['passed'];
        }
        $total = count($items);
        $done = count(array_filter($items, static fn ($i) => Catalog::isComplete((string) $i['type'], (string) $i['status'])));
        return [
            'release_id' => $releaseId,
            'project_id' => (int) $release['project_id'],
            'ready' => $ready,
            'conditions' => $conditions,
            'progress' => ['total' => $total, 'done' => $done, 'percent' => $total ? (int) round($done * 100 / $total) : 0],
        ];
    }

    /** Lý do một lỗi Blocker chưa đạt, hoặc null nếu hợp lệ. @param array<string,mixed> $i */
    private function blockerProblem(array $i): ?string
    {
        if ((int) $i['fix_revision'] < 1 || $i['last_test_id'] === null) {
            return $i['status'] === 'resolved' ? 'Đã sửa (Resolved) nhưng chưa có kết quả kiểm thử – Resolved không đồng nghĩa Passed' : 'Chưa có bản sửa được kiểm thử';
        }
        if ($i['last_test_result'] !== 'passed') {
            return 'Kết quả kiểm thử mới nhất là Failed';
        }
        if ((int) $i['last_test_revision'] !== (int) $i['fix_revision']) {
            return 'Kết quả Passed thuộc bản sửa #' . $i['last_test_revision'] . ', bản sửa hiện tại là #' . $i['fix_revision'] . ' – cần kiểm thử lại';
        }
        if (!in_array($i['status'], ['verified', 'closed'], true)) {
            return 'Trạng thái "' . Catalog::statusLabel((string) $i['status'], 'bug') . '" chưa được xác nhận';
        }
        return null;
    }

    /** @param array<string,mixed> $i @return array<string,mixed> */
    private function item(array $i, string $reason): array
    {
        return [
            'id' => (int) $i['issue_id'], 'code' => $i['code'], 'title' => $i['title'], 'status' => $i['status'],
            'severity' => $i['severity'], 'reason' => $reason, 'path' => '/issues/' . (int) $i['issue_id'],
        ];
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,mixed> */
    private function cond(string $key, string $label, bool $passed, string $message, array $items): array
    {
        return compact('key', 'label', 'passed', 'message', 'items');
    }
}
