<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

final class UserRepository extends Repository
{
    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->one('SELECT id, email, full_name, is_admin, is_active, last_login_at FROM users WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->one('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
    }

    public function touchLogin(int $id): void
    {
        $this->run('UPDATE users SET last_login_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $id]);
    }

    public function rehash(int $id, string $hash): void
    {
        $this->run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function activeList(): array
    {
        return $this->all('SELECT id, email, full_name FROM users WHERE is_active = 1 ORDER BY full_name');
    }
}
