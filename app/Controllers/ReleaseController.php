<?php
declare(strict_types=1);

namespace ReleaseHub\Controllers;

use ReleaseHub\Core\{Controller, Request, Response};
use ReleaseHub\Services\{MemberService, ProjectService, ReleaseReadinessService, ReleaseService, ReleaseSnapshotService};

final class ReleaseController extends Controller
{
    public function __construct(
        private ReleaseService $releases,
        private ReleaseSnapshotService $snapshots,
        private ReleaseReadinessService $readiness,
        private ProjectService $projects,
        private MemberService $members
    ) {
    }

    public function index(Request $r, array $p, ?array $user): Response
    {
        $id = (int) $p['id'];
        $h = $this->projects->header($user, $id);
        return $this->render($r, $user, 'projects/releases', $h + [
            'title' => 'Phiên bản – ' . $h['project']['code'], 'nav' => 'projects', 'tab' => 'releases',
            'list' => $this->releases->listForProject($user, $id), 'members' => $this->members->list($user, $id),
        ]);
    }

    public function history(Request $r, array $p, ?array $user): Response
    {
        $id = (int) $p['id'];
        $h = $this->projects->header($user, $id);
        $released = array_values(array_filter($this->releases->listForProject($user, $id), static fn ($x) => $x['status'] === 'released'));
        usort($released, static fn ($a, $b) => strcmp((string) $b['released_at'], (string) $a['released_at']));
        return $this->render($r, $user, 'projects/release_history', $h + [
            'title' => 'Lịch sử phát hành – ' . $h['project']['code'], 'nav' => 'projects', 'tab' => 'releases', 'released' => $released,
        ]);
    }

    public function show(Request $r, array $p, ?array $user): Response
    {
        $d = $this->releases->detail($user, (int) $p['id']);
        $h = $this->projects->header($user, (int) $d['release']['project_id']);
        return $this->render($r, $user, 'releases/show', $d + [
            'title' => $d['release']['project_code'] . ' ' . $d['release']['name'], 'nav' => 'projects', 'tab' => 'releases', 'role' => $h['role'], 'can' => $h['can'],
            'members' => $this->members->list($user, (int) $d['release']['project_id']),
        ]);
    }

    public function store(Request $r, array $p, ?array $user): Response
    {
        $rel = $this->releases->create($user, (int) $p['id'], $r->body, $r->ip);
        return $this->json(['ok' => true, 'redirect' => url('releases/' . $rel['id']), 'release' => $rel], 201);
    }

    public function update(Request $r, array $p, ?array $user): Response
    {
        $rel = $this->releases->update($user, (int) $p['id'], $r->body, $r->ip);
        return $this->json(['ok' => true, 'release' => $rel]);
    }

    public function attach(Request $r, array $p, ?array $user): Response
    {
        $this->releases->attach($user, (int) $p['id'], (int) $r->input('issue_id', 0), filter_var($r->input('required', true), FILTER_VALIDATE_BOOLEAN), $r->ip);
        return $this->json(['ok' => true], 201);
    }

    public function detach(Request $r, array $p, ?array $user): Response
    {
        $this->releases->detach($user, (int) $p['id'], (int) $p['iid'], $r->ip);
        return $this->json(['ok' => true]);
    }

    public function required(Request $r, array $p, ?array $user): Response
    {
        $this->releases->setRequired($user, (int) $p['id'], (int) $p['iid'], filter_var($r->input('required', true), FILTER_VALIDATE_BOOLEAN));
        return $this->json(['ok' => true]);
    }

    public function readiness(Request $r, array $p, ?array $user): Response
    {
        $d = $this->releases->detail($user, (int) $p['id']);
        return $this->json(['ok' => true, 'readiness' => $d['readiness'] ?? $this->readiness->evaluate((int) $p['id'])]);
    }

    public function finalize(Request $r, array $p, ?array $user): Response
    {
        $rel = $this->snapshots->finalize($user, (int) $p['id'], $r->ip);
        return $this->json(['ok' => true, 'release' => $rel, 'redirect' => url('releases/' . $rel['id'])]);
    }

    public function notesMarkdown(Request $r, array $p, ?array $user): Response
    {
        $d = $this->releases->detail($user, (int) $p['id']);
        $md = $this->snapshots->markdown($user, (int) $p['id']);
        return Response::download($md, 'release-notes-' . strtolower($d['release']['project_code']) . '-' . $d['release']['name'] . '.md', 'text/markdown; charset=utf-8');
    }

    public function notesJson(Request $r, array $p, ?array $user): Response
    {
        $d = $this->releases->detail($user, (int) $p['id']);
        $json = (string) json_encode($this->snapshots->exportJson($user, (int) $p['id']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return Response::download($json, 'release-notes-' . strtolower($d['release']['project_code']) . '-' . $d['release']['name'] . '.json', 'application/json; charset=utf-8');
    }
}
