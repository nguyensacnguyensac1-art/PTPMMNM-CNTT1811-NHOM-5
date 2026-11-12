<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use PDO;
use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\Database;
use ReleaseHub\Core\ForbiddenException;
use ReleaseHub\Core\NotFoundException;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Domain\Catalog;
use ReleaseHub\Domain\WorkflowRules;
use ReleaseHub\Repositories\CommentRepository;
use ReleaseHub\Repositories\DependencyRepository;
use ReleaseHub\Repositories\HistoryRepository;
use ReleaseHub\Repositories\IssueRepository;
use ReleaseHub\Repositories\MemberRepository;
use ReleaseHub\Repositories\ProjectRepository;
use ReleaseHub\Repositories\ReleaseRepository;
use ReleaseHub\Repositories\TestResultRepository;
use ReleaseHub\Support\Validate;

final class IssueService
{
    /** Trường nội dung được phép sửa qua form (trạng thái và bản sửa đi theo luồng riêng). */
    private const EDITABLE = [
        'title', 'description', 'priority', 'severity', 'due_date', 'environment',
        'steps_to_reproduce', 'expected_result', 'actual_result', 'commit_url', 'pr_url', 'assignee_id',
    ];

    public function __construct(
        private PDO $pdo,
        private IssueRepository $issues,
        private ProjectRepository $projects,
        private MemberRepository $members,
        private CommentRepository $comments,
        private HistoryRepository $history,
        private TestResultRepository $tests,
        private DependencyRepository $deps,
        private ReleaseRepository $releases,
        private AuthorizationService $auth
    ) {
    }

