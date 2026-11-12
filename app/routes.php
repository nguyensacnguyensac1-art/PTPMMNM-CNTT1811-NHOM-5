<?php
declare(strict_types=1);

use ReleaseHub\Controllers\{AuthController, DashboardController, DependencyController, IssueController, MemberController, ProjectController, ReleaseController};
use ReleaseHub\Core\Router;

return static function (Router $r): void {
    // Xác thực
    $r->get('/login', [AuthController::class, 'form'], true);
    $r->post('/login', [AuthController::class, 'login'], true);
    $r->post('/logout', [AuthController::class, 'logout']);

    // Dashboard & danh sách chung
    $r->get('/', [DashboardController::class, 'index']);
    $r->get('/api/dashboard', [DashboardController::class, 'data']);
    $r->get('/issues', [IssueController::class, 'all']);

    // Dự án
    $r->get('/projects', [ProjectController::class, 'index']);
    $r->get('/projects/new', [ProjectController::class, 'create']);
    $r->post('/api/projects', [ProjectController::class, 'store']);
    $r->get('/projects/{id:\d+}', [ProjectController::class, 'show']);
    $r->get('/projects/{id:\d+}/edit', [ProjectController::class, 'edit']);
    $r->post('/api/projects/{id:\d+}', [ProjectController::class, 'update']);
    $r->post('/api/projects/{id:\d+}/archive', [ProjectController::class, 'archive']);
    $r->get('/projects/{id:\d+}/issues', [IssueController::class, 'index']);
    $r->get('/projects/{id:\d+}/issues/new', [IssueController::class, 'create']);
    $r->get('/projects/{id:\d+}/board', [IssueController::class, 'board']);
    $r->get('/projects/{id:\d+}/graph', [DependencyController::class, 'page']);
    $r->get('/projects/{id:\d+}/releases', [ReleaseController::class, 'index']);
    $r->get('/projects/{id:\d+}/release-history', [ReleaseController::class, 'history']);
    $r->get('/projects/{id:\d+}/members', [MemberController::class, 'index']);
    $r->get('/projects/{id:\d+}/history', [ProjectController::class, 'history']);

    // Thành viên
    $r->post('/api/projects/{id:\d+}/members', [MemberController::class, 'add']);
    $r->post('/api/projects/{id:\d+}/members/{uid:\d+}/role', [MemberController::class, 'role']);
    $r->post('/api/projects/{id:\d+}/members/{uid:\d+}/remove', [MemberController::class, 'remove']);

    // Công việc / lỗi
    $r->post('/api/projects/{id:\d+}/issues', [IssueController::class, 'store']);
    $r->get('/issues/{id:\d+}', [IssueController::class, 'show']);
    $r->post('/api/issues/{id:\d+}', [IssueController::class, 'update']);
    $r->post('/api/issues/{id:\d+}/transition', [IssueController::class, 'transition']);
    $r->post('/api/issues/{id:\d+}/fix', [IssueController::class, 'fix']);
    $r->post('/api/issues/{id:\d+}/tests', [IssueController::class, 'test']);
    $r->post('/api/issues/{id:\d+}/comments', [IssueController::class, 'comment']);
    $r->get('/api/issues/{id:\d+}', [IssueController::class, 'current']);

    // Phụ thuộc
    $r->post('/api/issues/{id:\d+}/dependencies', [DependencyController::class, 'add']);
    $r->post('/api/dependencies/{id:\d+}/delete', [DependencyController::class, 'remove']);
    $r->get('/api/projects/{id:\d+}/graph', [DependencyController::class, 'data']);

    // Phát hành
    $r->post('/api/projects/{id:\d+}/releases', [ReleaseController::class, 'store']);
    $r->get('/releases/{id:\d+}', [ReleaseController::class, 'show']);
    $r->post('/api/releases/{id:\d+}', [ReleaseController::class, 'update']);
    $r->post('/api/releases/{id:\d+}/items', [ReleaseController::class, 'attach']);
    $r->post('/api/releases/{id:\d+}/items/{iid:\d+}/remove', [ReleaseController::class, 'detach']);
    $r->post('/api/releases/{id:\d+}/items/{iid:\d+}/required', [ReleaseController::class, 'required']);
    $r->get('/api/releases/{id:\d+}/readiness', [ReleaseController::class, 'readiness']);
    $r->post('/api/releases/{id:\d+}/finalize', [ReleaseController::class, 'finalize']);
    $r->get('/releases/{id:\d+}/notes.md', [ReleaseController::class, 'notesMarkdown']);
    $r->get('/releases/{id:\d+}/notes.json', [ReleaseController::class, 'notesJson']);
};
