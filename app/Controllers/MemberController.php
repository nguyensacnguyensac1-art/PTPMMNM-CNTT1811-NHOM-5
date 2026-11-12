<?php
declare(strict_types=1);

namespace ReleaseHub\Controllers;

use ReleaseHub\Core\{Controller, Request, Response};
use ReleaseHub\Services\{MemberService, ProjectService};

final class MemberController extends Controller
{
    public function __construct(private MemberService $members, private ProjectService $projects)
    {
    }

    public function index(Request $r, array $p, ?array $user): Response
    {
        $id = (int) $p['id'];
        $h = $this->projects->header($user, $id);
        return $this->render($r, $user, 'projects/members', $h + [
            'title' => 'Thành viên – ' . $h['project']['code'], 'nav' => 'projects', 'tab' => 'members',
            'list' => $this->members->list($user, $id), 'candidates' => $h['can']['members'] ? $this->members->candidates($user, $id) : [],
        ]);
    }

    public function add(Request $r, array $p, ?array $user): Response
    {
        $this->members->add($user, (int) $p['id'], $r->str('email'), $r->str('role'), $r->ip);
        return $this->json(['ok' => true], 201);
    }

    public function role(Request $r, array $p, ?array $user): Response
    {
        $this->members->changeRole($user, (int) $p['id'], (int) $p['uid'], $r->str('role'), $r->ip);
        return $this->json(['ok' => true]);
    }

    public function remove(Request $r, array $p, ?array $user): Response
    {
        $this->members->remove($user, (int) $p['id'], (int) $p['uid'], $r->ip);
        return $this->json(['ok' => true]);
    }
}
