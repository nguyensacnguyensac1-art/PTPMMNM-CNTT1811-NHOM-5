<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

use ReleaseHub\Repositories\UserRepository;

/** Front controller: định tuyến, CSRF, xác thực và xử lý lỗi tập trung. */
final class App
{
    public function run(): void
    {
        Session::start();
        $request = Request::capture();
        $response = $this->handle($request);
        foreach (['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'SAMEORIGIN', 'Referrer-Policy' => 'same-origin'] as $k => $v) {
            $response->headers[$k] ??= $v;
        }
        $response->send();
    }

    public function handle(Request $request): Response
    {
        $router = new Router();
        (require Config::rootPath('app/routes.php'))($router);
        $user = null;
        try {
            $match = $router->match($request->method === 'HEAD' ? 'GET' : $request->method, $request->path);
            if ($match === null) {
                if ($router->pathExists($request->path)) {
                    throw new HttpException(405, 'Phương thức không được hỗ trợ.', 'method_not_allowed');
                }
                throw new NotFoundException('Trang bạn tìm không tồn tại.');
            }
            if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true) && !Csrf::valid($request)) {
                throw new HttpException(403, 'Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ (CSRF). Hãy tải lại trang và thử lại.', 'csrf_failed');
            }
            $userId = Session::get('user_id');
            if ($userId) {
                $user = Container::get(UserRepository::class)->find((int) $userId);
                if ($user && (int) $user['is_active'] !== 1) {
                    $user = null;
                }
                if (!$user) {
                    Session::destroy();
                    Session::start();
                }
            }
            if (!$match['public'] && !$user) {
                if ($request->wantsJson()) {
                    throw new HttpException(401, 'Bạn cần đăng nhập.', 'unauthenticated');
                }
                $next = $request->path !== '/' ? '?next=' . rawurlencode($request->path) : '';
                return Response::redirect(url('login') . $next);
            }
            [$class, $method] = $match['handler'];
            $controller = Container::get($class);
            return $controller->$method($request, $match['params'], $user);
        } catch (HttpException $e) {
            return $this->error($request, $user, $e->status, $e->getMessage(), $e->errorCode, $e->extra);
        } catch (\Throwable $e) {
            Logger::error('Unhandled ' . $request->method . ' ' . $request->path, $e);
            $msg = Config::debug() ? $e->getMessage() : 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.';
            return $this->error($request, $user, 500, $msg, 'server_error', []);
        }
    }

    /** @param array<string,mixed> $extra */
    private function error(Request $request, ?array $user, int $status, string $message, string $code, array $extra): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => $code, 'message' => $message] + $extra, $status);
        }
        try {
            $html = View::render('errors/error', ['status' => $status, 'message' => $message, 'user' => $user, 'title' => 'Lỗi ' . $status, 'flash' => [], 'sidebarProjects' => [], 'nav' => ''], $user ? 'layouts/app' : 'layouts/auth');
        } catch (\Throwable) {
            $html = '<h1>' . $status . '</h1><p>' . htmlspecialchars($message) . '</p>';
        }
        return Response::html($html, $status);
    }
}
