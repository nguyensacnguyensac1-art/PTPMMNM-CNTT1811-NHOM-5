<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Integration;

use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\ForbiddenException;
use ReleaseHub\Core\HttpException;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Services\DependencyService;
use ReleaseHub\Tests\DatabaseTestCase;

final class DependencyServiceTest extends DatabaseTestCase
{
    /** PHÉP THỬ BẮT BUỘC 1: A phụ thuộc B, B phụ thuộc C; thêm C phụ thuộc A bị từ chối và trả đúng chuỗi vòng. */
    public function testCycleRejectedWithExactChain(): void
    {
        $dev = $this->user('Dev');
        $p = $this->project('CYC');
        $this->member($p, $dev, 'developer');
        $a = $this->issue($p, $dev);
        $b = $this->issue($p, $dev);
        $c = $this->issue($p, $dev);
        $svc = $this->svc(DependencyService::class);
        $svc->add($dev, $a, $b);
        $svc->add($dev, $b, $c);
        try {
            $svc->add($dev, $c, $a);
            $this->fail('Phải từ chối quan hệ tạo vòng');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('cycle_detected', $e->errorCode);
            $this->assertSame(['CYC-3', 'CYC-1', 'CYC-2', 'CYC-3'], $e->extra['cycle']);
            $this->assertSame('CYC-3 → CYC-1 → CYC-2 → CYC-3', $e->extra['cycle_text']);
        }
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM dependencies')->fetchColumn(), 'Cạnh gây vòng không được lưu');
    }

    public function testSelfDuplicateAndCrossProjectRejected(): void
    {
        $dev = $this->user('Dev');
        $p1 = $this->project('AAA');
        $p2 = $this->project('BBB');
        $this->member($p1, $dev, 'developer');
        $this->member($p2, $dev, 'developer');
        $a = $this->issue($p1, $dev);
        $b = $this->issue($p1, $dev);
        $x = $this->issue($p2, $dev);
        $svc = $this->svc(DependencyService::class);

        try {
            $svc->add($dev, $a, $a);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('depends_on_id', $e->errors);
        }
        $svc->add($dev, $a, $b);
        try {
            $svc->add($dev, $a, $b);
            $this->fail();
        } catch (ConflictException $e) {
            $this->assertSame('duplicate_dependency', $e->errorCode);
        }
        try {
            $svc->add($dev, $a, $x);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertStringContainsString('chéo dự án', $e->getMessage());
        }
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM dependencies')->fetchColumn());
    }

    public function testDatabaseItselfRejectsCrossProjectEdge(): void
    {
        $dev = $this->user('Dev');
        $p1 = $this->project('AAA');
        $p2 = $this->project('BBB');
        $a = $this->issue($p1, $dev);
        $x = $this->issue($p2, $dev);
        $this->expectException(\PDOException::class);
        $this->dep($p1, $a, $x, $dev); // khóa ngoại phức hợp (id, project_id) chặn liên kết chéo
    }

    public function testViewerAndOutsiderCannotAddOrRemove(): void
    {
        $dev = $this->user('Dev');
        $viewer = $this->user('Viewer');
        $outsider = $this->user('Outsider');
        $p = $this->project('PRM');
        $this->member($p, $dev, 'developer');
        $this->member($p, $viewer, 'viewer');
        $a = $this->issue($p, $dev);
        $b = $this->issue($p, $dev);
        $svc = $this->svc(DependencyService::class);
        foreach ([$viewer, $outsider] as $u) {
            try {
                $svc->add($u, $a, $b);
                $this->fail('Phải bị từ chối');
            } catch (ForbiddenException) {
                $this->assertTrue(true);
            }
        }
        $svc->add($dev, $a, $b);
        $id = (int) $this->pdo->query('SELECT id FROM dependencies')->fetchColumn();
        $this->expectException(ForbiddenException::class);
        $svc->remove($viewer, $id);
    }

    public function testRemoveThenReverseEdgeAllowed(): void
    {
        $dev = $this->user('Dev');
        $p = $this->project('REV');
        $this->member($p, $dev, 'developer');
        $a = $this->issue($p, $dev);
        $b = $this->issue($p, $dev);
        $svc = $this->svc(DependencyService::class);
        $svc->add($dev, $a, $b);
        $svc->remove($dev, (int) $this->pdo->query('SELECT id FROM dependencies')->fetchColumn());
        $svc->add($dev, $b, $a);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM dependencies')->fetchColumn());
    }
}
