<?php
declare(strict_types=1);

/**
 * LỆNH RESET – NGUY HIỂM: XÓA TOÀN BỘ bảng trong database đang cấu hình rồi dựng lại (migration + seeder).
 * Không bao giờ chạy tự động. Bắt buộc gõ lại đúng tên database:
 *   php bin/reset.php --confirm=releasehub            (dựng lại + nạp dữ liệu mẫu)
 *   php bin/reset.php --confirm=releasehub --no-seed  (chỉ dựng lại cấu trúc)
 */
require __DIR__ . '/bootstrap.php';

use ReleaseHub\Core\Database;
use ReleaseHub\Core\Env;
use ReleaseHub\Database\Seeders\DatabaseSeeder;
use ReleaseHub\Support\Migrator;

$db = (string) Env::get('DB_DATABASE', 'releasehub');
$args = array_slice($argv, 1);
$confirm = null;
foreach ($args as $a) {
    if (str_starts_with($a, '--confirm=')) {
        $confirm = substr($a, 10);
    }
}
if ($confirm !== $db) {
    fwrite(STDERR, "CẢNH BÁO: lệnh này sẽ XÓA TOÀN BỘ dữ liệu trong database '{$db}'.\n");
    fwrite(STDERR, "Nếu chắc chắn, chạy lại: php bin/reset.php --confirm={$db}\n");
    exit(1);
}
$pdo = Database::connection();
Migrator::dropAll($pdo);
(new Migrator($pdo))->migrate();
echo "Đã dựng lại cấu trúc database '{$db}'.\n";
if (!in_array('--no-seed', $args, true)) {
    $r = (new DatabaseSeeder($pdo))->run();
    echo "Đã nạp dữ liệu mẫu: {$r['projects']} dự án, {$r['issues']} công việc, {$r['releases']} phiên bản.\n";
}