    /**
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public function create(array $actor, int $projectId, array $in, string $ip = ''): array
    {
        $project = $this->projects->find($projectId) ?? throw new NotFoundException('Không tìm thấy dự án.');
        $role = $this->auth->authorize($actor, 'issue.create', $projectId);
        if ($project['archived_at'] !== null) {
            throw new ConflictException('Dự án đã được lưu trữ, không thể tạo thêm công việc.', 'project_archived');
        }
        $v = new Validate();
        $type = $v->enum($in, 'type', 'Loại', Catalog::TYPES, null, true) ?? 'task';
        $fields = $this->normalize($v, $in, $type, true);
        $fields['type'] = $type;
        $v->throwIfFailed();
        if ($fields['assignee_id'] !== null) {
            $this->auth->authorize($actor, 'issue.assign', $projectId);
            $this->assertAssignable($projectId, $fields['assignee_id']);
        }

        return Database::transaction(function (PDO $pdo) use ($actor, $projectId, $project, $fields, $ip) {
            Database::lockProject($pdo, $projectId);
            $number = $this->projects->nextIssueNumber($projectId);
            $id = $this->issues->insert($fields + [
                'project_id' => $projectId,
                'number' => $number,
                'code' => $project['code'] . '-' . $number,
                'reporter_id' => (int) $actor['id'],
            ]);
            $this->history->add($projectId, $id, (int) $actor['id'], 'created', ['type' => $fields['type'], 'title' => $fields['title']]);
            $this->history->audit($projectId, (int) $actor['id'], 'issue.create', 'issue', $id, ['code' => $project['code'] . '-' . $number], $ip);
            return $this->issues->find($id) ?? [];
        }, $this->pdo);
    }

    /**
     * Cập nhật có khóa lạc quan: UPDATE ... WHERE id = ? AND version = ?.
     * Version không khớp => HTTP 409, KHÔNG ghi đè dữ liệu của người lưu trước.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public function update(array $actor, int $issueId, array $in, mixed $expectedVersion, string $ip = ''): array
    {
        $issue = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
        $projectId = (int) $issue['project_id'];
        $this->auth->authorize($actor, 'issue.edit', $projectId);

        $v = new Validate();
        $expected = $this->expectedVersion($expectedVersion);
        $fields = $this->normalize($v, $in, (string) $issue['type'], false);
        $v->throwIfFailed();

        return Database::transaction(function (PDO $pdo) use ($actor, $issueId, $projectId, $fields, $expected, $ip) {
            Database::lockProject($pdo, $projectId);
            $current = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
            if ((int) $current['version'] !== $expected) {
                throw $this->conflict($current, $fields, $expected);
            }
            $changes = [];
            foreach ($fields as $k => $val) {
                $old = $current[$k] ?? null;
                if ((string) ($old ?? '') !== (string) ($val ?? '')) {
                    $changes[$k] = [$old, $val];
                }
            }
            if (isset($changes['assignee_id'])) {
                $this->auth->authorize($actor, 'issue.assign', $projectId);
                if ($fields['assignee_id'] !== null) {
                    $this->assertAssignable($projectId, (int) $fields['assignee_id']);
                }
            }
            if (!$changes) {
                return $current + ['unchanged' => true];
            }
            $set = array_map(static fn ($c) => $c[1], $changes);
            $affected = $this->issues->updateVersioned($issueId, $expected, $set);
            if ($affected !== 1) {
                throw $this->conflict($this->issues->find($issueId) ?? $current, $fields, $expected);
            }
            $this->history->add($projectId, $issueId, (int) $actor['id'], 'updated', $changes);
            $this->history->audit($projectId, (int) $actor['id'], 'issue.update', 'issue', $issueId, ['fields' => array_keys($changes)], $ip);
            return $this->issues->find($issueId) ?? [];
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function addComment(array $actor, int $issueId, string $body, string $ip = ''): array
    {
        $issue = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
        $projectId = (int) $issue['project_id'];
        $this->auth->authorize($actor, 'comment.create', $projectId);
        $v = new Validate();
        $text = $v->str(['body' => $body], 'body', 'Nội dung bình luận', 1, 5000, true);
        $v->throwIfFailed();
        return Database::transaction(function (PDO $pdo) use ($actor, $issueId, $projectId, $text, $ip) {
            Database::lockProject($pdo, $projectId);
            $id = $this->comments->add($projectId, $issueId, (int) $actor['id'], (string) $text);
            $this->history->add($projectId, $issueId, (int) $actor['id'], 'commented');
            $this->history->audit($projectId, (int) $actor['id'], 'comment.create', 'issue', $issueId, ['comment_id' => $id], $ip);
            return ['id' => $id];
        }, $this->pdo);
    }

    /**
     * Chi tiết đầy đủ cho màn hình lỗi.
     *
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function detail(array $actor, int $issueId): array
    {
        $issue = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
        $projectId = (int) $issue['project_id'];
        $role = $this->auth->authorize($actor, 'project.view', $projectId);
        $isAdmin = $role === AuthorizationService::ADMIN_ROLE;
        $latest = $this->tests->latest($issueId);
        $validPass = $latest !== null && $latest['result'] === 'passed' && (int) $latest['fix_revision'] === (int) $issue['fix_revision'] && (int) $issue['fix_revision'] > 0;
        return [
            'issue' => $issue,
            'project' => $this->projects->find($projectId),
            'role' => $role,
            'can' => [
                'edit' => $this->auth->can($actor, 'issue.edit', $projectId),
                'assign' => $this->auth->can($actor, 'issue.assign', $projectId),
                'comment' => $this->auth->can($actor, 'comment.create', $projectId),
                'fix' => $this->auth->can($actor, 'issue.fix', $projectId) && $issue['type'] === 'bug' && $issue['status'] === 'in_progress',
                'test' => $this->auth->can($actor, 'test.record', $projectId) && $issue['type'] === 'bug'
                    && in_array($issue['status'], ['resolved', 'verified', 'closed'], true) && (int) $issue['fix_revision'] > 0,
                'dependency' => $this->auth->can($actor, 'dependency.manage', $projectId),
            ],
            'transitions' => array_values(array_filter(
                WorkflowRules::allowedFor((string) $issue['type'], (string) $issue['status'], $role, $isAdmin),
                fn ($r) => $this->auth->can($actor, 'issue.transition', $projectId)
            )),
            'comments' => $this->comments->forIssue($issueId),
            'tests' => $this->tests->forIssue($issueId),
            'history' => $this->history->forIssue($issueId),
            'depends_on' => $this->deps->dependenciesOf($issueId),
            'blocks' => $this->deps->dependentsOf($issueId),
            'releases' => $this->releases->releasesOfIssue($issueId),
            'has_valid_pass' => $validPass,
            'members' => $this->members->list($projectId),
        ];
    }

    /**
     * Danh sách có lọc / sắp xếp / phân trang; luôn giới hạn trong các dự án người dùng được xem.
     *
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function search(array $actor, ?int $projectId, array $filters, string $sort, string $dir, int $page, int $perPage = 20): array
    {
        if ($projectId !== null) {
            $this->auth->authorize($actor, 'project.view', $projectId);
            $ids = [$projectId];
        } else {
            $ids = $this->auth->visibleProjectIds($actor) ?? $this->projects->allIds();
        }
        $res = $this->issues->search($ids, $filters, $sort, $dir, $page, $perPage);
        $perPage = max(1, min(100, $perPage));
        return $res + ['page' => max(1, $page), 'per_page' => $perPage, 'pages' => max(1, (int) ceil($res['total'] / $perPage))];
    }

    /** @param array<string,mixed> $actor @return array<int,array<string,mixed>> */
    public function board(array $actor, int $projectId): array
    {
        $this->auth->authorize($actor, 'project.view', $projectId);
        return $this->issues->board($projectId);
    }

