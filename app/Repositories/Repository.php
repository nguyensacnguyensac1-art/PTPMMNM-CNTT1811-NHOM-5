<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

use PDO;

abstract class Repository
{
    public function __construct(protected PDO $pdo)
    {
    }

    /** @param array<int,mixed> $params @return array<string,mixed>|null */
    protected function one(string $sql, array $params = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int,mixed> $params @return array<int,array<string,mixed>> */
    protected function all(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @param array<int,mixed> $params */
    protected function scalar(string $sql, array $params = []): mixed
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @param array<int,mixed> $params */
    protected function run(string $sql, array $params = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** Tạo "?,?,?" cho mệnh đề IN. */
    protected static function in(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /** Mảnh SQL: bản ghi issue $alias đã hoàn thành (theo loại). */
    public static function completeSql(string $alias): string
    {
        return "(({$alias}.type = 'bug' AND {$alias}.status IN ('verified','closed')) OR ({$alias}.type <> 'bug' AND {$alias}.status IN ('resolved','closed')))";
    }
}
