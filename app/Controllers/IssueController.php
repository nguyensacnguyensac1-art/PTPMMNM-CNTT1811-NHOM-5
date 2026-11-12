<?php
declare(strict_types=1);

namespace ReleaseHub\Controllers;

use ReleaseHub\Core\{Controller, Request, Response};
use ReleaseHub\Repositories\ReleaseRepository;
use ReleaseHub\Services\{IssueService, IssueWorkflowService, MemberService, ProjectService};

final class IssueController extends Controller
{
    public function __construct(
        private IssueService $issues,
        private IssueWorkflowService $workflow,
        private ProjectService $projects,
        private MemberService $members,
        private ReleaseRepository $releases
    ) {
    }

    /** @return array<string,mixed> */
    private function filters(Request $r): array
    {
        $f = [];
        foreach (['q', 'type', 'status', 'priority', 'severity', 'assignee_id', 'release_id'] as $k) {
            $v = $r->str($k);
            if ($v !== '') {
                $f[$k] = $v;
            }
        }
        if ($r->str('open_only') === '1') {
            $f['open_only'] = 1;
        }
        return $f;
    }

    public function index(Request $r, array $p, ?array $user): Response
    {
        $id = (int) $p['id'];
        $h = $this->projects->header($user, $id);
        $res = $this->listFor($r, $user, $id);
        return $this->render($r, $user, 'projects/issues', $h + $res + [
            'title' => 'Công việc – ' . $h['project']['code'], 'nav' => 'projects', 'tab' => 'issues',
            'members' => $this->members->list($user, $id), 'releaseList' => $this->releases->listByProject($id), 'scopeAll' => false,
        ]);
    }

    public function all(Request $r, array $p, ?array $user): Response
    {
        $res = $this->listFor($r, $user, null);
        return $this->render($r, $user, 'projects/issues', $res + [
            'title' => 'Tất cả công việc', 'nav' => 'issues', 'project' => null, 'tab' => '', 'can' => [], 'members' => [], 'releaseList' => [], 'scopeAll' => true,
        ]);
    }

    /** @return array<string,mixed> */
    private function listFor(Request $r, array $user, ?int $projectId): array
    {
        $filters = $this->filters($r);
        $sort = $r->str('sort', 'updated_at');
        $dir = $r->str('dir', 'desc');
        $page = max(1, (int) $r->input('page', 1));
        $per = (int) $r->input('per_page', 20);
        $res = $this->issues->search($user, $projectId, $filters, $sort, $dir, $page, $per ?: 20);
        return ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'result' => $res];
    }

    public function board(Request $r, array $p, ?array $user): Response
    {
        $id = (int) $p['id'];
        $h = $this->projects->header($user, $id);
        return $this->render($r, $user, 'projects/board', $h + [
            'title' => 'Kanban – ' . $h['project']['code'], 'nav' => 'projects', 'tab' => 'board', 'cards' => $this->issues->board($user, $id),
        ]);
    }

    public function create(Request $r, array $p, ?array $user): Response
    {
        $id = (int) $p['id'];
        $h = $this->projects->header($user, $id);
        if (!$h['can']['create_issue']) {
            throw new \ReleaseHub\Core\ForbiddenException('Vai trò của bạn không được tạo công việc.');
        }
        return $this->render($r, $user, 'issues/form', $h + [
            'title' => 'Tạo công việc', 'nav' => 'projects', 'tab' => 'issues', 'members' => $this->members->list($user, $id), 'defaultType' => $r->str('type', 'bug'),
        ]);
    }

    public function store(Request $r, array $p, ?array $user): Response
    {
        $issue = $this->issues->create($user, (int) $p['id'], $r->body, $r->ip);
        return $this->json(['ok' => true, 'issue' => $issue, 'redirect' => url('issues/' . $issue['id'])], 201);
    }

    public function show(Request $r, array $p, ?array $user): Response
    {
        $d = $this->issues->detail($user, (int) $p['id']);
        return $this->render($r, $user, 'issues/show', $d + [
            'title' => $d['issue']['code'] . ' – ' . $d['issue']['title'], 'nav' => 'projects', 'tab' => 'issues',
            'candidates' => $d['can']['dependency'] ? $this->depCandidates($d) : [],
        ]);
    }

    /** @param array<string,mixed> $d @return array<int,array<string,mixed>> */
    private function depCandidates(array $d): array
    {
        $st = \ReleaseHub\Core\Database::connection()->prepare('SELECT id, code, title FROM issues WHERE project_id = ? AND id <> ? ORDER BY number');
        $st->execute([$d['issue']['project_id'], $d['issue']['id']]);
        return $st->fetchAll();
    }

    public function update(Request $r, array $p, ?array $user): Response
    {
        $issue = $this->issues->update($user, (int) $p['id'], $r->body, $r->input('expected_version'), $r->ip);
        return $this->json(['ok' => true, 'issue' => $issue]);
    }

    public function current(Request $r, array $p, ?array $user): Response
    {
        $d = $this->issues->detail($user, (int) $p['id']);
        return $this->json(['ok' => true, 'issue' => $d['issue']]);
    }

    public function transition(Request $r, array $p, ?array $user): Response
    {
        $issue = $this->workflow->transition($user, (int) $p['id'], $r->str('to'), $r->input('expected_version'), $r->str('note') ?: null, $r->ip);
        return $this->json(['ok' => true, 'issue' => $issue]);
    }

    public function fix(Request $r, array $p, ?array $user): Response
    {
        $issue = $this->workflow->recordFix($user, (int) $p['id'], $r->str('fix_ref'), $r->input('expected_version'), $r->str('note') ?: null, $r->ip);
        return $this->json(['ok' => true, 'issue' => $issue]);
    }

    public function test(Request $r, array $p, ?array $user): Response
    {
        $res = $this->workflow->recordTest($user, (int) $p['id'], $r->str('result'), $r->input('fix_revision'), $r->str('note') ?: null, $r->ip);
        return $this->json(['ok' => true] + $res);
    }

    public function comment(Request $r, array $p, ?array $user): Response
    {
        return $this->json(['ok' => true] + $this->issues->addComment($user, (int) $p['id'], $r->str('body'), $r->ip), 201);
    }
}
