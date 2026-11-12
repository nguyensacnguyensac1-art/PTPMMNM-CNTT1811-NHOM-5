<?php
declare(strict_types=1);

namespace ReleaseHub\Support;

use PDO;
use ReleaseHub\Core\Config;

/** Trình chạy migration tối giản: tệp .sql trong database/migrations, tách lệnh bằng dòng "-- ;;". */
final class Migrator
{
    public function __construct(private PDO $pdo, private ?string $dir = null)
    {
        $this->dir ??= Config::rootPath('database/migrations');
    }

    /** @return string[] danh sách migration vừa áp dụng */
    public function migrate(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(100) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $done = $this->pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $applied = [];
        foreach ($this->files() as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $done, true)) {
                continue;
            }
            foreach (self::statements((string) file_get_contents($file)) as $sql) {
                $this->pdo->exec($sql);
            }
            $this->pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')
                ->execute([$version, gmdate('Y-m-d H:i:s')]);
            $applied[] = $version;
        }
        return $applied;
    }

    /** @return string[] */
    public function files(): array
    {
        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files);
        return $files;
    }

    /** @return string[] */
    public static function statements(string $sql): array
    {
        $out = [];
        foreach (preg_split('/^-- ;;\s*$/m', $sql) ?: [] as $chunk) {
            $chunk = trim($chunk);
            // bỏ dòng chú thích đầu tệp
            $chunk = trim(preg_replace('/\A(?:--[^\n]*\n)+/', '', $chunk) ?? $chunk);
            if ($chunk !== '') {
                $out[] = $chunk;
            }
        }
        return $out;
    }

    /** Xóa toàn bộ bảng & trigger (chỉ dùng cho lệnh reset có xác nhận và cho test DB). */
    public static function dropAll(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")
            ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $t) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
