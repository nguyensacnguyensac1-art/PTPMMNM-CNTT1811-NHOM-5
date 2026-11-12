<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use PDO;
use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\Database;
use ReleaseHub\Core\ForbiddenException;
use ReleaseHub\Core\HttpException;
use ReleaseHub\Core\NotFoundException;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Domain\Catalog;
use ReleaseHub\Domain\WorkflowRules;
use ReleaseHub\Repositories\HistoryRepository;
use ReleaseHub\Repositories\IssueRepository;
use ReleaseHub\Repositories\TestResultRepository;
use ReleaseHub\Support\Validate;

/** Chuyển trạng thái, ghi nhận bản sửa và kết quả kiểm thử. Mọi quy tắc được kiểm tra ở backend. */
final class IssueWorkflowService
{
    public function __construct(
        private PDO $pdo,
        private IssueRepository $issues,
        private TestResultRepository $tests,
        private HistoryRepository $history,
        private AuthorizationService $auth
    ) {
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function transition(array $actor, int $issueId, string $to, mixed $expectedVersion, ?string $note = null, string $ip = ''): array
    {
        $issue = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
        $projectId = (int) $issue['project_id'];
        $role = $this->auth->authorize($actor, 'issue.transition', $projectId);
        $expected = $this->expected($expectedVersion);

        return Database::transaction(function (PDO $pdo) use ($actor, $issueId, $projectId, $role, $to, $expected, $note, $ip) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
            if ((int) $cur['version'] !== $expected) {
                throw new ConflictException('Công việc vừa được người khác cập nhật. Hãy tải lại trước khi đổi trạng thái.', 'version_conflict', ['current' => $cur]);
            }
            $type = (string) $cur['type'];
            $from = (string) $cur['status'];
            $rule = WorkflowRules::find($type, $from, $to);
            if ($rule === null) {
                throw new HttpException(422, 'Không thể chuyển từ "' . Catalog::statusLabel($from, $type) . '" sang "' . Catalog::statusLabel($to, $type) . '".', 'invalid_transition');
            }
            if ($rule['via'] === 'fix') {
                throw new HttpException(422, 'Chuyển sang "Đã sửa" phải dùng chức năng Ghi nhận bản sửa (có mã commit/PR).', 'use_fix_flow');
            }
            if ($rule['via'] === 'test') {
                throw new HttpException(422, 'Trạng thái này chỉ đạt được bằng cách ghi kết quả kiểm thử.', 'use_test_flow');
            }
            if ($role !== AuthorizationService::ADMIN_ROLE && !in_array($role, $rule['roles'], true)) {
                throw new ForbiddenException('Vai trò ' . (Catalog::ROLES[$role] ?? $role) . ' không được phép chuyển trạng thái này.');
            }
            $fields = ['status' => $to];
            if ($to === 'in_progress' && $cur['assignee_id'] === null) {
                $fields['assignee_id'] = (int) $actor['id'];
            }
            if ($this->issues->updateVersioned($issueId, $expected, $fields) !== 1) {
                throw new ConflictException('Không thể cập nhật do xung đột phiên bản.', 'version_conflict');
            }
            $this->history->add($projectId, $issueId, (int) $actor['id'], 'status_changed', ['status' => [$from, $to]] + ($note ? ['note' => $note] : []));
            $this->history->audit($projectId, (int) $actor['id'], 'issue.transition', 'issue', $issueId, ['from' => $from, 'to' => $to], $ip);
            return $this->issues->find($issueId) ?? [];
        }, $this->pdo);
    }

