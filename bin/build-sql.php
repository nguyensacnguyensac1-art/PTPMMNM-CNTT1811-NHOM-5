<?php
declare(strict_types=1);

/**
 * Sinh database/releasehub.sql để import bằng phpMyAdmin.
 * Dùng một database TẠM (releasehub_sqlbuild) - không đụng tới database `releasehub` của bạn.
 * Chạy: php bin/build-sql.php
 */
require __DIR__ . '/bootstrap.php';

use ReleaseHub\Core\Config;
use ReleaseHub\Core\Container;
use ReleaseHub\Core\Database;
use ReleaseHub\Core\Env;
use ReleaseHub\Database\Seeders\DatabaseSeeder;
use ReleaseHub\Support\Migrator;

$scratch = 'releasehub_sqlbuild';
$server = new PDO('mysql:host=' . Env::get('DB_HOST', '127.0.0.1') . ';port=' . Env::get('DB_PORT', '3306') . ';charset=utf8mb4', Env::get('DB_USERNAME', 'root'), Env::get('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("DROP DATABASE IF EXISTS `{$scratch}`");
$server->exec("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo = Database::connect($scratch);
Database::setConnection($pdo);
Container::reset();
$migrator = new Migrator($pdo);
$migrator->migrate();
(new DatabaseSeeder($pdo))->run();

$out = "-- ReleaseHub – cấu trúc và dữ liệu mẫu (tất cả là DỮ LIỆU GIẢ)\n"
    . "-- Cách B: import vào database TRỐNG (đã tạo với utf8mb4). KHÔNG chạy cùng migration/seeder.\n"
    . "-- Sinh bởi: php bin/build-sql.php (16 dự án, 320 công việc, 48 phiên bản)\n\n"
    . "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

$files = $migrator->files();
// 001: cấu trúc (+ bảng roles đã nằm trong chính migration)
foreach (Migrator::statements((string) file_get_contents($files[0])) as $stmt) {
    $out .= $stmt . ";\n\n";
}
$out .= "CREATE TABLE schema_migrations (\n    version VARCHAR(100) NOT NULL PRIMARY KEY,\n    applied_at DATETIME NOT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

$tables = ['users', 'projects', 'project_members', 'issues', 'dependencies', 'comments', 'test_results', 'releases', 'release_items', 'release_snapshot_items', 'issue_histories', 'audit_logs'];
foreach ($tables as $t) {
    $cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = '{$scratch}' AND table_name = '{$t}' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
    $rows = $pdo->query("SELECT * FROM `{$t}` ORDER BY id")->fetchAll(PDO::FETCH_NUM);
    $out .= "-- Dữ liệu bảng {$t} (" . count($rows) . " dòng)\n";
    foreach (array_chunk($rows, 100) as $chunk) {
        $vals = array_map(static function (array $row) use ($pdo): string {
            return '(' . implode(',', array_map(static fn ($v) => $v === null ? 'NULL' : (is_int($v) || (is_string($v) && preg_match('/^-?[1-9][0-9]{0,15}$|^0$/', $v)) ? (string) $v : $pdo->quote((string) $v)), $row)) . ')';
        }, $chunk);
        $out .= "INSERT INTO `{$t}` (`" . implode('`,`', $cols) . "`) VALUES\n" . implode(",\n", $vals) . ";\n";
    }
    $out .= "\n";
}
// 002: trigger tạo SAU KHI nạp dữ liệu (trigger chặn ghi vào phiên bản đã chốt)
$out .= "-- Trigger bảo vệ snapshot bất biến (tạo sau cùng)\nDELIMITER \$\$\n";
foreach (Migrator::statements((string) file_get_contents($files[1])) as $stmt) {
    $out .= $stmt . "\$\$\n";
}
$out .= "DELIMITER ;\n\n";
$out .= "-- Trạng thái migration tương ứng (để chạy php bin/migrate.php sau này không áp dụng lại)\n";
$out .= "INSERT INTO schema_migrations (version, applied_at) VALUES\n";
$applied = $pdo->query('SELECT version, applied_at FROM schema_migrations ORDER BY version')->fetchAll();
$out .= implode(",\n", array_map(static fn ($r) => "('" . $r['version'] . "', '" . $r['applied_at'] . "')", $applied)) . ";\n\n";
$out .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents(Config::rootPath('database/releasehub.sql'), $out);
$server->exec("DROP DATABASE `{$scratch}`");
echo 'Đã ghi database/releasehub.sql (' . number_format(strlen($out)) . " byte)\n";
