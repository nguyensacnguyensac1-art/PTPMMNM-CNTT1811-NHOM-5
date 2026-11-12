<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

use PDO;
use PDOException;
use RuntimeException;

/** Kết nối PDO dùng chung (MySQL/MariaDB, utf8mb4, prepared statements thật). */
final class Database
{
    private static ?PDO $connection = null;
    private static int $depth = 0;

    public static function connection(): PDO
    {
        return self::$connection ??= self::connect();
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$connection = $pdo;
        self::$depth = 0;
    }

    /** Tạo kết nối ĐỘC LẬP mới (dùng cho kiểm thử đồng thời / tiến trình con). */
    public static function connect(?string $database = null): PDO
    {
        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $name = $database ?? Env::get('DB_DATABASE', 'releasehub');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, Env::get('DB_USERNAME', 'root'), Env::get('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'");
            return $pdo;
        } catch (PDOException $e) {
            // Không lộ thông tin đăng nhập hay chi tiết lỗi ra giao diện.
            Logger::error('DB connection failed (code ' . $e->getCode() . ')', $e);
            throw new RuntimeException('Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình.', 0);
        }
    }

    /**
     * Chạy $fn trong một transaction. Lồng nhau thì dùng chung transaction ngoài cùng.
     * Tự thử lại tối đa 3 lần khi gặp deadlock (SQLSTATE 40001).
     *
     * @template T
     * @param callable(PDO):T $fn
     * @return T
     */
    public static function transaction(callable $fn, ?PDO $pdo = null)
    {
        $pdo ??= self::connection();
        if ($pdo->inTransaction()) {
            return $fn($pdo);
        }
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $pdo->beginTransaction();
                $result = $fn($pdo);
                $pdo->commit();
                return $result;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $deadlock = $e instanceof PDOException && ($e->errorInfo[0] ?? '') === '40001';
                if ($deadlock && $attempt < 3) {
                    usleep(20000 * $attempt);
                    continue;
                }
                throw $e;
            }
        }
    }

    /** Khóa theo dự án: mọi thao tác ghi làm đổi điều kiện phát hành đều đi qua khóa này. */
    public static function lockProject(PDO $pdo, int $projectId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? FOR UPDATE');
        $stmt->execute([$projectId]);
        if ($stmt->fetchColumn() === false) {
            throw new NotFoundException('Không tìm thấy dự án.');
        }
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
