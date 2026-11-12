<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Integration;

use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\ForbiddenException;
use ReleaseHub\Core\HttpException;
use ReleaseHub\Domain\WorkflowRules;
use ReleaseHub\Services\IssueWorkflowService;
use ReleaseHub\Tests\DatabaseTestCase;

final class WorkflowTest extends DatabaseTestCase
{
    private array $dev;
    private array $qa;
    private array $pm;
    private int $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->p = $this->project('WFL');
        $this->dev = $this->user('Dev');
        $this->qa = $this->user('QA');
        $this->pm = $this->user('PM');
        $this->member($this->p, $this->dev, 'developer');
        $this->member($this->p, $this->qa, 'tester');
        $this->member($this->p, $this->pm, 'project_manager');
    }

    private function v(int $id): int
    {
        return (int) $this->fetchIssue($id)['version'];
    }

    public function testHappyPathBug(): void
    {
        $id = $this->issue($this->p, $this->dev);
        $wf = $this->svc(IssueWorkflowService::class);
        $wf->transition($this->dev, $id, 'in_progress', $this->v($id));
        $this->assertSame((int) $this->dev['id'], (int) $this->fetchIssue($id)['assignee_id'], 'Tự gán người nhận xử lý');
        $wf->recordFix($this->dev, $id, 'https://github.com/x/y/pull/7', $this->v($id));
        $this->assertSame('resolved', $this->fetchIssue($id)['status']);
        $wf->recordTest($this->qa, $id, 'passed', 1, 'OK');
        $this->assertSame('verified', $this->fetchIssue($id)['status']);
        $wf->transition($this->qa, $id, 'closed', $this->v($id));
        $this->assertSame('closed', $this->fetchIssue($id)['status']);
    }

    public function testInvalidTransitionsRejectedByBackend(): void
    {
        $id = $this->issue($this->p, $this->dev);
        $wf = $this->svc(IssueWorkflowService::class);
        foreach (['resolved', 'verified', 'closed', 'reopened'] as $to) {
            try {
                $wf->transition($this->pm, $id, $to, $this->v($id));
                $this->fail("Open -> {$to} phải bị từ chối");
            } catch (HttpException $e) {
                $this->assertSame('invalid_transition', $e->errorCode);
            }
        }
        $this->assertSame('open', $this->fetchIssue($id)['status']);
    }

    public function testResolvedAndVerifiedOnlyThroughFixAndTestFlows(): void
    {
        $id = $this->issue($this->p, $this->dev, ['status' => 'in_progress']);
        $wf = $this->svc(IssueWorkflowService::class);
        try {
            $wf->transition($this->dev, $id, 'resolved', $this->v($id));
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame('use_fix_flow', $e->errorCode);
        }
        $this->pdo->exec("UPDATE issues SET status = 'resolved', fix_revision = 1, fix_ref = 'abcdef1' WHERE id = {$id}");
        try {
            $wf->transition($this->qa, $id, 'verified', $this->v($id));
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame('use_test_flow', $e->errorCode);
        }
    }

    public function testRoleRestrictionsOnTransitions(): void
    {
        $id = $this->issue($this->p, $this->dev, ['status' => 'verified', 'fix_revision' => 1]);
        $wf = $this->svc(IssueWorkflowService::class);
        $this->expectException(ForbiddenException::class);
        $wf->transition($this->dev, $id, 'closed', $this->v($id)); // developer không được đóng lỗi
    }

    public function testDeveloperCannotRecordTestAndTesterCannotRecordFix(): void
    {
        $id = $this->issue($this->p, $this->dev, ['status' => 'in_progress']);
        $wf = $this->svc(IssueWorkflowService::class);
        try {
            $wf->recordFix($this->qa, $id, 'abcdef1', $this->v($id));
            $this->fail();
        } catch (ForbiddenException) {
        }
        $this->pdo->exec("UPDATE issues SET status = 'resolved', fix_revision = 1 WHERE id = {$id}");
        $this->expectException(ForbiddenException::class);
        $wf->recordTest($this->dev, $id, 'passed', 1, null);
    }

    public function testStaleVersionOnTransitionIsConflict(): void
    {
        $id = $this->issue($this->p, $this->dev);
        $wf = $this->svc(IssueWorkflowService::class);
        $v = $this->v($id);
        $wf->transition($this->dev, $id, 'in_progress', $v);
        $this->expectException(ConflictException::class);
        $wf->transition($this->pm, $id, 'in_progress', $v);
    }

    public function testInvalidFixReferenceRejected(): void
    {
        $id = $this->issue($this->p, $this->dev, ['status' => 'in_progress']);
        $this->expectException(\ReleaseHub\Core\ValidationException::class);
        $this->svc(IssueWorkflowService::class)->recordFix($this->dev, $id, 'javascript:alert(1)', $this->v($id));
    }

    public function testTaskWorkflowNeedsNoReproductionFields(): void
    {
        $id = $this->issue($this->p, $this->dev, ['type' => 'task']);
        $wf = $this->svc(IssueWorkflowService::class);
        $wf->transition($this->dev, $id, 'in_progress', $this->v($id));
        $wf->transition($this->dev, $id, 'resolved', $this->v($id));
        $wf->transition($this->pm, $id, 'closed', $this->v($id));
        $this->assertSame('closed', $this->fetchIssue($id)['status']);
        try {
            $wf->recordTest($this->qa, $id, 'passed', 1, null);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame('not_a_bug', $e->errorCode);
        }
    }

    public function testRuleTableIsPublishedAndConsistent(): void
    {
        foreach (['bug', 'task'] as $type) {
            foreach (WorkflowRules::rules($type) as $r) {
                $this->assertNotEmpty($r['roles']);
                $this->assertNotSame($r['from'], $r['to']);
            }
        }
        $this->assertNull(WorkflowRules::find('bug', 'open', 'closed'));
        $this->assertNotNull(WorkflowRules::find('bug', 'closed', 'reopened'));
    }
}
