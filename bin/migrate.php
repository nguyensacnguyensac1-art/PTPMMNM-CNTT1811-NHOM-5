<?php
declare(strict_types=1);

// Chạy: php bin/migrate.php   (chỉ áp dụng migration chưa chạy, KHÔNG xóa dữ liệu)
require __DIR__ . '/bootstrap.php';

use ReleaseHub\Core\Database;
use ReleaseHub\Support\Migrator;

$applied = (new Migrator(Database::connection()))->migrate();
echo $applied ? 'Đã áp dụng: ' . implode(', ', $applied) . PHP_EOL : 'Không có migration mới.' . PHP_EOL;
