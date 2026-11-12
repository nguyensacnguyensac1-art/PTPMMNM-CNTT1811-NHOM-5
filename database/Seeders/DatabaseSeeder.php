<?php
declare(strict_types=1);

namespace ReleaseHub\Database\Seeders;

use PDO;
use ReleaseHub\Core\Container;
use ReleaseHub\Services\ReleaseSnapshotService;

/**
 * Seeder XÁC ĐỊNH: cùng thứ tự, cùng mốc thời gian cố định => cùng dữ liệu. Chạy lại không nhân đôi
 * (dự án đã có mã thì bỏ qua; người dùng so theo email). Toàn bộ là dữ liệu giả.
 */
final class DatabaseSeeder
{
    public const DEMO_PASSWORD = 'ReleaseHub@2026';
    private const BASE = '2026-06-01 02:00:00';

    /** Cặp (A phụ thuộc B) theo chỉ số 0..19; đồ thị luôn không có vòng. */
    private const EDGES = [[14, 12], [13, 12], [17, 13], [4, 2], [6, 5], [18, 14], [3, 2], [5, 4], [8, 4], [10, 9], [15, 9], [16, 15], [19, 16], [7, 6], [11, 10]];
    private const RELEASE_ITEMS = [0 => [0, 2, 4, 8, 12, 13, 17], 1 => [1, 3, 5, 6, 14, 18], 2 => [7, 9, 10, 15, 16, 19]];
    private const PRIORITY = ['urgent', 'urgent', 'high', 'high', 'high', 'medium', 'high', 'medium', 'low', 'medium', 'low', 'high', 'medium', 'medium', 'high', 'medium', 'low', 'low', 'medium', 'low'];

    private const USERS = [
        'admin' => ['admin@releasehub.local', 'Quản trị hệ thống', 1],
        'pm1' => ['minh.pm@releasehub.local', 'Nguyễn Văn Minh', 0], 'pm2' => ['an.pm@releasehub.local', 'Trần Thu An', 0],
        'rm1' => ['binh.rm@releasehub.local', 'Lê Quang Bình', 0], 'rm2' => ['ha.rm@releasehub.local', 'Phạm Ngọc Hà', 0],
        'dev1' => ['huy.dev@releasehub.local', 'Hoàng Gia Huy', 0], 'dev2' => ['lan.dev@releasehub.local', 'Đặng Mai Lan', 0],
        'dev3' => ['khoa.dev@releasehub.local', 'Vũ Đức Khoa', 0], 'dev4' => ['nguyen.dev@releasehub.local', 'Bùi Thảo Nguyên', 0],
        'qa1' => ['tung.qa@releasehub.local', 'Đỗ Thanh Tùng', 0], 'qa2' => ['chau.qa@releasehub.local', 'Ngô Bảo Châu', 0], 'qa3' => ['yen.qa@releasehub.local', 'Lý Hải Yến', 0],
        'view1' => ['thu.view@releasehub.local', 'Phan Anh Thư', 0], 'view2' => ['viet.view@releasehub.local', 'Trịnh Quốc Việt', 0],
        'user_a' => ['user.a@releasehub.local', 'Thành viên A (chỉ dự án VOCAB)', 0],
        'user_b' => ['user.b@releasehub.local', 'Thành viên B (chỉ dự án PAY)', 0],
        'multi' => ['multi@releasehub.local', 'Người dùng nhiều dự án', 0],
    ];

