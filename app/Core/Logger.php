<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

/** Ghi log nội bộ vào storage/logs (không bao giờ hiển thị cho người dùng). */
final class Logger
{
    public static function error(string $message, ?\Throwable $e = null): void
    {
        $dir = Config::rootPath('storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message;
        if ($e) {
            $line .= ' | ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
        }
        @file_put_contents($dir . '/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
