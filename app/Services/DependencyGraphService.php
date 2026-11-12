<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

/**
 * Thuật toán đồ thị phụ thuộc TỰ VIẾT (không dùng thư viện). Quy ước: cạnh (A, B) = "A phụ thuộc B",
 * tức B phải hoàn thành trước A.
 *
 * Thêm cạnh mới A -> B tạo vòng  <=>  B đã đi tới được A theo các cạnh sẵn có.
 * Dùng DFS lặp (ngăn xếp tường minh, không đệ quy nên không tràn ngăn xếp) kèm mảng "cha" để truy vết đường đi.
 * Độ phức tạp: O(V + E) thời gian, O(V) bộ nhớ.
 */
final class DependencyGraphService
{
    /**
     * @param array<int,array{issue_id:int,depends_on_id:int}> $edges các cạnh hiện có
     * @return int[]|null chuỗi đỉnh khép kín, ví dụ [C, A, B, C]; null nếu thêm cạnh vẫn an toàn
     */
    public function findCycleIfAdded(array $edges, int $issueId, int $dependsOnId): ?array
    {
        if ($issueId === $dependsOnId) {
            return [$issueId, $issueId];
        }
        $adj = $this->adjacency($edges);

        // DFS từ B (dependsOnId) để xem có tới được A (issueId) không.
        $parent = [$dependsOnId => null];
        $stack = [$dependsOnId];
        while ($stack) {
            $node = array_pop($stack);
            if ($node === $issueId) {
                $path = [];
                for ($cur = $node; $cur !== null; $cur = $parent[$cur]) {
                    $path[] = $cur;
                }
                $path = array_reverse($path);           // B ... A
                return array_merge([$issueId], $path);  // A -> B ... -> A
            }
            foreach ($adj[$node] ?? [] as $next) {
                if (!array_key_exists($next, $parent)) {
                    $parent[$next] = $node;
                    $stack[] = $next;
                }
            }
        }
        return null;
    }

    /**
     * Kiểm tra toàn đồ thị có chu trình không (DFS 3 màu). Dùng cho kiểm thử/chẩn đoán.
     *
     * @param array<int,array{issue_id:int,depends_on_id:int}> $edges
     * @return int[]|null một chu trình nếu có
     */
    public function findAnyCycle(array $edges): ?array
    {
        $adj = $this->adjacency($edges);
        $color = [];
        $nodes = array_keys($adj);
        foreach ($edges as $e) {
            $nodes[] = $e['depends_on_id'];
        }
        foreach (array_unique($nodes) as $start) {
            if (isset($color[$start])) {
                continue;
            }
            $stack = [[$start, 0]];
            $trail = [];
            $color[$start] = 1;
            $trail[] = $start;
            while ($stack) {
                [$node, $i] = array_pop($stack);
                $children = $adj[$node] ?? [];
                if ($i < count($children)) {
                    $stack[] = [$node, $i + 1];
                    $next = $children[$i];
                    $c = $color[$next] ?? 0;
                    if ($c === 1) {
                        $pos = array_search($next, $trail, true);
                        return array_merge(array_slice($trail, (int) $pos), [$next]);
                    }
                    if ($c === 0) {
                        $color[$next] = 1;
                        $trail[] = $next;
                        $stack[] = [$next, 0];
                    }
                } else {
                    $color[$node] = 2;
                    array_pop($trail);
                }
            }
        }
        return null;
    }

    /**
     * Mọi công việc mà $issueId phụ thuộc trực tiếp hoặc gián tiếp.
     *
     * @param array<int,array{issue_id:int,depends_on_id:int}> $edges
     * @return int[]
     */
    public function transitiveDependencies(array $edges, int $issueId): array
    {
        $adj = $this->adjacency($edges);
        $seen = [];
        $stack = $adj[$issueId] ?? [];
        while ($stack) {
            $n = array_pop($stack);
            if (isset($seen[$n])) {
                continue;
            }
            $seen[$n] = true;
            foreach ($adj[$n] ?? [] as $m) {
                $stack[] = $m;
            }
        }
        return array_keys($seen);
    }

    /** @param array<int,array{issue_id:int,depends_on_id:int}> $edges @return array<int,int[]> */
    private function adjacency(array $edges): array
    {
        $adj = [];
        foreach ($edges as $e) {
            $adj[(int) $e['issue_id']][] = (int) $e['depends_on_id'];
        }
        return $adj;
    }
}
