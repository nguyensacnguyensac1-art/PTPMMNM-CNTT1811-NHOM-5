<?php
declare(strict_types=1);

use ReleaseHub\Core\Env;

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
} else {
    // Dự phòng khi chưa chạy "composer install": PSR-4 tự viết (ứng dụng không có phụ thuộc runtime).
    spl_autoload_register(static function (string $class) use ($root): void {
        $map = ['ReleaseHub\\Database\\' => $root . '/database/', 'ReleaseHub\\Tests\\' => $root . '/tests/', 'ReleaseHub\\' => $root . '/app/'];
        foreach ($map as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
                return;
            }
        }
    });
    require_once $root . '/app/Support/helpers.php';
}

Env::load($root . '/.env');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
