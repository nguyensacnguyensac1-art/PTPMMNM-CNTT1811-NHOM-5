<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use ReleaseHub\Core\Database;
use ReleaseHub\Core\Env;
use ReleaseHub\Support\Migrator;

// AN TOÀN: kiểm thử chỉ chạy trên database có đuôi _test, KHÔNG bao giờ đụng vào database thật của người dùng.
$testDb = Env::get('TEST_DB_DATABASE', 'releasehub_test');
if (!preg_match('/_test$/', (string) $testDb)) {
    fwrite(STDERR, "TEST_DB_DATABASE phải kết thúc bằng _test (hiện là '{$testDb}'). Dừng để bảo vệ dữ liệu thật.\n");
    exit(2);
}
Env::set('DB_DATABASE', (string) $testDb);

try {
    $pdo = new PDO(
        'mysql:host=' . Env::get('DB_HOST', '127.0.0.1') . ';port=' . Env::get('DB_PORT', '3306') . ';charset=utf8mb4',
        Env::get('DB_USERNAME', 'root'), Env::get('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $testDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (PDOException $e) {
    fwrite(STDERR, "Không kết nối được MySQL để chạy test: kiểm tra .env và XAMPP.\n");
    exit(2);
}

$conn = Database::connect((string) $testDb);
Database::setConnection($conn);
// Dựng lại schema sạch từ migration cho database kiểm thử.
Migrator::dropAll($conn);
(new Migrator($conn))->migrate();
