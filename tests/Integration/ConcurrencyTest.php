<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Integration;

use ReleaseHub\Services\DependencyGraphService;
use ReleaseHub\Services\IssueWorkflowService;
use ReleaseHub\Tests\DatabaseTestCase;

/**
 * Kiểm thử đồng thời THẬT: hai tiến trình PHP riêng, mỗi tiến trình có kết nối MySQL độc lập,
 * cùng xuất phát tại một mốc thời gian. (Hai lời gọi nối tiếp KHÔNG phải kiểm thử race condition.)
 */
final class ConcurrencyTest extends DatabaseTestCase
{
    /** @param array<int,array{0:string,1:array<string,mixed>}> $jobs @return array<int,array<string,mixed>> */
    private function race(array $jobs): array
    {
        $startAt = microtime(true) + 1.0;
        $procs = [];
        foreach ($jobs as $i => [$scenario, $params]) {
            $cmd = [PHP_BINARY, __DIR__ . '/../helpers/worker.php', $scenario, (string) $startAt, json_encode($params)];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, [
                'TEST_DB_DATABASE' => (string) getenv('DB_DATABASE'),
                'PATH' => (string) getenv('PATH'),
                'DB_HOST' => (string) (getenv('DB_HOST') ?: '127.0.0.1'),
                'DB_PORT' => (string) (getenv('DB_PORT') ?: '3306'),
                'DB_USERNAME' => (string) (getenv('DB_USERNAME') ?: 'root'),
                'DB_PASSWORD' => (string) (getenv('DB_PASSWORD') ?: ''),
            ]);
            $procs[$i] = [$proc, $pipes];
        }
        $out = [];
        foreach ($procs as $i => [$proc, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            proc_close($proc);
            $out[$i] = json_decode((string) $stdout, true) ?? ['ok' => false, 'code' => 'no-output:' . $stdout];
        }
        return $out;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Các tiến trình con cần thấy dữ liệu: transaction của test không được mở.
        $this->assertFalse($this->pdo->inTransaction());
        putenv('DB_DATABASE=' . $this->pdo->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testTwoRequestsCannotTogetherCreateACycle(): void
    {
        $dev = $this->user('Dev');
        $p = $this->project('RAC');
        $this->member($p, $dev, 'developer');
        $rounds = 6;
        $bothRejected = 0;
        for ($r = 0; $r < $rounds; $r++) {
            $this->pdo->exec('DELETE FROM dependencies');
            $a = $this->issue($p, $dev);
            $b = $this->issue($p, $dev);
            $c = $this->issue($p, $dev);
            $this->dep($p, $a, $b, $dev); // A -> B có sẵn
            // Riêng lẻ mỗi cạnh đều hợp lệ; cùng lúc thì B->C và C->A tạo vòng A->B->C->A
            $res = $this->race([
                ['add_edge', ['actor' => $dev['id'], 'issue' => $b, 'dep' => $c]],
                ['add_edge', ['actor' => $dev['id'], 'issue' => $c, 'dep' => $a]],
            ]);
            $ok = count(array_filter($res, fn ($x) => $x['ok']));
            $this->assertSame(1, $ok, 'Round ' . $r . ': đúng một request được chấp nhận. Kết quả: ' . json_encode($res));
            $rejected = array_values(array_filter($res, fn ($x) => !$x['ok']))[0];
            $this->assertSame('cycle_detected', $rejected['code']);
            $edges = $this->svc(\ReleaseHub\Repositories\DependencyRepository::class)->edges($p);
            $this->assertCount(2, $edges);
            $this->assertNull((new DependencyGraphService())->findAnyCycle($edges), 'Đồ thị không được có vòng');
        }
    }

    public function testConcurrentFinalizeCreatesExactlyOneRelease(): void
    {
        $rm = $this->user('RM');
        $dev = $this->user('Dev');
        $p = $this->project('FIN');
        $this->member($p, $rm, 'release_manager');
        $this->member($p, $dev, 'developer');
        $t1 = $this->issue($p, $dev, ['type' => 'task', 'status' => 'closed']);
        $t2 = $this->issue($p, $dev, ['type' => 'improvement', 'status' => 'resolved']);
        $rel = $this->release($p, $rm);
        $this->attach($p, $rel, $t1, $rm);
        $this->attach($p, $rel, $t2, $rm);
        $res = $this->race([
            ['finalize', ['actor' => $rm['id'], 'release' => $rel]],
            ['finalize', ['actor' => $rm['id'], 'release' => $rel]],
            ['finalize', ['actor' => $rm['id'], 'release' => $rel]],
        ]);
        $this->assertSame(1, count(array_filter($res, fn ($x) => $x['ok'])), json_encode($res));
        foreach ($res as $x) {
            if (!$x['ok']) {
                $this->assertSame('already_released', $x['code']);
            }
        }
        $this->assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM release_snapshot_items WHERE release_id = {$rel}")->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'release.finalize'")->fetchColumn());
    }

