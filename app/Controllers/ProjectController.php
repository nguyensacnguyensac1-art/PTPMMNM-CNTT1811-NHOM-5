<?php
declare(strict_types=1);

namespace ReleaseHub\Controllers;

use ReleaseHub\Core\{Controller, Request, Response};
use ReleaseHub\Services\{MemberService, ProjectService, ReleaseService};

final class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects, private ReleaseService $releases, private MemberService $members)
    {
    }

    public function index(Request $r, array $p, ?array $user): Response
    {
        $filters = ['q' => $r->str('q'), 'status' => $r->str('status', 'active')];
        return $this->render($r, $user, 'projects/index', [
            'title' => 'Dự án', 'nav' => 'projects', 'items' => $this->projects->listVisible($user, $filters), 'filters' => $filters,
            'canCreate' => !empty($user['is_admin']) || $this->canCreate($user),
        ]);
    }

    public function create(Request $r, array $p, ?array $user): Response
    {
        return $this->render($r, $user, 'projects/form', ['title' => 'Tạo dự án', 'nav' => 'projects', 'project' => null, 'members' => []]);
    }

    public function store(Request $r, array $p, ?array $user): Response
    {
        $project = $this->projects->create($user, $r->body, $r->ip);
        return $this->json(['ok' => true, 'redirect' => url('projects/' . $project['id']), 'project' => $project], 201);
    }

    public function show(Request $r, array $p, ?array $user): Response
    {
        $id = (int) $p['id'];
        $ov = $this->projects->overview($user, $id);
        return $this->render($r, $user, 'projects/show', $ov + [
            'title' => $ov['project']['name'], 'nav' => 'projects', 'tab' => 'overview',
            'releases' => $this->releases->listForProject($user, $id),
        ]);
    }

    public function edit(Request $r, array $p, ?array $user): Response
    {
        $h = $this->projects->header($user, (int) $p['id']);
        if (!$h['can']['manage']) {
            throw new \ReleaseHub\Core\ForbiddenException('Bạn không có quyền sửa dự án này.');
        }
        return $this->render($r, $user, 'projects/form', $h + [
            'title' => 'Sửa dự án', 'nav' => 'projects', 'tab' => 'settings', 'members' => $this->members->list($user, (int) $p['id']),
        ]);
    }

    public function update(Request $r, array $p, ?array $user): Response
    {
        $project = $this->projects->update($user, (int) $p['id'], $r->body, $r->ip);
        return $this->json(['ok' => true, 'redirect' => url('projects/' . $project['id']), 'project' => $project]);
    }

    public function archive(Request $r, array $p, ?array $user): Response
    {
        $archived = filter_var($r->input('archived', true), FILTER_VALIDATE_BOOLEAN);
        $this->projects->setArchived($user, (int) $p['id'], $archived, $r->ip);
        return $this->json(['ok' => true, 'archived' => $archived]);
    }

    public function history(Request $r, array $p, ?array $user): Response
    {
        $id = (int) $p['id'];
        $page = max(1, (int) $r->input('page', 1));
        $res = $this->projects->history($user, $id, $page, 30);
        return $this->render($r, $user, 'projects/history', $this->projects->header($user, $id) + [
            'title' => 'Lịch sử dự án', 'nav' => 'projects', 'tab' => 'history', 'rows' => $res['rows'],
            'page' => $page, 'pages' => max(1, (int) ceil($res['total'] / 30)), 'total' => $res['total'],
        ]);
    }

    /** @param array<string,mixed> $user */
    private function canCreate(array $user): bool
    {
        return (bool) \ReleaseHub\Core\Database::connection()->query("SELECT 1 FROM project_members WHERE role_code = 'project_manager' AND user_id = " . (int) $user['id'] . ' LIMIT 1')->fetchColumn();
    }
}