    // ------------------------------------------------------------------ nội bộ

    /**
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    private function normalize(Validate $v, array $in, string $type, bool $full): array
    {
        $out = [];
        $has = static fn (string $k): bool => $full || array_key_exists($k, $in);
        if ($has('title')) {
            $out['title'] = $v->str($in, 'title', 'Tiêu đề', 3, 200, true);
        }
        if ($has('description')) {
            $out['description'] = $v->str($in, 'description', 'Mô tả', 0, 10000);
        }
        if ($has('priority')) {
            $out['priority'] = $v->enum($in, 'priority', 'Mức ưu tiên', Catalog::PRIORITIES, 'medium');
        }
        if ($has('severity')) {
            $sev = $v->enum($in, 'severity', 'Mức nghiêm trọng', Catalog::SEVERITIES, null, $type === 'bug');
            $out['severity'] = $type === 'bug' ? $sev : null;
        }
        if ($has('due_date')) {
            $out['due_date'] = $v->date($in, 'due_date', 'Thời hạn');
        }
        if ($has('environment')) {
            $out['environment'] = $v->str($in, 'environment', 'Môi trường', 0, 255);
        }
        foreach (['steps_to_reproduce' => 'Các bước tái hiện', 'expected_result' => 'Kết quả mong đợi', 'actual_result' => 'Kết quả thực tế'] as $k => $label) {
            if ($has($k)) {
                $val = $v->str($in, $k, $label, 0, 5000, $type === 'bug' && $k !== 'actual_result');
                $out[$k] = $type === 'bug' ? $val : null;
            }
        }
        if ($has('commit_url')) {
            $out['commit_url'] = $v->url($in, 'commit_url', 'Liên kết commit');
        }
        if ($has('pr_url')) {
            $out['pr_url'] = $v->url($in, 'pr_url', 'Liên kết PR');
        }
        if ($has('assignee_id')) {
            $out['assignee_id'] = $v->intId($in, 'assignee_id', 'Người xử lý');
        }
        return array_intersect_key($out, array_flip(self::EDITABLE));
    }

    private function assertAssignable(int $projectId, int $userId): void
    {
        $role = $this->members->roleOf($projectId, $userId);
        if ($role === null || $role === 'viewer') {
            throw new ValidationException(['assignee_id' => 'Người xử lý phải là thành viên (không phải Viewer) của dự án này.']);
        }
    }

    private function expectedVersion(mixed $v): int
    {
        if (!is_numeric($v) || (int) $v < 1) {
            throw new ValidationException(['expected_version' => 'Thiếu số phiên bản (expected_version) của công việc đang sửa.']);
        }
        return (int) $v;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $draft
     */
    private function conflict(array $current, array $draft, int $expected): ConflictException
    {
        $diff = [];
        foreach ($draft as $k => $val) {
            if ((string) ($current[$k] ?? '') !== (string) ($val ?? '')) {
                $diff[] = $k;
            }
        }
        return new ConflictException(
            'Công việc đã được người khác cập nhật (phiên bản ' . $current['version'] . ' so với bản bạn mở: ' . $expected . '). Thay đổi của bạn chưa được lưu.',
            'version_conflict',
            ['current' => $current, 'draft' => $draft, 'expected_version' => $expected, 'differing_fields' => $diff]
        );
    }
}
