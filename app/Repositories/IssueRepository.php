<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

final class IssueRepository extends Repository
{
    private const BASE = 'SELECT i.*, p.code AS project_code, p.name AS project_name,
            a.full_name AS assignee_name, rp.full_name AS reporter_name
          FROM issues i
          JOIN projects p ON p.id = i.project_id
          LEFT JOIN users a ON a.id = i.assignee_id
          LEFT JOIN users rp ON rp.id = i.reporter_id';

    private const EDITABLE = [
        'title', 'description', 'priority', 'severity', 'due_date', 'environment',
        'steps_to_reproduce', 'expected_result', 'actual_result', 'commit_url', 'pr_url',
        'assignee_id', 'status', 'fix_ref', 'fix_revision', 'type',
    ];

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->one(self::BASE . ' WHERE i.id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findInProject(int $projectId, int $id): ?array
    {
        return $this->one(self::BASE . ' WHERE i.id = ? AND i.project_id = ?', [$id, $projectId]);
    }

    /** @param array<string,mixed> $d */
    public function insert(array $d): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->run(
            'INSERT INTO issues (project_id, number, code, type, title, description, priority, severity, status, reporter_id, assignee_id,
                 due_date, environment, steps_to_reproduce, expected_result, actual_result, commit_url, pr_url, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $d['project_id'], $d['number'], $d['code'], $d['type'], $d['title'], $d['description'], $d['priority'], $d['severity'],
                'open', $d['reporter_id'], $d['assignee_id'], $d['due_date'], $d['environment'], $d['steps_to_reproduce'],
                $d['expected_result'], $d['actual_result'], $d['commit_url'], $d['pr_url'], $now, $now,
            ]
        );
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * UPDATE ... WHERE id = ? AND version = ? (khóa lạc quan). Trả về số dòng bị ảnh hưởng (0 = xung đột).
     *
     * @param array<string,mixed> $fields
     */
    public function updateVersioned(int $id, int $expectedVersion, array $fields): int
    {
        $sets = [];
        $params = [];
        foreach ($fields as $col => $val) {
            if (!in_array($col, self::EDITABLE, true)) {
                throw new \InvalidArgumentException('Cột không được phép cập nhật: ' . $col);
            }
            $sets[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $sets[] = 'version = version + 1';
        $sets[] = 'updated_at = ?';
        $params[] = gmdate('Y-m-d H:i:s');
        array_push($params, $id, $expectedVersion);
        return $this->run('UPDATE issues SET ' . implode(', ', $sets) . ' WHERE id = ? AND version = ?', $params);
    }

    /**
     * @param int[] $projectIds
     * @param array<string,mixed> $f
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public function search(array $projectIds, array $f, string $sort, string $dir, int $page, int $perPage): array
    {
        if (!$projectIds) {
            return ['rows' => [], 'total' => 0];
        }
        $where = ['i.project_id IN (' . self::in($projectIds) . ')'];
        $params = $projectIds;
        foreach (['type', 'status', 'priority', 'severity'] as $col) {
            if (!empty($f[$col])) {
                $where[] = "i.{$col} = ?";
                $params[] = $f[$col];
            }
        }
        if (isset($f['assignee_id']) && $f['assignee_id'] !== '') {
            if ($f['assignee_id'] === 'none') {
                $where[] = 'i.assignee_id IS NULL';
            } else {
                $where[] = 'i.assignee_id = ?';
                $params[] = (int) $f['assignee_id'];
            }
        }
        if (!empty($f['release_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM release_items ri WHERE ri.issue_id = i.id AND ri.release_id = ?)';
            $params[] = (int) $f['release_id'];
        }
        if (!empty($f['open_only'])) {
            $where[] = 'NOT ' . self::completeSql('i');
        }
        if (!empty($f['q'])) {
            $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            $where[] = '(i.title LIKE ? OR i.code LIKE ? OR i.description LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $sortMap = [
            'code' => 'i.project_id, i.number',
            'title' => 'i.title',
            'type' => 'i.type',
            'status' => "FIELD(i.status,'open','reopened','in_progress','resolved','verified','closed')",
            'priority' => "FIELD(i.priority,'low','medium','high','urgent')",
            'severity' => "FIELD(i.severity,'trivial','minor','major','critical','blocker')",
            'due_date' => 'i.due_date IS NULL, i.due_date',
            'updated_at' => 'i.updated_at',
            'created_at' => 'i.created_at',
        ];
        $order = $sortMap[$sort] ?? $sortMap['updated_at'];
        $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $orderSql = ' ORDER BY ' . $order . ' ' . $direction . ', i.id ' . $direction;

        $total = (int) $this->scalar('SELECT COUNT(*) FROM issues i' . $whereSql, $params);
        $perPage = max(1, min(100, $perPage));
        $offset = (max(1, $page) - 1) * $perPage;
        $blocked = 'SELECT COUNT(*) FROM dependencies d JOIN issues b ON b.id = d.depends_on_id WHERE d.issue_id = i.id AND NOT ' . self::completeSql('b');
        $rows = $this->all(
            "SELECT i.id, i.project_id, i.code, i.number, i.type, i.title, i.priority, i.severity, i.status, i.due_date,
                    i.assignee_id, i.version, i.fix_revision, i.updated_at, p.code AS project_code, a.full_name AS assignee_name,
                    ({$blocked}) AS blocked_by_count
               FROM issues i JOIN projects p ON p.id = i.project_id LEFT JOIN users a ON a.id = i.assignee_id"
            . $whereSql . $orderSql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public function board(int $projectId): array
    {
        $blocked = 'SELECT COUNT(*) FROM dependencies d JOIN issues b ON b.id = d.depends_on_id WHERE d.issue_id = i.id AND NOT ' . self::completeSql('b');
        return $this->all(
            "SELECT i.id, i.code, i.type, i.title, i.priority, i.severity, i.status, i.assignee_id, i.version, i.due_date,
                    a.full_name AS assignee_name, ({$blocked}) AS blocked_by_count
               FROM issues i LEFT JOIN users a ON a.id = i.assignee_id
              WHERE i.project_id = ? ORDER BY i.number",
            [$projectId]
        );
    }

    /** @return array<string,int> */
    public function countByStatus(int $projectId): array
    {
        $rows = $this->all('SELECT status, COUNT(*) c FROM issues WHERE project_id = ? GROUP BY status', [$projectId]);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function idsAndCodes(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        return $this->all('SELECT id, code, title, status, type, project_id FROM issues WHERE id IN (' . self::in($ids) . ')', array_values($ids));
    }
}
