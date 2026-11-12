<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use PDO;
use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\Database;
use ReleaseHub\Core\HttpException;
use ReleaseHub\Core\NotFoundException;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Repositories\DependencyRepository;
use ReleaseHub\Repositories\HistoryRepository;
use ReleaseHub\Repositories\IssueRepository;

final class DependencyService
{
    public function __construct(
        private PDO $pdo,
        private DependencyRepository $deps,
        private IssueRepository $issues,
        private HistoryRepository $history,
        private DependencyGraphService $graph,
        private AuthorizationService $auth
    ) {
    }

    /**
     * A phụ thuộc B. Chạy trong transaction với khóa dự án để hai request đồng thời không thể
     * cùng tạo ra chu trình (request thứ hai sẽ đọc đồ thị đã gồm cạnh của request thứ nhất).
     *
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function add(array $actor, int $issueId, int $dependsOnId, string $ip = ''): array
    {
        $a = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
        $this->auth->authorize($actor, 'dependency.manage', (int) $a['project_id']);
        $b = $this->issues->find($dependsOnId);
        if (!$b) {
            throw new ValidationException(['depends_on_id' => 'Công việc được chọn không tồn tại.']);
        }
        if ((int) $a['project_id'] !== (int) $b['project_id']) {
            throw new ValidationException(['depends_on_id' => 'Không thể liên kết công việc thuộc dự án khác.'], 'Liên kết chéo dự án bị từ chối.');
        }
        $projectId = (int) $a['project_id'];

        return Database::transaction(function (PDO $pdo) use ($actor, $a, $b, $projectId, $ip) {
            Database::lockProject($pdo, $projectId);
            $aid = (int) $a['id'];
            $bid = (int) $b['id'];
            if ($aid === $bid) {
                throw new ValidationException(['depends_on_id' => 'Một công việc không thể phụ thuộc chính nó.'], 'Tự phụ thuộc bị từ chối.');
            }
            if ($this->deps->exists($aid, $bid)) {
                throw new ConflictException('Quan hệ phụ thuộc này đã tồn tại.', 'duplicate_dependency');
            }
            $cycle = $this->graph->findCycleIfAdded($this->deps->edges($projectId), $aid, $bid);
            if ($cycle !== null) {
                $codes = $this->codesFor($cycle);
                throw new HttpException(422, 'Quan hệ này tạo vòng phụ thuộc: ' . implode(' → ', $codes), 'cycle_detected', [
                    'cycle' => $codes,
                    'cycle_text' => implode(' → ', $codes),
                ]);
            }
            $id = $this->deps->insert($projectId, $aid, $bid, (int) $actor['id']);
            $this->history->add($projectId, $aid, (int) $actor['id'], 'dependency_added', ['depends_on' => $b['code']]);
            $this->history->add($projectId, $bid, (int) $actor['id'], 'dependent_added', ['blocks' => $a['code']]);
            $this->history->audit($projectId, (int) $actor['id'], 'dependency.add', 'dependency', $id, ['from' => $a['code'], 'to' => $b['code']], $ip);
            return ['id' => $id, 'issue' => $a['code'], 'depends_on' => $b['code']];
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor */
    public function remove(array $actor, int $dependencyId, string $ip = ''): void
    {
        $dep = $this->deps->find($dependencyId) ?? throw new NotFoundException('Không tìm thấy quan hệ phụ thuộc.');
        $projectId = (int) $dep['project_id'];
        $this->auth->authorize($actor, 'dependency.manage', $projectId);
        Database::transaction(function (PDO $pdo) use ($actor, $dep, $projectId, $ip) {
            Database::lockProject($pdo, $projectId);
            if ($this->deps->find((int) $dep['id']) === null) {
                return;
            }
            $this->deps->delete((int) $dep['id']);
            $ids = $this->issues->idsAndCodes([(int) $dep['issue_id'], (int) $dep['depends_on_id']]);
            $codes = array_column($ids, 'code', 'id');
            $this->history->add($projectId, (int) $dep['issue_id'], (int) $actor['id'], 'dependency_removed', ['depends_on' => $codes[$dep['depends_on_id']] ?? '']);
            $this->history->add($projectId, (int) $dep['depends_on_id'], (int) $actor['id'], 'dependent_removed', ['blocks' => $codes[$dep['issue_id']] ?? '']);
            $this->history->audit($projectId, (int) $actor['id'], 'dependency.remove', 'dependency', (int) $dep['id'], [], $ip);
        }, $this->pdo);
    }

    /** @param array<string,mixed> $actor @return array{depends_on:array<int,array<string,mixed>>,blocks:array<int,array<string,mixed>>} */
    public function forIssue(array $actor, int $issueId): array
    {
        $issue = $this->issues->find($issueId) ?? throw new NotFoundException('Không tìm thấy công việc.');
        $this->auth->authorize($actor, 'project.view', (int) $issue['project_id']);
        return ['depends_on' => $this->deps->dependenciesOf($issueId), 'blocks' => $this->deps->dependentsOf($issueId)];
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function graph(array $actor, int $projectId): array
    {
        $this->auth->authorize($actor, 'project.view', $projectId);
        return [
            'nodes' => $this->deps->graphNodes($projectId),
            'edges' => $this->deps->edges($projectId),
            'blockers' => $this->deps->blockers($projectId),
        ];
    }

    /** @param int[] $ids @return string[] */
    private function codesFor(array $ids): array
    {
        $map = array_column($this->issues->idsAndCodes(array_values(array_unique($ids))), 'code', 'id');
        return array_map(static fn ($id) => (string) ($map[$id] ?? ('#' . $id)), $ids);
    }
}
