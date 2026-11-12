<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function valid(Request $request): bool
    {
        $given = $request->header('x-csrf-token') ?? (string) ($request->body['_csrf'] ?? '');
        $expected = (string) ($_SESSION['_csrf'] ?? '');
        return $expected !== '' && $given !== '' && hash_equals($expected, $given);
    }
}
