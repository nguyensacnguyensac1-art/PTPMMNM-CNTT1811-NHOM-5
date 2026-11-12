<?php
declare(strict_types=1);

namespace ReleaseHub\Controllers;

use ReleaseHub\Core\{Controller, Request, Response};
use ReleaseHub\Services\DashboardService;

final class DashboardController extends Controller
{
    public function __construct(private DashboardService $dash)
    {
    }

    public function index(Request $r, array $p, ?array $user): Response
    {
        return $this->render($r, $user, 'dashboard/index', ['title' => 'Tổng quan', 'nav' => 'dashboard', 'd' => $this->dash->summary($user)]);
    }

    public function data(Request $r, array $p, ?array $user): Response
    {
        return $this->json($this->dash->summary($user));
    }
}
