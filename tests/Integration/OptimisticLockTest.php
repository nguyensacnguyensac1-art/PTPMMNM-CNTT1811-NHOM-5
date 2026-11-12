<?php
declare(strict_types=1);

namespace ReleaseHub\Tests\Integration;

use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\ValidationException;
use ReleaseHub\Services\IssueService;
use ReleaseHub\Tests\DatabaseTestCase;

final class OptimisticLockTest extends DatabaseTestCase
{
    /** PHÉP THỬ BẮT BUỘC 3: hai người cùng sửa; lượt lưu sau báo xung đột (HTTP 409) và không ghi đè. */
    public function testSecondSaveGetsConflictAndDoesNotOverwrite(): void
    {
        $alice = $this->user('Alice');
        $bob = $this->user('Bob');
        $p = $this->project('LCK');
        $this->member($p, $alice, 'developer');
        $this->member($p, $bob, 'developer');
        $id = $this->issue($p, $alice, ['title' => 'Tiêu đề gốc']);
        $svc = $this->svc(IssueService::class);

        $v = (int) $this->fetchIssue($id)['version']; // cả hai cùng mở form ở version này
        $svc->update($alice, $id, ['title' => 'Bản của Alice'], $v);

        try {
            $svc->update($bob, $id, ['title' => 'Bản của Bob', 'description' => 'Bản nháp dài của Bob'], $v);
            $this->fail('Lượt lưu sau phải bị xung đột');
        } catch (ConflictException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('version_conflict', $e->errorCode);
            $this->assertSame('Bản của Alice', $e->extra['current']['title']);       // trả bản mới nhất
            $this->assertSame('Bản của Bob', $e->extra['draft']['title']);           // giữ bản nháp để đối chiếu
            $this->assertContains('title', $e->extra['differing_fields']);
        }
        $row = $this->fetchIssue($id);
        $this->assertSame('Bản của Alice', $row['title'], 'Không được ghi đè dữ liệu của người lưu trước');
        $this->assertSame((int) $v + 1, (int) $row['version']);

        // Bob tải lại, lấy version mới và lưu lại thành công (không tự động ép lưu).
        $svc->update($bob, $id, ['title' => 'Bản của Bob'], (int) $row['version']);
        $this->assertSame('Bản của Bob', $this->fetchIssue($id)['title']);
    }

    public function testHistoryWrittenInSameTransactionAndRolledBackOnConflict(): void
    {
        $a = $this->user('A');
        $p = $this->project('HST');
        $this->member($p, $a, 'developer');
        $id = $this->issue($p, $a);
        $svc = $this->svc(IssueService::class);
        $svc->update($a, $id, ['title' => 'Mới'], 1);
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM issue_histories WHERE issue_id = {$id} AND action = 'updated'")->fetchColumn());
        try {
            $svc->update($a, $id, ['title' => 'Tiêu đề cũ'], 1);
        } catch (ConflictException) {
        }
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM issue_histories WHERE issue_id = {$id} AND action = 'updated'")->fetchColumn());
    }

    public function testMissingOrInvalidExpectedVersionRejected(): void
    {
        $a = $this->user('A');
        $p = $this->project('VER');
        $this->member($p, $a, 'developer');
        $id = $this->issue($p, $a);
        $this->expectException(ValidationException::class);
        $this->svc(IssueService::class)->update($a, $id, ['title' => 'Mới'], null);
    }

    public function testValidationFailureKeepsDataAndReportsFields(): void
    {
        $a = $this->user('A');
        $p = $this->project('VAL');
        $this->member($p, $a, 'developer');
        $id = $this->issue($p, $a, ['title' => 'Giữ nguyên']);
        try {
            $this->svc(IssueService::class)->update($a, $id, ['title' => 'ab', 'due_date' => '31/12/2026', 'pr_url' => 'javascript:alert(1)'], 1);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(['title', 'due_date', 'pr_url'], array_keys($e->errors));
        }
        $this->assertSame('Giữ nguyên', $this->fetchIssue($id)['title']);
        $this->assertSame(1, (int) $this->fetchIssue($id)['version']);
    }
}
