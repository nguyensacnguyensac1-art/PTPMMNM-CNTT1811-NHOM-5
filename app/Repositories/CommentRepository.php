<?php
declare(strict_types=1);

namespace ReleaseHub\Repositories;

final class CommentRepository extends Repository
{
    public function add(int $projectId, int $issueId, int $userId, string $body): int
    {
        $this->run('INSERT INTO comments (project_id, issue_id, user_id, body, created_at) VALUES (?,?,?,?,?)', [$projectId, $issueId, $userId, $body, gmdate('Y-m-d H:i:s')]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> */
    public function forIssue(int $issueId): array
    {
        return $this->all('SELECT c.id, c.body, c.created_at, u.full_name AS author_name FROM comments c JOIN users u ON u.id = c.user_id WHERE c.issue_id = ? ORDER BY c.id', [$issueId]);
    }
}
