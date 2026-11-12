<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use PDO;
use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\Database;
use ReleaseHub\Core\NotFoundException;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Domain\Catalog;
use ReleaseHub\Repositories\DependencyRepository;
use ReleaseHub\Repositories\HistoryRepository;
use ReleaseHub\Repositories\IssueRepository;
use ReleaseHub\Repositories\MemberRepository;
use ReleaseHub\Repositories\ProjectRepository;
use ReleaseHub\Support\Validate;

final class ProjectService
{
    public function __construct(
        private PDO $pdo,
        private ProjectRepository $projects,
        private MemberRepository $members,
        private IssueRepository $issues,
        private DependencyRepository $deps,
        private HistoryRepository $history,
        private AuthorizationService $auth
    ) {
    }

    /** @param array<string,mixed> $actor @param array<string,mixed> $filters @return array<int,array<string,mixed>> */
    public function listVisible(array $actor, array $filters = []): array
    {
        $ids = $this->auth->visibleProjectIds($actor);
        return $this->projects->listVisible($ids === null ? null : (int) $actor['id'], $filters);
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function overview(array $actor, int $projectId): array
    {
        $role = $this->auth->authorize($actor, 'project.view', $projectId);
        $project = $this->projects->find($projectId) ?? throw new NotFoundException('Không tìm thấy dự án.');
        $counts = $this->issues->countByStatus($projectId);
        $total = array_sum($counts);
        $board = $this->issues->board($projectId);
        $done = count(array_filter($board, static fn ($i) => Catalog::isComplete((string) $i['type'], (string) $i['status'])));
        return [
            'project' => $project,
            'role' => $role,
            'status_counts' => $counts,
            'total' => $total,
            'done' => $done,
            'percent' => $total ? (int) round($done * 100 / $total) : 0,
            'blockers' => $this->deps->blockers($projectId),
            'can' => [
                'manage' => $this->auth->can($actor, 'project.manage', $projectId),
                'members' => $this->auth->can($actor, 'member.manage', $projectId),
                'create_issue' => $this->auth->can($actor, 'issue.create', $projectId),
                'plan' => $this->auth->can($actor, 'release.plan', $projectId),
            ],
        ];
    }

    /** Dữ liệu gọn cho phần đầu trang dự án (tên, vai trò, quyền). @param array<string,mixed> $actor @return array<string,mixed> */
    public function header(array $actor, int $projectId): array
    {
        $role = $this->auth->authorize($actor, 'project.view', $projectId);
        return [
            'project' => $this->projects->find($projectId) ?? throw new NotFoundException('Không tìm thấy dự án.'),
            'role' => $role,
            'can' => [
                'manage' => $this->auth->can($actor, 'project.manage', $projectId),
                'members' => $this->auth->can($actor, 'member.manage', $projectId),
                'create_issue' => $this->auth->can($actor, 'issue.create', $projectId),
                'plan' => $this->auth->can($actor, 'release.plan', $projectId),
                'finalize' => $this->auth->can($actor, 'release.finalize', $projectId),
                'assign' => $this->auth->can($actor, 'issue.assign', $projectId),
            ],
        ];
    }

    /** @param array<string,mixed> $actor @return array{rows:array<int,array<string,mixed>>,total:int} */
    public function history(array $actor, int $projectId, int $page, int $perPage = 30): array
    {
        $this->auth->authorize($actor, 'project.view', $projectId);
        return $this->history->forProject($projectId, $page, $perPage);
    }

    /** @param array<string,mixed> $actor @param array<string,mixed> $in @return array<string,mixed> */
    public function create(array $actor, array $in, string $ip = ''): array
    {
        if (empty($actor['is_admin'])) {
            // Người dùng thường chỉ được tạo dự án nếu đang là Project Manager của ít nhất một dự án khác.
            $isPm = (bool) $this->pdo->query("SELECT 1 FROM project_members WHERE role_code = 'project_manager' AND user_id = " . (int) $actor['id'] . ' LIMIT 1')->fetchColumn();
            if (!$isPm) {
                throw new \ReleaseHub\Core\ForbiddenException('Chỉ System Admin hoặc Project Manager mới được tạo dự án mới.');
            }
        }
        $v = new Validate();
        $code = $v->str($in, 'code', 'Mã dự án', 2, 16, true);
        if ($code !== null && !preg_match('/^[A-Za-z][A-Za-z0-9]{1,15}$/', $code)) {
            $v->fail('code', 'Mã dự án gồm 2–16 chữ/số, bắt đầu bằng chữ (ví dụ VOCAB).');
        }
        $data = $this->fields($v, $in);
        $v->throwIfFailed();
        $code = strtoupper((string) $code);

        return Database::transaction(function (PDO $pdo) use ($actor, $code, $data, $ip) {
            if ($this->projects->findByCode($code)) {
                throw new ValidationException(['code' => 'Mã dự án đã tồn tại.']);
            }
            $id = $this->projects->insert($data + ['code' => $code, 'owner_id' => (int) $actor['id']]);
            $this->members->add($id, (int) $actor['id'], 'project_manager');
            $this->history->audit($id, (int) $actor['id'], 'project.create', 'project', $id, ['code' => $code], $ip);
            return $this->projects->find($id) ?? [];
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor @param array<string,mixed> $in @return array<string,mixed> */
    public function update(array $actor, int $projectId, array $in, string $ip = ''): array
    {
        $this->auth->authorize($actor, 'project.manage', $projectId);
        $v = new Validate();
        $data = $this->fields($v, $in);
        $owner = $v->intId($in, 'owner_id', 'Người phụ trách');
        if ($owner !== null && $this->members->roleOf($projectId, $owner) === null) {
            $v->fail('owner_id', 'Người phụ trách phải là thành viên của dự án.');
        }
        $v->throwIfFailed();
        return Database::transaction(function (PDO $pdo) use ($actor, $projectId, $data, $owner, $ip) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->projects->find($projectId) ?? throw new NotFoundException('Không tìm thấy dự án.');
            $this->projects->update($projectId, $data + ['owner_id' => $owner ?? $cur['owner_id']]);
            $this->history->audit($projectId, (int) $actor['id'], 'project.update', 'project', $projectId, [], $ip);
            return $this->projects->find($projectId) ?? [];
        }, $this->pdo);
    }

    /** Lưu trữ thay vì xóa để bảo toàn lịch sử. @param array<string,mixed> $actor */
    public function setArchived(array $actor, int $projectId, bool $archived, string $ip = ''): void
    {
        $this->auth->authorize($actor, 'project.manage', $projectId);
        Database::transaction(function (PDO $pdo) use ($actor, $projectId, $archived, $ip) {
            Database::lockProject($pdo, $projectId);
            $this->projects->setArchived($projectId, $archived);
            $this->history->audit($projectId, (int) $actor['id'], $archived ? 'project.archive' : 'project.unarchive', 'project', $projectId, [], $ip);
        }, $this->pdo);
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    private function fields(Validate $v, array $in): array
    {
        $name = $v->str($in, 'name', 'Tên dự án', 3, 150, true);
        $desc = $v->str($in, 'description', 'Mô tả', 0, 5000);
        $color = $v->str($in, 'color', 'Màu', 0, 7) ?? '#4f46e5';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $v->fail('color', 'Màu phải có dạng #RRGGBB.');
            $color = '#4f46e5';
        }
        $icon = $v->str($in, 'icon', 'Biểu tượng', 0, 40) ?? 'bi-kanban';
        if (!preg_match('/^bi-[a-z0-9-]+$/', $icon)) {
            $v->fail('icon', 'Biểu tượng không hợp lệ.');
            $icon = 'bi-kanban';
        }
        return ['name' => $name, 'description' => $desc, 'color' => $color, 'icon' => $icon, 'owner_id' => null];
    }
}
