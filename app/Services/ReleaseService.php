<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use PDO;
use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\Database;
use ReleaseHub\Core\NotFoundException;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Repositories\HistoryRepository;
use ReleaseHub\Repositories\IssueRepository;
use ReleaseHub\Repositories\MemberRepository;
use ReleaseHub\Repositories\ProjectRepository;
use ReleaseHub\Repositories\ReleaseRepository;
use ReleaseHub\Support\Validate;

/** Lập kế hoạch phiên bản: tạo/sửa, gắn/gỡ công việc. Phiên bản đã chốt không thể thay đổi. */
final class ReleaseService
{
    public function __construct(
        private PDO $pdo,
        private ReleaseRepository $releases,
        private IssueRepository $issues,
        private ProjectRepository $projects,
        private MemberRepository $members,
        private HistoryRepository $history,
        private ReleaseReadinessService $readiness,
        private ReleaseSnapshotService $snapshots,
        private AuthorizationService $auth
    ) {
    }

    /** @param array<string,mixed> $actor @param array<string,mixed> $in @return array<string,mixed> */
    public function create(array $actor, int $projectId, array $in, string $ip = ''): array
    {
        $project = $this->projects->find($projectId) ?? throw new NotFoundException('Không tìm thấy dự án.');
        $this->auth->authorize($actor, 'release.plan', $projectId);
        if ($project['archived_at'] !== null) {
            throw new ConflictException('Dự án đã được lưu trữ.', 'project_archived');
        }
        $data = $this->validated($in, $projectId);
        return Database::transaction(function (PDO $pdo) use ($actor, $projectId, $data, $ip) {
            Database::lockProject($pdo, $projectId);
            $dup = $pdo->prepare('SELECT 1 FROM releases WHERE project_id = ? AND name = ?');
            $dup->execute([$projectId, $data['name']]);
            if ($dup->fetchColumn()) {
                throw new ValidationException(['name' => 'Mã phiên bản này đã tồn tại trong dự án.']);
            }
            $id = $this->releases->insert($data + ['project_id' => $projectId, 'created_by' => (int) $actor['id']]);
            $this->history->audit($projectId, (int) $actor['id'], 'release.create', 'release', $id, ['name' => $data['name']], $ip);
            return $this->releases->find($id) ?? [];
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor @param array<string,mixed> $in @return array<string,mixed> */
    public function update(array $actor, int $releaseId, array $in, string $ip = ''): array
    {
        $rel = $this->releases->find($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
        $projectId = (int) $rel['project_id'];
        $this->auth->authorize($actor, 'release.plan', $projectId);
        $data = $this->validated($in, $projectId);
        return Database::transaction(function (PDO $pdo) use ($actor, $releaseId, $projectId, $data, $ip) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->releases->findForUpdate($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
            $this->assertPlanned($cur);
            $dup = $pdo->prepare('SELECT 1 FROM releases WHERE project_id = ? AND name = ? AND id <> ?');
            $dup->execute([$projectId, $data['name'], $releaseId]);
            if ($dup->fetchColumn()) {
                throw new ValidationException(['name' => 'Mã phiên bản này đã tồn tại trong dự án.']);
            }
            $this->releases->update($releaseId, $data);
            $this->history->audit($projectId, (int) $actor['id'], 'release.update', 'release', $releaseId, [], $ip);
            return $this->releases->find($releaseId) ?? [];
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor */
    public function attach(array $actor, int $releaseId, int $issueId, bool $required = true, string $ip = ''): void
    {
        $rel = $this->releases->find($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
        $projectId = (int) $rel['project_id'];
        $this->auth->authorize($actor, 'release.plan', $projectId);
        $issue = $this->issues->find($issueId) ?? throw new ValidationException(['issue_id' => 'Công việc không tồn tại.']);
        if ((int) $issue['project_id'] !== $projectId) {
            throw new ValidationException(['issue_id' => 'Chỉ được gắn công việc cùng dự án với phiên bản.'], 'Công việc thuộc dự án khác.');
        }
        Database::transaction(function (PDO $pdo) use ($actor, $releaseId, $projectId, $issue, $required, $ip) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->releases->findForUpdate($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
            $this->assertPlanned($cur);
            $other = $this->releases->otherPlannedRelease((int) $issue['id'], $releaseId);
            if ($other) {
                throw new ConflictException('Công việc đã nằm trong phiên bản dự kiến khác (' . $other['name'] . ').', 'already_in_release');
            }
            $exists = $pdo->prepare('SELECT 1 FROM release_items WHERE release_id = ? AND issue_id = ?');
            $exists->execute([$releaseId, (int) $issue['id']]);
            if ($exists->fetchColumn()) {
                throw new ConflictException('Công việc đã có trong phiên bản này.', 'duplicate_item');
            }
            $this->releases->addItem($projectId, $releaseId, (int) $issue['id'], $required, (int) $actor['id']);
            $this->history->add($projectId, (int) $issue['id'], (int) $actor['id'], 'release_attached', ['release' => $cur['name']]);
            $this->history->audit($projectId, (int) $actor['id'], 'release.attach', 'release', $releaseId, ['issue' => $issue['code']], $ip);
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor */
    public function detach(array $actor, int $releaseId, int $issueId, string $ip = ''): void
    {
        $rel = $this->releases->find($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
        $projectId = (int) $rel['project_id'];
        $this->auth->authorize($actor, 'release.plan', $projectId);
        Database::transaction(function (PDO $pdo) use ($actor, $releaseId, $projectId, $issueId, $ip) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->releases->findForUpdate($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
            $this->assertPlanned($cur);
            if ($this->releases->removeItem($releaseId, $issueId) > 0) {
                $this->history->add($projectId, $issueId, (int) $actor['id'], 'release_detached', ['release' => $cur['name']]);
                $this->history->audit($projectId, (int) $actor['id'], 'release.detach', 'release', $releaseId, ['issue_id' => $issueId], $ip);
            }
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor */
    public function setRequired(array $actor, int $releaseId, int $issueId, bool $required): void
    {
        $rel = $this->releases->find($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
        $projectId = (int) $rel['project_id'];
        $this->auth->authorize($actor, 'release.plan', $projectId);
        Database::transaction(function (PDO $pdo) use ($releaseId, $projectId, $issueId, $required) {
            Database::lockProject($pdo, $projectId);
            $this->assertPlanned($this->releases->findForUpdate($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.'));
            $this->releases->setRequired($releaseId, $issueId, $required);
        }, $this->pdo);
    }

    /**
     * Chi tiết phiên bản. Chưa chốt: dữ liệu hiện tại + điều kiện phát hành.
     * Đã chốt: CHỈ đọc snapshot (không đọc dữ liệu hiện tại của công việc).
     *
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function detail(array $actor, int $releaseId): array
    {
        $rel = $this->releases->find($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
        $projectId = (int) $rel['project_id'];
        $this->auth->authorize($actor, 'release.view', $projectId);
        $out = [
            'release' => $rel,
            'project' => $this->projects->find($projectId),
            'can_plan' => $this->auth->can($actor, 'release.plan', $projectId),
            'can_finalize' => $this->auth->can($actor, 'release.finalize', $projectId),
        ];
        if ($rel['status'] === 'released') {
            return $out + [
                'items' => [], 'snapshot' => $this->releases->snapshotItems($releaseId),
                'readiness' => null, 'candidates' => [],
                'integrity' => $this->snapshots->verifyIntegrity($releaseId),
            ];
        }
        return $out + [
            'items' => $this->releases->items($releaseId),
            'snapshot' => [],
            'readiness' => $this->readiness->evaluate($releaseId),
            'candidates' => $this->releases->candidateIssues($projectId, $releaseId),
            'integrity' => null,
        ];
    }

    /** @param array<string,mixed> $actor @return array<int,array<string,mixed>> */
    public function listForProject(array $actor, int $projectId): array
    {
        $this->auth->authorize($actor, 'release.view', $projectId);
        return $this->releases->listWithCounts($projectId);
    }

    /** @param array<string,mixed> $rel */
    private function assertPlanned(array $rel): void
    {
        if ($rel['status'] !== 'planned') {
            throw new ConflictException('Phiên bản đã được chốt nên không thể thay đổi.', 'release_locked');
        }
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    private function validated(array $in, int $projectId): array
    {
        $v = new Validate();
        $name = $v->str($in, 'name', 'Mã phiên bản', 1, 40, true);
        if ($name !== null && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._ +-]*$/', $name)) {
            $v->fail('name', 'Mã phiên bản chỉ gồm chữ, số, dấu chấm, gạch ngang (ví dụ v1.2.0).');
        }
        $desc = $v->str($in, 'description', 'Mô tả', 0, 5000);
        $date = $v->date($in, 'planned_date', 'Ngày dự kiến');
        $owner = $v->intId($in, 'owner_id', 'Người phụ trách');
        if ($owner !== null) {
            $role = $this->members->roleOf($projectId, $owner);
            if ($role === null || $role === 'viewer') {
                $v->fail('owner_id', 'Người phụ trách phải là thành viên (không phải Viewer) của dự án.');
            }
        }
        $v->throwIfFailed();
        return ['name' => $name, 'description' => $desc, 'planned_date' => $date, 'owner_id' => $owner];
    }
}
