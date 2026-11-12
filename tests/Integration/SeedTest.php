<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Integration;

use ReleaseHub\Database\Seeders\DatabaseSeeder;
use ReleaseHub\Services\AuthService;
use ReleaseHub\Services\DependencyGraphService;
use ReleaseHub\Services\ReleaseReadinessService;
use ReleaseHub\Services\ReleaseSnapshotService;
use ReleaseHub\Tests\DatabaseTestCase;

final class SeedTest extends DatabaseTestCase
{
    private static bool $seeded = false;

    private function seed(): void
    {
        (new DatabaseSeeder($this->pdo))->run();
    }

    public function testSeedCountsAndIdempotency(): void
    {
        $this->seed();
        $q = fn (string $sql) => (int) $this->pdo->query($sql)->fetchColumn();
        $this->assertSame(16, $q('SELECT COUNT(*) FROM projects'));
        $this->assertSame(320, $q('SELECT COUNT(*) FROM issues'));
        $this->assertSame(48, $q('SELECT COUNT(*) FROM releases'));
        $this->assertGreaterThanOrEqual(128, $q('SELECT COUNT(*) FROM dependencies'));
        $this->assertSame(16, $q('SELECT COUNT(DISTINCT project_id) FROM issues'));
        $this->assertSame(16, $q("SELECT COUNT(*) FROM (SELECT project_id FROM issues GROUP BY project_id HAVING COUNT(*) = 20) x"));
        $this->assertSame(16, $q('SELECT COUNT(*) FROM (SELECT project_id FROM dependencies GROUP BY project_id HAVING COUNT(*) >= 8) x'));
        $this->assertSame(16, $q('SELECT COUNT(*) FROM (SELECT project_id FROM releases GROUP BY project_id HAVING COUNT(*) = 3) x'));
        $before = $this->pdo->query('SELECT (SELECT COUNT(*) FROM comments) c, (SELECT COUNT(*) FROM issue_histories) h, (SELECT COUNT(*) FROM test_results) t, (SELECT COUNT(*) FROM users) u')->fetch();
        $this->seed(); // chạy lại không nhân đôi
        $after = $this->pdo->query('SELECT (SELECT COUNT(*) FROM comments) c, (SELECT COUNT(*) FROM issue_histories) h, (SELECT COUNT(*) FROM test_results) t, (SELECT COUNT(*) FROM users) u')->fetch();
        $this->assertSame($before, $after);
        $this->assertSame(320, $q('SELECT COUNT(*) FROM issues'));
    }

    public function testNoCopiedIssueSetsAcrossProjects(): void
    {
        $this->seed();
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM issues')->fetchColumn();
        $distinctTitles = (int) $this->pdo->query('SELECT COUNT(DISTINCT title) FROM issues')->fetchColumn();
        $this->assertSame($total, $distinctTitles, 'Mỗi tiêu đề lỗi/công việc phải khác nhau giữa mọi dự án');
        $distinctSteps = (int) $this->pdo->query("SELECT COUNT(DISTINCT steps_to_reproduce) FROM issues WHERE type = 'bug'")->fetchColumn();
        $this->assertSame((int) $this->pdo->query("SELECT COUNT(*) FROM issues WHERE type = 'bug'")->fetchColumn(), $distinctSteps);
    }

    public function testBugsHaveReproductionDataAndTasksDoNot(): void
    {
        $this->seed();
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM issues WHERE type = 'bug' AND (steps_to_reproduce IS NULL OR expected_result IS NULL OR actual_result IS NULL OR environment IS NULL OR severity IS NULL)")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM issues WHERE type <> 'bug' AND (steps_to_reproduce IS NOT NULL OR severity IS NOT NULL)")->fetchColumn());
    }

    public function testDemoLoginWorksWithRealAuthentication(): void
    {
        $this->seed();
        $u = $this->svc(AuthService::class)->attempt('admin@releasehub.local', DatabaseSeeder::DEMO_PASSWORD);
        $this->assertSame(1, (int) $u['is_admin']);
        $this->expectException(\ReleaseHub\Core\ValidationException::class);
        $this->svc(AuthService::class)->attempt('admin@releasehub.local', 'sai-mat-khau');
    }

    public function testAllGraphsAcyclicAndSnapshotsValid(): void
    {
        $this->seed();
        $g = new DependencyGraphService();
        foreach ($this->pdo->query('SELECT id FROM projects')->fetchAll(\PDO::FETCH_COLUMN) as $pid) {
            $edges = $this->pdo->query("SELECT issue_id, depends_on_id FROM dependencies WHERE project_id = {$pid}")->fetchAll();
            $this->assertNull($g->findAnyCycle($edges));
        }
        $this->assertSame(16, (int) $this->pdo->query("SELECT COUNT(*) FROM releases WHERE status = 'released'")->fetchColumn());
        foreach ($this->pdo->query("SELECT id FROM releases WHERE status = 'released'")->fetchAll(\PDO::FETCH_COLUMN) as $rid) {
            $this->assertTrue($this->svc(ReleaseSnapshotService::class)->verifyIntegrity((int) $rid)['ok']);
        }
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM releases r WHERE r.status = 'released' AND NOT EXISTS (SELECT 1 FROM release_snapshot_items s WHERE s.release_id = r.id)")->fetchColumn());
    }

    public function testReleaseScenariosArePresent(): void
    {
        $this->seed();
        $ready = $blockedBlocker = $blockedDep = 0;
        $svc = $this->svc(ReleaseReadinessService::class);
        foreach ($this->pdo->query("SELECT id FROM releases WHERE name = 'v1.1.0'")->fetchAll(\PDO::FETCH_COLUMN) as $rid) {
            $rep = $svc->evaluate((int) $rid);
            $failed = array_column(array_filter($rep['conditions'], fn ($c) => !$c['passed']), 'key');
            if ($rep['ready']) {
                $ready++;
            }
            if (in_array('blockers_retested', $failed, true)) {
                $blockedBlocker++;
            }
            if (in_array('dependencies_done', $failed, true)) {
                $blockedDep++;
            }
        }
        $this->assertSame(4, $ready);
        $this->assertSame(8, $blockedBlocker);
        $this->assertSame(4, $blockedDep);
        $this->assertGreaterThan(0, (int) $this->pdo->query("SELECT COUNT(*) FROM test_results WHERE result = 'failed'")->fetchColumn());
        $this->assertGreaterThan(0, (int) $this->pdo->query("SELECT COUNT(*) FROM issues WHERE status = 'reopened'")->fetchColumn());
    }

    public function testMembershipVariety(): void
    {
        $this->seed();
        $uid = fn (string $e) => (int) $this->pdo->query("SELECT id FROM users WHERE email = '{$e}'")->fetchColumn();
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM project_members WHERE user_id = ' . $uid('user.a@releasehub.local'))->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM project_members WHERE user_id = ' . $uid('user.b@releasehub.local'))->fetchColumn());
        $roles = $this->pdo->query('SELECT COUNT(DISTINCT role_code) FROM project_members WHERE user_id = ' . $uid('multi@releasehub.local'))->fetchColumn();
        $this->assertSame(4, (int) $roles);
        $this->assertGreaterThanOrEqual(1, (int) $this->pdo->query("SELECT COUNT(*) FROM (SELECT user_id FROM project_members GROUP BY user_id HAVING COUNT(DISTINCT role_code) > 1) x")->fetchColumn());
    }
}