    /**
     * Ghi nhận bản sửa: tăng fix_revision (đây là thứ khiến kết quả kiểm thử cũ hết hiệu lực)
     * và chuyển In Progress -> Resolved. Sửa metadata KHÔNG đổi fix_revision.
     *
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function recordFix(array $actor, int $issueId, string $fixRef, mixed $expectedVersion, ?string $note = null, string $ip = ''): array
    {
        $issue = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
        $projectId = (int) $issue['project_id'];
        $this->auth->authorize($actor, 'issue.fix', $projectId);
        $expected = $this->expected($expectedVersion);
        $v = new Validate();
        $ref = $v->str(['fix_ref' => $fixRef], 'fix_ref', 'Mã commit / liên kết PR', 4, 500, true);
        if ($ref !== null && !preg_match('/^[0-9a-f]{7,40}$/i', $ref)) {
            $parts = parse_url($ref);
            if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || preg_match('/\s/', $ref)) {
                $v->fail('fix_ref', 'Nhập mã commit (7–40 ký tự hex) hoặc đường dẫn http(s) tới commit/PR.');
            }
        }
        $v->throwIfFailed();

        return Database::transaction(function (PDO $pdo) use ($actor, $issueId, $projectId, $ref, $expected, $note, $ip) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
            if ((int) $cur['version'] !== $expected) {
                throw new ConflictException('Công việc vừa được người khác cập nhật. Hãy tải lại.', 'version_conflict', ['current' => $cur]);
            }
            if ($cur['type'] !== 'bug') {
                throw new HttpException(422, 'Chỉ lỗi (Bug) mới có bản sửa cần kiểm thử.', 'not_a_bug');
            }
            if ($cur['status'] !== 'in_progress') {
                throw new HttpException(422, 'Chỉ ghi nhận bản sửa khi lỗi đang ở trạng thái "Đang xử lý".', 'invalid_transition');
            }
            $rev = (int) $cur['fix_revision'] + 1;
            $this->issues->updateVersioned($issueId, $expected, ['status' => 'resolved', 'fix_ref' => $ref, 'fix_revision' => $rev]);
            $this->history->add($projectId, $issueId, (int) $actor['id'], 'fix_recorded', ['fix_ref' => $ref, 'fix_revision' => $rev, 'status' => ['in_progress', 'resolved']] + ($note ? ['note' => $note] : []));
            $this->history->audit($projectId, (int) $actor['id'], 'issue.fix', 'issue', $issueId, ['fix_revision' => $rev], $ip);
            return $this->issues->find($issueId) ?? [];
        }, $this->pdo);
    }

    /**
     * Ghi kết quả kiểm thử gắn với BẢN SỬA HIỆN TẠI (fix_revision). Nếu bản sửa đã đổi từ lúc tester mở màn hình
     * thì từ chối (409) để tránh xác nhận nhầm bản sửa cũ.
     *
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function recordTest(array $actor, int $issueId, string $result, mixed $testedRevision, ?string $note, string $ip = ''): array
    {
        $issue = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
        $projectId = (int) $issue['project_id'];
        $this->auth->authorize($actor, 'test.record', $projectId);
        $v = new Validate();
        $res = $v->enum(['result' => $result], 'result', 'Kết quả', ['passed' => 'Passed', 'failed' => 'Failed'], null, true);
        $noteText = $v->str(['note' => $note ?? ''], 'note', 'Ghi chú kiểm thử', $res === 'failed' ? 3 : 0, 5000, $res === 'failed');
        if (!is_numeric($testedRevision) || (int) $testedRevision < 1) {
            $v->fail('fix_revision', 'Thiếu bản sửa được kiểm tra (fix_revision).');
        }
        $v->throwIfFailed();

        return Database::transaction(function (PDO $pdo) use ($actor, $issueId, $projectId, $res, $noteText, $testedRevision, $ip) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
            if ($cur['type'] !== 'bug') {
                throw new HttpException(422, 'Kết quả kiểm thử chỉ áp dụng cho lỗi (Bug).', 'not_a_bug');
            }
            if (!in_array($cur['status'], ['resolved', 'verified', 'closed'], true) || (int) $cur['fix_revision'] < 1) {
                throw new HttpException(422, 'Lỗi chưa có bản sửa để kiểm thử.', 'no_fix_to_test');
            }
            if ((int) $testedRevision !== (int) $cur['fix_revision']) {
                throw new ConflictException(
                    'Bản sửa đã thay đổi (hiện là bản #' . $cur['fix_revision'] . ', bạn kiểm tra bản #' . (int) $testedRevision . '). Hãy tải lại và kiểm thử bản mới.',
                    'fix_changed',
                    ['current' => $cur]
                );
            }
            $id = $this->tests->add($projectId, $issueId, (int) $actor['id'], (string) $res, (int) $cur['fix_revision'], $cur['fix_ref'], $noteText);
            $from = (string) $cur['status'];
            $to = $from;
            if ($res === 'passed' && $from === 'resolved') {
                $to = 'verified';
            } elseif ($res === 'failed' && $from !== 'reopened') {
                $to = 'reopened';
            }
            if ($to !== $from) {
                $this->issues->updateVersioned($issueId, (int) $cur['version'], ['status' => $to]);
            }
            $this->history->add($projectId, $issueId, (int) $actor['id'], 'test_recorded', [
                'result' => $res, 'fix_revision' => (int) $cur['fix_revision'], 'status' => [$from, $to],
            ] + ($noteText ? ['note' => $noteText] : []));
            $this->history->audit($projectId, (int) $actor['id'], 'issue.test', 'issue', $issueId, ['result' => $res, 'test_id' => $id], $ip);
            return ['test_id' => $id, 'issue' => $this->issues->find($issueId) ?? []];
        }, $this->pdo);
    }

    private function expected(mixed $v): int
    {
        if (!is_numeric($v) || (int) $v < 1) {
            throw new ValidationException(['expected_version' => 'Thiếu số phiên bản (expected_version) của công việc.']);
        }
        return (int) $v;
    }
}