    /** Cập nhật đồng thời khi chốt: snapshot không bao giờ chứa trạng thái "ma" (đã đổi giữa lúc kiểm tra và lúc chốt). */
    public function testFinalizeRacingWithReopenNeverProducesInconsistentSnapshot(): void
    {
        $rm = $this->user('RM');
        $dev = $this->user('Dev');
        $qa = $this->user('QA');
        $p = $this->project('RCE');
        $this->member($p, $rm, 'release_manager');
        $this->member($p, $dev, 'developer');
        $this->member($p, $qa, 'tester');
        $released = 0;
        $blocked = 0;
        for ($r = 0; $r < 8; $r++) {
            $bug = $this->issue($p, $dev, ['severity' => 'blocker', 'status' => 'in_progress']);
            $wf = $this->svc(IssueWorkflowService::class);
            $wf->recordFix($dev, $bug, 'abcdef1', (int) $this->fetchIssue($bug)['version']);
            $wf->recordTest($qa, $bug, 'passed', 1, null);
            $rel = $this->release($p, $rm, 'v' . $r);
            $this->attach($p, $rel, $bug, $rm);
            $res = $this->race([
                ['finalize', ['actor' => $rm['id'], 'release' => $rel]],
                ['transition', ['actor' => $qa['id'], 'issue' => $bug, 'to' => 'reopened', 'version' => (int) $this->fetchIssue($bug)['version']]],
            ]);
            $status = $this->pdo->query("SELECT status FROM releases WHERE id = {$rel}")->fetchColumn();
            if ($status === 'released') {
                $released++;
                $snap = $this->pdo->query("SELECT status, test_evidence FROM release_snapshot_items WHERE release_id = {$rel}")->fetch();
                $this->assertContains($snap['status'], ['verified', 'closed'], 'Snapshot chứa trạng thái không hợp lệ: ' . json_encode($res));
                $this->assertSame('passed', json_decode($snap['test_evidence'], true)['result']);
            } else {
                $blocked++;
                $this->assertFalse($res[0]['ok']);
                $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM release_snapshot_items WHERE release_id = {$rel}")->fetchColumn());
            }
        }
        $this->assertSame(8, $released + $blocked);
    }

    public function testConcurrentEditsOnlyOneWinsTheOtherGets409(): void
    {
        $a = $this->user('A');
        $b = $this->user('B');
        $p = $this->project('EDT');
        $this->member($p, $a, 'developer');
        $this->member($p, $b, 'developer');
        $id = $this->issue($p, $a, ['title' => 'Gốc']);
        $res = $this->race([
            ['edit', ['actor' => $a['id'], 'issue' => $id, 'fields' => ['title' => 'Từ A đồng thời'], 'version' => 1]],
            ['edit', ['actor' => $b['id'], 'issue' => $id, 'fields' => ['title' => 'Từ B đồng thời'], 'version' => 1]],
        ]);
        $this->assertSame(1, count(array_filter($res, fn ($x) => $x['ok'])), json_encode($res));
        $loser = array_values(array_filter($res, fn ($x) => !$x['ok']))[0];
        $this->assertSame(409, $loser['status']);
        $winnerTitle = $res[0]['ok'] ? 'Từ A đồng thời' : 'Từ B đồng thời';
        $this->assertSame($winnerTitle, $this->fetchIssue($id)['title']);
        $this->assertSame(2, (int) $this->fetchIssue($id)['version']);
    }
}
