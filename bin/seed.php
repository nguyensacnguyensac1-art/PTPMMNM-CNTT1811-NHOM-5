<?php
declare(strict_types=1);

// Chạy: php bin/seed.php   (an toàn chạy lại: không nhân đôi dữ liệu)
require __DIR__ . '/bootstrap.php';

use ReleaseHub\Core\Database;
use ReleaseHub\Database\Seeders\DatabaseSeeder;

$result = (new DatabaseSeeder(Database::connection()))->run();
foreach ($result as $k => $v) {
    echo str_pad($k, 18) . $v . PHP_EOL;
}
echo 'Mật khẩu tài khoản demo: ' . DatabaseSeeder::DEMO_PASSWORD . PHP_EOL;
