<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

use ReleaseHub\Repositories\ProjectRepository;

abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function render(Request $r, ?array $user, string $view, array $data = [], int $status = 200): Response
    {
        $sidebar = [];
        if ($user) {
            $sidebar = Container::get(ProjectRepository::class)->simpleList(!empty($user['is_admin']) ? null : (int) $user['id']);
        }
        $html = View::render($view, $data + [
            'user' => $user,
            'sidebarProjects' => $sidebar,
            'flash' => Session::pullFlash(),
            'nav' => $data['nav'] ?? '',
            'title' => $data['title'] ?? 'ReleaseHub',
        ], $data['layout'] ?? 'layouts/app');
        return Response::html($html, $status);
    }

    /** @param mixed $data */
    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }
}