    /** @var array<string,int> */
    private array $uid = [];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,int> */
    public function run(): array
    {
        $this->seedUsers();
        $projects = [];
        foreach ([1, 2, 3, 4] as $n) {
            foreach ((require __DIR__ . '/Data/projects_' . $n . '.php') as $p) {
                $projects[] = $p;
            }
        }
        $created = 0;
        foreach ($projects as $pi => $p) {
            $exists = $this->pdo->prepare('SELECT 1 FROM projects WHERE code = ?');
            $exists->execute([$p['code']]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $this->pdo->beginTransaction();
            try {
                $this->seedProject($pi, $p);
                $this->pdo->commit();
                $created++;
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
        return ['projects_created' => $created] + $this->counts();
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        $c = static fn (PDO $p, string $sql) => (int) $p->query($sql)->fetchColumn();
        return [
            'projects' => $c($this->pdo, 'SELECT COUNT(*) FROM projects'),
            'issues' => $c($this->pdo, 'SELECT COUNT(*) FROM issues'),
            'dependencies' => $c($this->pdo, 'SELECT COUNT(*) FROM dependencies'),
            'releases' => $c($this->pdo, 'SELECT COUNT(*) FROM releases'),
            'users' => $c($this->pdo, 'SELECT COUNT(*) FROM users'),
        ];
    }

    private function at(int $hoursFromBase): string
    {
        return gmdate('Y-m-d H:i:s', strtotime(self::BASE . ' UTC') + $hoursFromBase * 3600);
    }

    private function seedUsers(): void
    {
        $hash = password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT);
        $now = $this->at(0);
        foreach (self::USERS as $key => [$email, $name, $admin]) {
            $st = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
            $st->execute([$email]);
            $id = $st->fetchColumn();
            if ($id === false) {
                $this->pdo->prepare('INSERT INTO users (email, full_name, password_hash, is_admin, created_at, updated_at) VALUES (?,?,?,?,?,?)')
                    ->execute([$email, $name, $hash, $admin, $now, $now]);
                $id = $this->pdo->lastInsertId();
            }
            $this->uid[$key] = (int) $id;
        }
    }

    /** @param array<string,mixed> $p */
    private function seedProject(int $pi, array $p): void
    {
        $lower = strtolower($p['code']);
        $scenario = $pi % 4; // 0 sẵn sàng | 1 Blocker chưa test lại | 2 phụ thuộc chưa xong | 3 test thất bại & mở lại
        $pm = $this->uid['pm' . (($pi % 2) + 1)];
        $rmKey = 'rm' . (($pi % 2) + 1);
        $rm = $this->uid[$rmKey];
        $devKeys = ['dev' . (($pi % 4) + 1), 'dev' . ((($pi + 1) % 4) + 1)];
        $qaKey = 'qa' . (($pi % 3) + 1);
        $qa = $this->uid[$qaKey];

        $this->pdo->prepare('INSERT INTO projects (code, name, description, color, icon, owner_id, issue_counter, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$p['code'], $p['name'], $p['description'], $p['color'], $p['icon'], $pm, 20, $this->at($pi), $this->at($pi)]);
        $pid = (int) $this->pdo->lastInsertId();
        $this->audit($pid, $pm, 'project.create', 'project', $pid, $this->at($pi));

        // Thành viên và vai trò (một người có thể có vai trò khác nhau ở mỗi dự án)
        $members = [];
        $add = function (string $key, string $role) use (&$members): void {
            $members[$key] ??= $role;
        };
        $add('pm' . (($pi % 2) + 1), 'project_manager');
        $add($rmKey, 'release_manager');
        foreach ($devKeys as $k) {
            $add($k, 'developer');
        }
        $add($qaKey, 'tester');
        $add('view' . (($pi % 2) + 1), 'viewer');
        if ($pi % 3 === 0) {
            $add('pm' . ((($pi + 1) % 2) + 1), 'viewer');
        }
        if ($pi % 2 === 1) {
            $add('dev' . ((($pi + 2) % 4) + 1), 'tester');
        }
        match ($pi) {
            0 => [$add('user_a', 'developer'), $add('multi', 'project_manager')],
            1 => [$add('user_b', 'developer'), $add('multi', 'viewer')],
            2 => $add('multi', 'tester'),
            3 => $add('multi', 'release_manager'),
            default => null,
        };
        foreach ($members as $key => $role) {
            $this->pdo->prepare('INSERT INTO project_members (project_id, user_id, role_code, created_at) VALUES (?,?,?,?)')->execute([$pid, $this->uid[$key], $role, $this->at($pi)]);
        }
        $devs = array_map(fn ($k) => $this->uid[$k], $devKeys);

        // Công việc
        $ids = [];
        $planOf = [];
        $ins = $this->pdo->prepare('INSERT INTO issues (project_id, number, code, type, title, description, priority, severity, status, reporter_id, assignee_id, due_date,
                environment, steps_to_reproduce, expected_result, actual_result, fix_ref, fix_revision, commit_url, pr_url, version, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($p['issues'] as $idx => $row) {
            $number = $idx + 1;
            $code = $p['code'] . '-' . $number;
            $isBug = $row[0] === 'b';
            $type = ['b' => 'bug', 't' => 'task', 'i' => 'improvement'][$row[0]];
            $plan = $this->plan($idx, $scenario);
            $planOf[$idx] = $plan;
            $createdH = $pi * 5 + $idx * 9;
            $events = $this->events($isBug, $plan, $idx, $scenario);
            $lastH = $createdH + 7 * (count($events) - 1);
            $title = $isBug ? $row[2] : $row[1];
            $env = $p['env'][$idx % 2];
            $dev = $devs[$idx % 2];
            $assignee = ($plan['status'] !== 'open' || $idx % 2 === 0) ? $dev : null;
            $due = $plan['complete'] ? null : ($idx === 9 ? '2026-10-20' : gmdate('Y-m-d', strtotime('2026-11-05 UTC') + (($idx + $pi) * 2) * 86400));
            $pr = $plan['rev'] > 0 ? "https://github.com/releasehub-demo/{$lower}/pull/" . ($plan['rev'] === 2 ? 100 : 10) . $number : null;
            $commit = $plan['rev'] > 0 ? "https://github.com/releasehub-demo/{$lower}/commit/" . substr(sha1($code . $plan['rev']), 0, 7) : null;
            $ins->execute([
                $pid, $number, $code, $type, $title,
                $isBug ? 'Lỗi ghi nhận khi kiểm thử "' . $p['name'] . '": ' . $row[5] . '.' : $row[2],
                self::PRIORITY[$idx], $isBug ? $row[1] : null, $plan['status'],
                $isBug ? $qa : $pm, $assignee, $due,
                $isBug ? $env : null, $isBug ? $row[3] : null, $isBug ? $row[4] : null, $isBug ? $row[5] : null,
                $pr, $plan['rev'], $isBug ? $commit : null, $isBug ? $pr : null,
                count($events), $this->at($createdH), $this->at($lastH),
            ]);
            $iid = (int) $this->pdo->lastInsertId();
            $ids[$idx] = $iid;
            $this->seedHistoryAndTests($pid, $iid, $events, $createdH, $dev, $qa, $pm, $pr, $lower, $number);
            $this->seedComments($p, $pid, $iid, $idx, $isBug, $title, $plan, $createdH, $lastH, $dev, $qa, $pm);
        }

        // Quan hệ phụ thuộc (A phụ thuộc B)
        $edges = self::EDGES;
        if ($scenario === 2) {
            $edges[] = [14, 15]; // việc trong phiên bản v1.1.0 phụ thuộc việc chưa xong nằm ngoài phiên bản
        }
        $dep = $this->pdo->prepare('INSERT INTO dependencies (project_id, issue_id, depends_on_id, created_by, created_at) VALUES (?,?,?,?,?)');
        foreach ($edges as $k => [$a, $b]) {
            $dep->execute([$pid, $ids[$a], $ids[$b], $devs[0], $this->at($pi * 5 + 3 + $k)]);
        }

        // Phiên bản
        $names = ['v1.0.0', 'v1.1.0', 'v1.2.0'];
        $planned = [gmdate('Y-m-d', strtotime('2026-09-14 UTC')), gmdate('Y-m-d', strtotime('2026-11-05 UTC') + $pi * 86400), gmdate('Y-m-d', strtotime('2026-12-15 UTC') + $pi * 86400)];
        $relIds = [];
        foreach ($names as $r => $name) {
            $this->pdo->prepare('INSERT INTO releases (project_id, name, description, planned_date, owner_id, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$pid, $name, $p['releases'][$r], $planned[$r], $rm, $rm, $this->at($pi + $r), $this->at($pi + $r)]);
            $rid = (int) $this->pdo->lastInsertId();
            $relIds[$r] = $rid;
            foreach (self::RELEASE_ITEMS[$r] as $idx) {
                $required = !($r === 2 && $idx === 19);
                $this->pdo->prepare('INSERT INTO release_items (project_id, release_id, issue_id, is_required, added_by, added_at) VALUES (?,?,?,?,?,?)')
                    ->execute([$pid, $rid, $ids[$idx], $required ? 1 : 0, $rm, $this->at($pi + $r + 1)]);
            }
        }
        // v1.0.0 đã phát hành: chốt qua chính ReleaseSnapshotService (kiểm tra điều kiện + snapshot thật)
        $rmUser = ['id' => $rm, 'full_name' => self::USERS[$rmKey][1], 'email' => self::USERS[$rmKey][0], 'is_admin' => 0];
        Container::get(ReleaseSnapshotService::class)->finalize($rmUser, $relIds[0], '', gmdate('Y-m-d H:i:s', strtotime('2026-09-15 03:00:00 UTC') + $pi * 600));
    }

    /**
     * @return array{status:string,rev:int,complete:bool}
     */
    private function plan(int $idx, int $scenario): array
    {
        $bug = [
            0 => ['verified', 1], 1 => [['verified', 1], ['resolved', 2], ['verified', 1], ['reopened', 1]][$scenario], 2 => ['closed', 1],
            3 => $scenario === 3 ? ['reopened', 1] : ['closed', 1], 4 => ['closed', 1], 5 => ['verified', 1], 6 => ['closed', 1],
            7 => ['in_progress', 0], 8 => ['verified', 1], 9 => ['open', 0], 10 => ['reopened', 1], 11 => ['open', 0],
        ];
        $work = [12 => 'closed', 13 => 'closed', 14 => 'resolved', 15 => 'in_progress', 16 => 'open', 17 => 'closed', 18 => 'resolved', 19 => 'open'];
        [$status, $rev] = $idx < 12 ? $bug[$idx] : [$work[$idx], 0];
        $complete = $idx < 12 ? in_array($status, ['verified', 'closed'], true) : in_array($status, ['resolved', 'closed'], true);
        return ['status' => $status, 'rev' => $rev, 'complete' => $complete];
    }

    /**
     * Chuỗi sự kiện hợp lệ theo workflow (dùng cho lịch sử và kết quả kiểm thử).
     *
     * @param array{status:string,rev:int,complete:bool} $plan
     * @return array<int,array<string,mixed>>
     */
    private function events(bool $isBug, array $plan, int $idx, int $scenario): array
    {
        $ev = [['a' => 'created']];
        $s = $plan['status'];
        if ($s === 'open') {
            return $ev;
        }
        $ev[] = ['a' => 'status', 'from' => 'open', 'to' => 'in_progress'];
        if ($s === 'in_progress') {
            return $ev;
        }
        if (!$isBug) {
            $ev[] = ['a' => 'status', 'from' => 'in_progress', 'to' => 'resolved'];
            if ($s === 'closed') {
                $ev[] = ['a' => 'status', 'from' => 'resolved', 'to' => 'closed'];
            }
            return $ev;
        }
        $ev[] = ['a' => 'fix', 'rev' => 1];
        if ($s === 'reopened') {
            $ev[] = ['a' => 'test', 'result' => 'failed', 'rev' => 1, 'from' => 'resolved', 'to' => 'reopened'];
            return $ev;
        }
        $ev[] = ['a' => 'test', 'result' => 'passed', 'rev' => 1, 'from' => 'resolved', 'to' => 'verified'];
        if ($idx === 1 && $scenario === 1) { // tái phát: bản sửa #2 chưa kiểm thử
            $ev[] = ['a' => 'status', 'from' => 'verified', 'to' => 'reopened'];
            $ev[] = ['a' => 'status', 'from' => 'reopened', 'to' => 'in_progress'];
            $ev[] = ['a' => 'fix', 'rev' => 2];
            return $ev;
        }
        if ($s === 'closed') {
            $ev[] = ['a' => 'status', 'from' => 'verified', 'to' => 'closed'];
        }
        return $ev;
    }

    /** @param array<int,array<string,mixed>> $events */
    private function seedHistoryAndTests(int $pid, int $iid, array $events, int $createdH, int $dev, int $qa, int $pm, ?string $pr, string $lower, int $number): void
    {
        $h = $this->pdo->prepare('INSERT INTO issue_histories (project_id, issue_id, actor_id, action, changes, created_at) VALUES (?,?,?,?,?,?)');
        $t = $this->pdo->prepare('INSERT INTO test_results (project_id, issue_id, tester_id, result, fix_revision, fix_ref, note, tested_at) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($events as $k => $e) {
            $when = $this->at($createdH + 7 * $k);
            $refFor = static fn (int $rev) => "https://github.com/releasehub-demo/{$lower}/pull/" . ($rev === 2 ? 100 : 10) . $number;
            switch ($e['a']) {
                case 'created':
                    $h->execute([$pid, $iid, $pm, 'created', null, $when]);
                    break;
                case 'status':
                    $actor = in_array($e['to'], ['closed', 'reopened'], true) ? $qa : $dev;
                    $h->execute([$pid, $iid, $actor, 'status_changed', json_encode(['status' => [$e['from'], $e['to']]]), $when]);
                    break;
                case 'fix':
                    $h->execute([$pid, $iid, $dev, 'fix_recorded', json_encode(['fix_ref' => $refFor($e['rev']), 'fix_revision' => $e['rev'], 'status' => ['in_progress', 'resolved']], JSON_UNESCAPED_SLASHES), $when]);
                    break;
                case 'test':
                    $note = $e['result'] === 'passed' ? 'Đã kiểm tra lại các bước tái hiện, kết quả đúng mong đợi.' : 'Vẫn còn tái hiện được ở một trường hợp biên, cần sửa tiếp.';
                    $t->execute([$pid, $iid, $qa, $e['result'], $e['rev'], $refFor($e['rev']), $note, $when]);
                    $h->execute([$pid, $iid, $qa, 'test_recorded', json_encode(['result' => $e['result'], 'fix_revision' => $e['rev'], 'status' => [$e['from'], $e['to']]]), $when]);
                    break;
            }
        }
    }

    /** @param array<string,mixed> $p @param array{status:string,rev:int,complete:bool} $plan */
    private function seedComments(array $p, int $pid, int $iid, int $idx, bool $isBug, string $title, array $plan, int $createdH, int $lastH, int $dev, int $qa, int $pm): void
    {
        $c = $this->pdo->prepare('INSERT INTO comments (project_id, issue_id, user_id, body, created_at) VALUES (?,?,?,?,?)');
        $fill = static fn (string $tpl) => str_replace('{title}', $title, $tpl);
        if ($plan['status'] !== 'open' || $idx < 4) {
            $c->execute([$pid, $iid, $dev, $fill($p['comments']['dev']), $this->at($createdH + 2)]);
        }
        if ($isBug && $plan['rev'] > 0) {
            $c->execute([$pid, $iid, $qa, $fill($p['comments']['qa']), $this->at($lastH + 1)]);
        }
        if ($idx < 4) {
            $c->execute([$pid, $iid, $pm, $fill($p['comments']['pm']), $this->at($createdH + 1)]);
        }
    }

    private function audit(int $pid, int $uid, string $action, string $type, int $id, string $at): void
    {
        $this->pdo->prepare('INSERT INTO audit_logs (project_id, user_id, action, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)')->execute([$pid, $uid, $action, $type, $id, $at]);
    }
}
