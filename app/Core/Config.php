<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

final class Config
{
    public static function rootPath(string $sub = ''): string
    {
        $root = dirname(__DIR__, 2);
        return $sub === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($sub, '/\\');
    }

    public static function debug(): bool
    {
        return Env::bool('APP_DEBUG', false);
    }

    public static function demoMode(): bool
    {
        return Env::bool('APP_DEMO_MODE', false);
    }

    public static function appUrl(): string
    {
        return rtrim(Env::get('APP_URL', '') ?? '', '/');
    }
}
