<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Integration;

use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\ForbiddenException;
use ReleaseHub\Core\HttpException;
use ReleaseHub\Services\IssueService;
use ReleaseHub\Services\IssueWorkflowService;
use ReleaseHub\Services\ReleaseReadinessService;
use ReleaseHub\Services\ReleaseService;
use ReleaseHub\Services\ReleaseSnapshotService;
use ReleaseHub\Tests\DatabaseTestCase;

final class ReleaseFlowTest extends DatabaseTestCase
{
    /** @var array<string,array<string,mixed>> */
    private array $u = [];
    private int $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->p = $this->project('REL');
        foreach (['pm' => 'project_manager', 'rm' => 'release_manager', 'dev' => 'developer', 'qa' => 'tester', 'view' => 'viewer'] as $k => $role) {
            $this->u[$k] = $this->user(strtoupper($k));
            $this->member($this->p, $this->u[$k], $role);
        }
    }

    /** Đưa Blocker đi đủ vòng: In Progress -> fix -> test. */
    private function fixBug(int $id, ?string $result = 'passed', string $ref = 'abcdef1'): void
    {
        $wf = $this->svc(IssueWorkflowService::class);
        $v = (int) $this->fetchIssue($id)['version'];
        $wf->transition($this->u['dev'], $id, 'in_progress', $v);
        $v = (int) $this->fetchIssue($id)['version'];
        $issue = $wf->recordFix($this->u['dev'], $id, $ref, $v);
        if ($result) {
            $wf->recordTest($this->u['qa'], $id, $result, (int) $issue['fix_revision'], $result === 'failed' ? 'Vẫn lỗi' : null);
        }
    }

    private function blockerRelease(string $status = 'open'): array
    {
        $bug = $this->issue($this->p, $this->u['dev'], ['severity' => 'blocker', 'status' => $status]);
        $rel = $this->release($this->p, $this->u['rm']);
        $this->attach($this->p, $rel, $bug, $this->u['rm']);
        return [$rel, $bug];
    }

    /** PHÉP THỬ BẮT BUỘC 2: Blocker chưa kiểm tra lại ngăn xác nhận sẵn sàng. */
    public function testUnverifiedBlockerPreventsReadiness(): void
    {
        [$rel, $bug] = $this->blockerRelease('open');
        $report = $this->svc(ReleaseReadinessService::class)->evaluate($rel);
        $this->assertFalse($report['ready']);
        $keys = array_column(array_filter($report['conditions'], fn ($c) => !$c['passed']), 'key');
        $this->assertContains('blockers_retested', $keys);
        try {
            $this->svc(ReleaseSnapshotService::class)->finalize($this->u['rm'], $rel);
            $this->fail('Không được chốt');
        } catch (HttpException $e) {
            $this->assertSame('not_ready', $e->errorCode);
        }
        $this->assertSame('planned', $this->pdo->query("SELECT status FROM releases WHERE id = {$rel}")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM release_snapshot_items')->fetchColumn());
    }

    public function testResolvedIsNotPassed(): void
    {
        [$rel, $bug] = $this->blockerRelease('open');
        $this->fixBug($bug, null); // đã sửa (Resolved) nhưng chưa test
        $this->assertSame('resolved', $this->fetchIssue($bug)['status']);
        $report = $this->svc(ReleaseReadinessService::class)->evaluate($rel);
        $this->assertFalse($report['ready']);
        $blk = array_values(array_filter($report['conditions'], fn ($c) => $c['key'] === 'blockers_retested'))[0];
        $this->assertStringContainsString('Resolved không đồng nghĩa Passed', $blk['items'][0]['reason']);
    }

    public function testPassedOnOldFixIsInvalidForNewFix(): void
    {
        [$rel, $bug] = $this->blockerRelease('open');
        $this->fixBug($bug, 'passed', 'abcdef1');
        $this->assertTrue($this->svc(ReleaseReadinessService::class)->evaluate($rel)['ready']);

        // Lỗi tái phát -> bản sửa mới (#2) => Passed của bản #1 hết hiệu lực
        $wf = $this->svc(IssueWorkflowService::class);
        $wf->transition($this->u['qa'], $bug, 'reopened', (int) $this->fetchIssue($bug)['version']);
        $wf->transition($this->u['dev'], $bug, 'in_progress', (int) $this->fetchIssue($bug)['version']);
        $wf->recordFix($this->u['dev'], $bug, '1234567', (int) $this->fetchIssue($bug)['version']);
        $report = $this->svc(ReleaseReadinessService::class)->evaluate($rel);
        $this->assertFalse($report['ready']);
        $this->assertSame(2, (int) $this->fetchIssue($bug)['fix_revision']);
    }

    public function testMetadataEditDoesNotInvalidatePassedTest(): void
    {
        [$rel, $bug] = $this->blockerRelease('open');
        $this->fixBug($bug);
        $this->svc(IssueService::class)->update($this->u['dev'], $bug, ['title' => 'Tiêu đề mới', 'priority' => 'urgent'], (int) $this->fetchIssue($bug)['version']);
        $this->assertSame(1, (int) $this->fetchIssue($bug)['fix_revision']);
        $this->assertTrue($this->svc(ReleaseReadinessService::class)->evaluate($rel)['ready']);
    }

    public function testStaleTestRevisionRejected(): void
    {
        [$rel, $bug] = $this->blockerRelease('open');
        $this->fixBug($bug, null);
        $this->expectException(ConflictException::class);
        $this->svc(IssueWorkflowService::class)->recordTest($this->u['qa'], $bug, 'passed', 99, null);
    }

    public function testFailedTestReopensAndBlocks(): void
    {
        [$rel, $bug] = $this->blockerRelease('open');
        $this->fixBug($bug, 'failed');
        $this->assertSame('reopened', $this->fetchIssue($bug)['status']);
        $this->assertFalse($this->svc(ReleaseReadinessService::class)->evaluate($rel)['ready']);
    }

    public function testDependencyOutsideReleaseStillBlocks(): void
    {
        $task = $this->issue($this->p, $this->u['dev'], ['type' => 'task', 'status' => 'closed']);
        $outside = $this->issue($this->p, $this->u['dev'], ['type' => 'task', 'status' => 'in_progress']);
        $this->dep($this->p, $task, $outside, $this->u['dev']);
        $rel = $this->release($this->p, $this->u['rm']);
        $this->attach($this->p, $rel, $task, $this->u['rm']);
        $report = $this->svc(ReleaseReadinessService::class)->evaluate($rel);
        $this->assertFalse($report['ready']);
        $c = array_values(array_filter($report['conditions'], fn ($c) => $c['key'] === 'dependencies_done'))[0];
        $this->assertFalse($c['passed']);
        $this->assertStringContainsString('ngoài phiên bản', $c['items'][0]['reason']);
        // hoàn thành phụ thuộc -> hết chặn
        $this->pdo->exec("UPDATE issues SET status = 'closed' WHERE id = {$outside}");
        $this->assertTrue($this->svc(ReleaseReadinessService::class)->evaluate($rel)['ready']);
    }

    public function testRequiredIncompleteBlocksButOptionalDoesNot(): void
    {
        $done = $this->issue($this->p, $this->u['dev'], ['type' => 'task', 'status' => 'closed']);
        $wip = $this->issue($this->p, $this->u['dev'], ['type' => 'task', 'status' => 'in_progress']);
        $rel = $this->release($this->p, $this->u['rm']);
        $this->attach($this->p, $rel, $done, $this->u['rm']);
        $this->attach($this->p, $rel, $wip, $this->u['rm'], true);
        $this->assertFalse($this->svc(ReleaseReadinessService::class)->evaluate($rel)['ready']);
        $this->pdo->exec("UPDATE release_items SET is_required = 0 WHERE issue_id = {$wip}");
        $this->assertTrue($this->svc(ReleaseReadinessService::class)->evaluate($rel)['ready']);
    }

    public function testAttachRules(): void
    {
        $other = $this->project('OTH');
        $dev2 = $this->user('Other');
        $this->member($other, $dev2, 'developer');
        $foreign = $this->issue($other, $dev2);
        $mine = $this->issue($this->p, $this->u['dev'], ['type' => 'task']);
        $rel = $this->release($this->p, $this->u['rm']);
        $rel2 = $this->release($this->p, $this->u['rm'], 'v2.0.0');
        $svc = $this->svc(ReleaseService::class);
        try {
            $svc->attach($this->u['rm'], $rel, $foreign);
            $this->fail('Khác dự án phải bị từ chối');
        } catch (\ReleaseHub\Core\ValidationException) {
            $this->assertTrue(true);
        }
        $svc->attach($this->u['rm'], $rel, $mine);
        try {
            $svc->attach($this->u['rm'], $rel2, $mine);
            $this->fail('Đã ở phiên bản dự kiến khác');
        } catch (ConflictException $e) {
            $this->assertSame('already_in_release', $e->errorCode);
        }
        $this->expectException(ForbiddenException::class);
        $svc->attach($this->u['dev'], $rel, $mine); // developer không có release.plan
    }

    public function testOnlyReleaseManagerMayFinalize(): void
    {
        $t = $this->issue($this->p, $this->u['dev'], ['type' => 'task', 'status' => 'closed']);
        $rel = $this->release($this->p, $this->u['rm']);
        $this->attach($this->p, $rel, $t, $this->u['rm']);
        foreach (['dev', 'qa', 'view', 'pm'] as $k) {
            try {
                $this->svc(ReleaseSnapshotService::class)->finalize($this->u[$k], $rel);
                $this->fail("{$k} không được chốt");
            } catch (ForbiddenException) {
                $this->assertTrue(true);
            }
        }
        $this->svc(ReleaseSnapshotService::class)->finalize($this->u['rm'], $rel);
        $this->assertSame('released', $this->pdo->query("SELECT status FROM releases WHERE id = {$rel}")->fetchColumn());
    }

    public function testDoubleFinalizeCreatesNoDuplicates(): void
    {
        [$rel, $bug] = $this->blockerRelease('open');
        $this->fixBug($bug);
        $svc = $this->svc(ReleaseSnapshotService::class);
        $svc->finalize($this->u['rm'], $rel);
        try {
            $svc->finalize($this->u['rm'], $rel);
            $this->fail('Chốt lặp phải bị từ chối');
        } catch (ConflictException $e) {
            $this->assertSame('already_released', $e->errorCode);
        }
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM release_snapshot_items WHERE release_id = {$rel}")->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'release.finalize'")->fetchColumn());
    }

    /** PHÉP THỬ BẮT BUỘC 5: sửa công việc sau phát hành không đổi snapshot cũ. */
    public function testEditingIssueAfterReleaseDoesNotChangeSnapshot(): void
    {
        [$rel, $bug] = $this->blockerRelease('open');
        $this->fixBug($bug);
        $this->svc(ReleaseSnapshotService::class)->finalize($this->u['rm'], $rel);
        $before = $this->pdo->query("SELECT title, status, severity, fix_ref, test_evidence FROM release_snapshot_items WHERE release_id = {$rel}")->fetch();
        $md1 = $this->svc(ReleaseSnapshotService::class)->markdown($this->u['view'], $rel);

        $issues = $this->svc(IssueService::class);
        $issues->update($this->u['pm'], $bug, ['title' => 'TIÊU ĐỀ ĐÃ ĐỔI SAU PHÁT HÀNH', 'severity' => 'trivial'], (int) $this->fetchIssue($bug)['version']);
        $this->svc(IssueWorkflowService::class)->transition($this->u['qa'], $bug, 'reopened', (int) $this->fetchIssue($bug)['version']);

        $after = $this->pdo->query("SELECT title, status, severity, fix_ref, test_evidence FROM release_snapshot_items WHERE release_id = {$rel}")->fetch();
        $this->assertSame($before, $after);
        $md2 = $this->svc(ReleaseSnapshotService::class)->markdown($this->u['view'], $rel);
        $this->assertSame($md1, $md2);
        $this->assertStringNotContainsString('ĐÃ ĐỔI', $md2);
        $this->assertTrue($this->svc(ReleaseSnapshotService::class)->verifyIntegrity($rel)['ok']);
        $detail = $this->svc(ReleaseService::class)->detail($this->u['view'], $rel);
        $this->assertSame($before['title'], $detail['snapshot'][0]['title']);
    }

    public function testSnapshotAndReleasedDataAreImmutableAtDatabaseLevel(): void
    {
        [$rel, $bug] = $this->blockerRelease('open');
        $this->fixBug($bug);
        $this->svc(ReleaseSnapshotService::class)->finalize($this->u['rm'], $rel);
        foreach ([
            "UPDATE release_snapshot_items SET title = 'hack' WHERE release_id = {$rel}",
            "DELETE FROM release_snapshot_items WHERE release_id = {$rel}",
            "UPDATE releases SET name = 'x' WHERE id = {$rel}",
            "DELETE FROM releases WHERE id = {$rel}",
            "DELETE FROM release_items WHERE release_id = {$rel}",
        ] as $sql) {
            try {
                $this->pdo->exec($sql);
                $this->fail("Phải bị trigger chặn: {$sql}");
            } catch (\PDOException $e) {
                $this->assertSame('45000', $e->errorInfo[0]);
            }
        }
        $other = $this->issue($this->p, $this->u['dev'], ['type' => 'task']);
        $this->expectException(ConflictException::class);
        $this->svc(ReleaseService::class)->attach($this->u['rm'], $rel, $other); // không gắn thêm vào bản đã chốt
    }

    public function testReleaseNotesComeFromSnapshotAndAreGrouped(): void
    {
        $bug = $this->issue($this->p, $this->u['dev'], ['severity' => 'blocker', 'title' => 'Sai điểm']);
        $imp = $this->issue($this->p, $this->u['dev'], ['type' => 'improvement', 'status' => 'closed', 'title' => 'Tăng tốc']);
        $rel = $this->release($this->p, $this->u['rm'], 'v3.1.0');
        $this->attach($this->p, $rel, $bug, $this->u['rm']);
        $this->attach($this->p, $rel, $imp, $this->u['rm']);
        $this->fixBug($bug);
        $notes = $this->svc(ReleaseSnapshotService::class);
        $notes->finalize($this->u['rm'], $rel);
        $md = $notes->markdown($this->u['view'], $rel);
        $this->assertStringContainsString('v3.1.0', $md);
        $this->assertStringContainsString('## Sửa lỗi', $md);
        $this->assertStringContainsString('## Cải tiến', $md);
        $this->assertStringContainsString('REL-1', $md);
        $json = $notes->exportJson($this->u['view'], $rel);
        $this->assertCount(2, $json['changes']);
        $this->assertSame('v3.1.0', $json['release']);
    }

    public function testNotesBlockedForOutsiderAndUnreleased(): void
    {
        $t = $this->issue($this->p, $this->u['dev'], ['type' => 'task', 'status' => 'closed']);
        $rel = $this->release($this->p, $this->u['rm']);
        $this->attach($this->p, $rel, $t, $this->u['rm']);
        $out = $this->user('Outsider');
        try {
            $this->svc(ReleaseSnapshotService::class)->markdown($this->u['view'], $rel);
            $this->fail('Chưa chốt');
        } catch (ConflictException $e) {
            $this->assertSame('not_released', $e->errorCode);
        }
        $this->svc(ReleaseSnapshotService::class)->finalize($this->u['rm'], $rel);
        $this->expectException(ForbiddenException::class);
        $this->svc(ReleaseSnapshotService::class)->markdown($out, $rel);
    }
}
