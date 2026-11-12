<?php
declare(strict_types=1);

namespace ReleaseHub\Controllers;

use ReleaseHub\Core\{Config, Controller, Request, Response, Session, ValidationException};
use ReleaseHub\Database\Seeders\DatabaseSeeder;
use ReleaseHub\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(private AuthService $auth)
    {
    }

    public function form(Request $r, array $p, ?array $user): Response
    {
        if ($user) {
            return $this->redirect('');
        }
        return $this->render($r, null, 'auth/login', [
            'layout' => 'layouts/auth', 'title' => 'Đăng nhập', 'errors' => [], 'old' => ['email' => ''], 'next' => $r->str('next'),
            'demo' => Config::demoMode(),
        ]);
    }

    public function login(Request $r, array $p, ?array $user): Response
    {
        try {
            $u = $this->auth->attempt($r->str('email'), (string) $r->input('password', ''), $r->ip);
        } catch (ValidationException $e) {
            return $this->render($r, null, 'auth/login', [
                'layout' => 'layouts/auth', 'title' => 'Đăng nhập', 'errors' => $e->errors, 'old' => ['email' => $r->str('email')],
                'next' => $r->str('next'), 'demo' => Config::demoMode(),
            ], 422);
        }
        $this->auth->startSession($u);
        $next = $r->str('next');
        // Chỉ cho phép chuyển hướng nội bộ (chống open redirect)
        $safe = ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//') && !str_contains($next, '\\')) ? $next : '';
        return $this->redirect($safe);
    }

    public function logout(Request $r, array $p, ?array $user): Response
    {
        Session::destroy();
        Session::start();
        Session::flash('success', 'Bạn đã đăng xuất.');
        return $this->redirect('login');
    }
}
