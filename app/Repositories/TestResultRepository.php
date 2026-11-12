<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

final class TestResultRepository extends Repository
{
    public function add(int $projectId, int $issueId, int $testerId, string $result, int $fixRevision, ?string $fixRef, ?string $note): int
    {
        $this->run(
            'INSERT INTO test_results (project_id, issue_id, tester_id, result, fix_revision, fix_ref, note, tested_at) VALUES (?,?,?,?,?,?,?,?)',
            [$projectId, $issueId, $testerId, $result, $fixRevision, $fixRef, $note, gmdate('Y-m-d H:i:s')]
        );
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> */
    public function forIssue(int $issueId): array
    {
        return $this->all(
            'SELECT t.id, t.result, t.fix_revision, t.fix_ref, t.note, t.tested_at, u.full_name AS tester_name
               FROM test_results t JOIN users u ON u.id = t.tester_id WHERE t.issue_id = ? ORDER BY t.id DESC',
            [$issueId]
        );
    }

    /** @return array<string,mixed>|null */
    public function latest(int $issueId): ?array
    {
        return $this->one(
            'SELECT t.id, t.result, t.fix_revision, t.fix_ref, t.note, t.tested_at, u.full_name AS tester_name
               FROM test_results t JOIN users u ON u.id = t.tester_id WHERE t.issue_id = ? ORDER BY t.id DESC LIMIT 1',
            [$issueId]
        );
    }
}
