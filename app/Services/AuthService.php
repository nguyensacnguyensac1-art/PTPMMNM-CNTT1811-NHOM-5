<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use ReleaseHub\Core\Session;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Repositories\HistoryRepository;
use ReleaseHub\Repositories\UserRepository;

final class AuthService
{
    // Băm giả để thời gian phản hồi không tiết lộ email có tồn tại hay không.
    private const DUMMY_HASH = '$2y$10$usesomesillystringforsalt.eFh0wQnE8zq9Z4cH3kXv8Jr2YB5l9m';

    public function __construct(private UserRepository $users, private HistoryRepository $history)
    {
    }

    /** @return array<string,mixed> */
    public function attempt(string $email, string $password, string $ip = ''): array
    {
        $email = mb_strtolower(trim($email));
        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Vui lòng nhập email hợp lệ.';
        }
        if ($password === '') {
            $errors['password'] = 'Vui lòng nhập mật khẩu.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        $user = $this->users->findByEmail($email);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $ok = password_verify($password, $hash) && $user !== null && (int) $user['is_active'] === 1;
        if (!$ok) {
            $this->history->audit(null, $user ? (int) $user['id'] : null, 'login_failed', 'user', $user ? (int) $user['id'] : null, [], $ip);
            throw new ValidationException(['form' => 'Email hoặc mật khẩu không đúng.'], 'Đăng nhập thất bại.');
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->rehash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $this->users->touchLogin((int) $user['id']);
        $this->history->audit(null, (int) $user['id'], 'login', 'user', (int) $user['id'], [], $ip);
        return $this->users->find((int) $user['id']) ?? $user;
    }

    /** Ghi nhận đăng nhập vào session và đổi session ID (chống session fixation). */
    public function startSession(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
    }
}
