<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReleaseHub\Services\DependencyGraphService;

final class DependencyGraphServiceTest extends TestCase
{
    private function e(int $a, int $b): array
    {
        return ['issue_id' => $a, 'depends_on_id' => $b];
    }

    public function testRequiredCaseAbcCycleReturnsExactChain(): void
    {
        // A=1, B=2, C=3. A phụ thuộc B; B phụ thuộc C. Thêm C phụ thuộc A => vòng.
        $g = new DependencyGraphService();
        $cycle = $g->findCycleIfAdded([$this->e(1, 2), $this->e(2, 3)], 3, 1);
        $this->assertSame([3, 1, 2, 3], $cycle);
    }

    public function testSafeEdgeReturnsNull(): void
    {
        $g = new DependencyGraphService();
        $this->assertNull($g->findCycleIfAdded([$this->e(1, 2), $this->e(2, 3)], 1, 3)); // đường tắt, không tạo vòng
        $this->assertNull($g->findCycleIfAdded([], 1, 2));
    }

    public function testSelfDependencyIsACycle(): void
    {
        $this->assertSame([5, 5], (new DependencyGraphService())->findCycleIfAdded([], 5, 5));
    }

    public function testDirectTwoNodeCycle(): void
    {
        $this->assertSame([2, 1, 2], (new DependencyGraphService())->findCycleIfAdded([$this->e(1, 2)], 2, 1));
    }

    public function testDiamondIsNotACycle(): void
    {
        $edges = [$this->e(1, 2), $this->e(1, 3), $this->e(2, 4), $this->e(3, 4)];
        $this->assertNull((new DependencyGraphService())->findCycleIfAdded($edges, 5, 1));
        $this->assertNull((new DependencyGraphService())->findAnyCycle($edges));
    }

    public function testFindAnyCycleDetectsExistingLoop(): void
    {
        $c = (new DependencyGraphService())->findAnyCycle([$this->e(1, 2), $this->e(2, 3), $this->e(3, 1), $this->e(9, 1)]);
        $this->assertNotNull($c);
        $this->assertSame($c[0], $c[count($c) - 1]);
    }

    public function testTransitiveDependencies(): void
    {
        $r = (new DependencyGraphService())->transitiveDependencies([$this->e(1, 2), $this->e(2, 3), $this->e(4, 1)], 4);
        sort($r);
        $this->assertSame([1, 2, 3], $r);
    }

    public function testLongChainDoesNotOverflowStack(): void
    {
        $edges = [];
        for ($i = 1; $i < 20000; $i++) {
            $edges[] = $this->e($i, $i + 1);
        }
        $cycle = (new DependencyGraphService())->findCycleIfAdded($edges, 20000, 1);
        $this->assertCount(20001, $cycle);
    }
}
