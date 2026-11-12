<?php
declare(strict_types=1);

namespace ReleaseHub\Controllers;

use ReleaseHub\Core\{Controller, Database, Request, Response};
use ReleaseHub\Services\{DependencyService, ProjectService};

final class DependencyController extends Controller
{
    public function __construct(private DependencyService $deps, private ProjectService $projects)
    {
    }

    public function page(Request $r, array $p, ?array $user): Response
    {
        $id = (int) $p['id'];
        $h = $this->projects->header($user, $id);
        $st = Database::connection()->prepare('SELECT id, code, title FROM issues WHERE project_id = ? ORDER BY number');
        $st->execute([$id]);
        return $this->render($r, $user, 'projects/graph', $h + [
            'title' => 'Phụ thuộc – ' . $h['project']['code'], 'nav' => 'projects', 'tab' => 'graph',
            'graph' => $this->deps->graph($user, $id), 'issueOptions' => $st->fetchAll(),
            'canManage' => $h['role'] === 'system_admin' || in_array($h['role'], ['project_manager', 'release_manager', 'developer'], true),
        ]);
    }

    public function data(Request $r, array $p, ?array $user): Response
    {
        return $this->json(['ok' => true] + $this->deps->graph($user, (int) $p['id']));
    }

    public function add(Request $r, array $p, ?array $user): Response
    {
        $res = $this->deps->add($user, (int) $p['id'], (int) $r->input('depends_on_id', 0), $r->ip);
        return $this->json(['ok' => true] + $res, 201);
    }

    public function remove(Request $r, array $p, ?array $user): Response
    {
        $this->deps->remove($user, (int) $p['id'], $r->ip);
        return $this->json(['ok' => true]);
    }
}
