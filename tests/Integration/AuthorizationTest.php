<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Integration;

use ReleaseHub\Core\ForbiddenException;
use ReleaseHub\Services\DashboardService;
use ReleaseHub\Services\DependencyService;
use ReleaseHub\Services\IssueService;
use ReleaseHub\Services\IssueWorkflowService;
use ReleaseHub\Services\MemberService;
use ReleaseHub\Services\ProjectService;
use ReleaseHub\Services\ReleaseService;
use ReleaseHub\Services\ReleaseSnapshotService;
use ReleaseHub\Tests\DatabaseTestCase;

final class AuthorizationTest extends DatabaseTestCase
{
    private array $a;  // chỉ thuộc A
    private array $b;  // chỉ thuộc B
    private array $multi; // PM ở A, Viewer ở B
    private int $pa;
    private int $pb;
    private int $issueB;
    private int $issueA;
    private int $relB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->user('Chỉ A');
        $this->b = $this->user('Chỉ B');
        $this->multi = $this->user('Nhiều dự án');
        $this->pa = $this->project('PAA');
        $this->pb = $this->project('PBB');
        $this->member($this->pa, $this->a, 'project_manager');
        $this->member($this->pb, $this->b, 'project_manager');
        $this->member($this->pa, $this->multi, 'project_manager');
        $this->member($this->pb, $this->multi, 'viewer');
        $this->issueA = $this->issue($this->pa, $this->a, ['title' => 'Việc riêng A']);
        $this->issueB = $this->issue($this->pb, $this->b, ['title' => 'BÍ MẬT CỦA B', 'status' => 'closed', 'type' => 'task']);
        $this->relB = $this->release($this->pb, $this->b);
        $this->attach($this->pb, $this->relB, $this->issueB, $this->b);
    }

    /** PHÉP THỬ BẮT BUỘC 4: thành viên chỉ thuộc A không sửa được công việc riêng của B bằng cách đổi ID. */
    public function testMemberOfAOnlyCannotEditIssueOfB(): void
    {
        $before = $this->fetchIssue($this->issueB);
        try {
            $this->svc(IssueService::class)->update($this->a, $this->issueB, ['title' => 'Đã bị sửa'], 1);
            $this->fail('Phải bị từ chối');
        } catch (ForbiddenException $e) {
            $this->assertSame(403, $e->status);
        }
        $this->assertSame($before, $this->fetchIssue($this->issueB));
    }

    public function testEveryWriteAndReadPathIsIsolated(): void
    {
        $a = $this->a;
        $wf = $this->svc(IssueWorkflowService::class);
        $calls = [
            'detail' => fn () => $this->svc(IssueService::class)->detail($a, $this->issueB),
            'comment' => fn () => $this->svc(IssueService::class)->addComment($a, $this->issueB, 'xin chào'),
            'transition' => fn () => $wf->transition($a, $this->issueB, 'reopened', 1),
            'fix' => fn () => $wf->recordFix($a, $this->issueB, 'abcdef1', 1),
            'test' => fn () => $wf->recordTest($a, $this->issueB, 'passed', 1, null),
            'dep_add' => fn () => $this->svc(DependencyService::class)->add($a, $this->issueB, $this->issueB),
            'dep_view' => fn () => $this->svc(DependencyService::class)->forIssue($a, $this->issueB),
            'graph' => fn () => $this->svc(DependencyService::class)->graph($a, $this->pb),
            'list_in_project' => fn () => $this->svc(IssueService::class)->search($a, $this->pb, [], 'code', 'asc', 1),
            'board' => fn () => $this->svc(IssueService::class)->board($a, $this->pb),
            'release_detail' => fn () => $this->svc(ReleaseService::class)->detail($a, $this->relB),
            'release_create' => fn () => $this->svc(ReleaseService::class)->create($a, $this->pb, ['name' => 'v9']),
            'release_attach' => fn () => $this->svc(ReleaseService::class)->attach($a, $this->relB, $this->issueB),
            'finalize' => fn () => $this->svc(ReleaseSnapshotService::class)->finalize($a, $this->relB),
            'export' => fn () => $this->svc(ReleaseSnapshotService::class)->exportJson($a, $this->relB),
            'members' => fn () => $this->svc(MemberService::class)->list($a, $this->pb),
            'add_member' => fn () => $this->svc(MemberService::class)->add($a, $this->pb, $this->a['email'], 'viewer'),
            'project_update' => fn () => $this->svc(ProjectService::class)->update($a, $this->pb, ['name' => 'Chiếm quyền']),
            'archive' => fn () => $this->svc(ProjectService::class)->setArchived($a, $this->pb, true),
            'overview' => fn () => $this->svc(ProjectService::class)->overview($a, $this->pb),
            'create_issue' => fn () => $this->svc(IssueService::class)->create($a, $this->pb, ['type' => 'task', 'title' => 'xâm nhập']),
        ];
        foreach ($calls as $name => $fn) {
            try {
                $fn();
                $this->fail("Đường '{$name}' không bị chặn");
            } catch (ForbiddenException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testListsAndDashboardDoNotLeakOtherProjects(): void
    {
        $res = $this->svc(IssueService::class)->search($this->a, null, [], 'code', 'asc', 1, 50);
        $titles = array_column($res['rows'], 'title');
        $this->assertContains('Việc riêng A', $titles);
        $this->assertNotContains('BÍ MẬT CỦA B', $titles);
        $this->assertSame(1, $res['total']);

        $res2 = $this->svc(IssueService::class)->search($this->a, null, ['q' => 'BÍ MẬT'], 'code', 'asc', 1, 50);
        $this->assertSame(0, $res2['total'], 'Tìm kiếm không được lộ dữ liệu dự án khác');

        $projects = $this->svc(ProjectService::class)->listVisible($this->a);
        $this->assertSame(['PAA'], array_column($projects, 'code'));

        $d = $this->svc(DashboardService::class)->summary($this->a);
        $this->assertSame(1, $d['kpi']['total_items']);
        $this->assertSame(['PAA'], $d['chart_projects']['labels']);
        $this->assertSame([], $d['upcoming_releases']);

        $dB = $this->svc(DashboardService::class)->summary($this->b);
        $this->assertSame(1, $dB['kpi']['total_items']);
        $this->assertCount(1, $dB['upcoming_releases']);
    }

    public function testRolesDifferPerProjectForSameUser(): void
    {
        $edit = fn (int $issueId) => $this->svc(IssueService::class)->update($this->multi, $issueId, ['title' => 'Sửa bởi nhiều dự án'], 1);
        $edit($this->issueA); // PM ở A: được
        $this->assertSame('Sửa bởi nhiều dự án', $this->fetchIssue($this->issueA)['title']);
        $this->expectException(ForbiddenException::class);
        $edit($this->issueB); // Viewer ở B: không được
    }

    public function testViewerIsReadOnlyEverywhere(): void
    {
        $viewer = $this->multi; // Viewer ở B
        $this->svc(IssueService::class)->detail($viewer, $this->issueB); // đọc được
        foreach ([
            fn () => $this->svc(IssueService::class)->addComment($viewer, $this->issueB, 'x'),
            fn () => $this->svc(IssueService::class)->create($viewer, $this->pb, ['type' => 'task', 'title' => 'abc']),
            fn () => $this->svc(IssueWorkflowService::class)->transition($viewer, $this->issueB, 'reopened', 1),
            fn () => $this->svc(ReleaseService::class)->create($viewer, $this->pb, ['name' => 'v9']),
            fn () => $this->svc(MemberService::class)->add($viewer, $this->pb, $this->a['email'], 'viewer'),
        ] as $fn) {
            try {
                $fn();
                $this->fail('Viewer không được ghi');
            } catch (ForbiddenException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testSystemAdminSeesEverythingButIsolationCaseUsesNonAdmin(): void
    {
        $admin = $this->user('Admin', true);
        $this->assertSame(2, $this->svc(IssueService::class)->search($admin, null, [], 'code', 'asc', 1)['total']);
        $this->assertCount(2, $this->svc(ProjectService::class)->listVisible($admin));
        $this->svc(IssueService::class)->update($admin, $this->issueB, ['title' => 'Admin sửa'], 1);
        $this->assertSame('Admin sửa', $this->fetchIssue($this->issueB)['title']);
    }

    public function testLastProjectManagerCannotBeRemoved(): void
    {
        $this->expectException(\ReleaseHub\Core\ConflictException::class);
        $this->svc(MemberService::class)->remove($this->b, $this->pb, (int) $this->b['id']);
    }
}
