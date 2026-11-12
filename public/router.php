<?php
declare(strict_types=1);

/**
 * Router cho máy chủ tích hợp của PHP (CHỈ dùng khi phát triển, không dùng trên XAMPP/Apache):
 *   php -S localhost:8000 -t public public/router.php
 * Tệp tĩnh có thật được phục vụ trực tiếp; mọi yêu cầu khác đi vào front controller.
 */
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = realpath(__DIR__ . $path);
if ($path !== '/' && $file !== false && is_file($file) && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && !str_ends_with($file, '.php')) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
