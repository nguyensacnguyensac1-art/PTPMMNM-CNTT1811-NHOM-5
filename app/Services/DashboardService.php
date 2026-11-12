<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use PDO;
use ReleaseHub\Domain\Catalog;
use ReleaseHub\Repositories\HistoryRepository;
use ReleaseHub\Repositories\ProjectRepository;
use ReleaseHub\Repositories\Repository;

/** Số liệu dashboard: mọi truy vấn đều giới hạn trong các dự án người dùng có quyền xem. */
final class DashboardService
{
    public function __construct(
        private PDO $pdo,
        private ProjectRepository $projects,
        private HistoryRepository $history,
        private AuthorizationService $auth
    ) {
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function summary(array $actor): array
    {
        $ids = $this->auth->visibleProjectIds($actor) ?? $this->projects->allIds();
        if (!$ids) {
            return $this->empty();
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $complete = Repository::completeSql('i');

        $kpi = $this->row(
            "SELECT
                SUM(i.type = 'bug' AND NOT {$complete}) AS open_bugs,
                SUM(i.type = 'bug' AND i.severity = 'blocker' AND NOT {$complete}) AS open_blockers,
                SUM(i.type = 'bug' AND i.status = 'resolved') AS awaiting_test,
                SUM(NOT {$complete}) AS open_items,
                COUNT(*) AS total_items
               FROM issues i WHERE i.project_id IN ({$in})",
            $ids
        );
        $upcoming = $this->rows(
            "SELECT r.id, r.name, r.planned_date, p.code AS project_code, p.name AS project_name,
                    (SELECT COUNT(*) FROM release_items ri WHERE ri.release_id = r.id) AS item_count
               FROM releases r JOIN projects p ON p.id = r.project_id
              WHERE r.status = 'planned' AND r.project_id IN ({$in}) AND p.archived_at IS NULL
           ORDER BY r.planned_date IS NULL, r.planned_date, r.id LIMIT 8",
            $ids
        );
        $byStatus = $this->rows("SELECT i.status, COUNT(*) c FROM issues i WHERE i.project_id IN ({$in}) GROUP BY i.status", $ids);
        $bySeverity = $this->rows("SELECT i.severity, COUNT(*) c FROM issues i WHERE i.type = 'bug' AND NOT {$complete} AND i.project_id IN ({$in}) GROUP BY i.severity", $ids);
        $perProject = $this->rows(
            "SELECT p.code, SUM(i.type = 'bug' AND NOT {$complete}) AS open_bugs, SUM({$complete}) AS done, COUNT(i.id) AS total
               FROM projects p LEFT JOIN issues i ON i.project_id = p.id WHERE p.id IN ({$in}) AND p.archived_at IS NULL GROUP BY p.id, p.code ORDER BY p.code",
            $ids
        );
        $mine = $this->rows(
            "SELECT i.id, i.code, i.title, i.status, i.type, i.severity, i.due_date FROM issues i
              WHERE i.assignee_id = ? AND NOT {$complete} AND i.project_id IN ({$in}) ORDER BY i.due_date IS NULL, i.due_date, i.id LIMIT 8",
            array_merge([(int) $actor['id']], $ids)
        );
        $statusData = [];
        foreach ($byStatus as $r) {
            $statusData[$r['status']] = (int) $r['c'];
        }
        $sevData = [];
        foreach ($bySeverity as $r) {
            $sevData[(string) ($r['severity'] ?? 'none')] = (int) $r['c'];
        }
        return [
            'kpi' => array_map('intval', $kpi),
            'project_count' => count($ids),
            'upcoming_releases' => $upcoming,
            'chart_status' => ['keys' => array_keys(array_intersect_key(array_flip(Catalog::STATUS_ORDER), $statusData)), 'labels' => array_values(array_map(fn ($s) => Catalog::STATUSES[$s], array_keys(array_intersect_key(array_flip(Catalog::STATUS_ORDER), $statusData)))),
                               'values' => array_values(array_map(fn ($s) => $statusData[$s], array_keys(array_intersect_key(array_flip(Catalog::STATUS_ORDER), $statusData))))],
            'chart_severity' => ['labels' => array_map(fn ($s) => Catalog::SEVERITIES[$s] ?? 'Không áp dụng', array_keys($sevData)), 'values' => array_values($sevData), 'keys' => array_keys($sevData)],
            'chart_projects' => ['labels' => array_column($perProject, 'code'), 'open' => array_map('intval', array_column($perProject, 'open_bugs')), 'done' => array_map('intval', array_column($perProject, 'done'))],
            'my_items' => $mine,
            'activity' => $this->history->recent($ids, 10),
        ];
    }

    /** @return array<string,mixed> */
    private function empty(): array
    {
        return ['kpi' => ['open_bugs' => 0, 'open_blockers' => 0, 'awaiting_test' => 0, 'open_items' => 0, 'total_items' => 0], 'project_count' => 0,
            'upcoming_releases' => [], 'chart_status' => ['keys' => array_keys(array_intersect_key(array_flip(Catalog::STATUS_ORDER), $statusData)), 'labels' => [], 'values' => []], 'chart_severity' => ['labels' => [], 'values' => [], 'keys' => []],
            'chart_projects' => ['labels' => [], 'open' => [], 'done' => []], 'my_items' => [], 'activity' => []];
    }

    /** @param array<int,mixed> $p @return array<string,mixed> */
    private function row(string $sql, array $p): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return array_map(static fn ($v) => $v ?? 0, $st->fetch() ?: []);
    }

    /** @param array<int,mixed> $p @return array<int,array<string,mixed>> */
    private function rows(string $sql, array $p): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }
}
