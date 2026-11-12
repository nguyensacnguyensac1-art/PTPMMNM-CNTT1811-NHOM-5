<?php
declare(strict_types=1);

namespace ReleaseHub\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use ReleaseHub\Core\Container;
use ReleaseHub\Core\Database;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;
    private static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = Database::connection();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_logs', 'issue_histories', 'release_snapshot_items', 'release_items', 'releases', 'test_results', 'comments', 'dependencies', 'issues', 'project_members', 'projects', 'users'] as $t) {
            $this->pdo->exec("TRUNCATE TABLE `{$t}`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        Container::reset();
    }

    /** @template T of object @param class-string<T> $class @return T */
    protected function svc(string $class): object
    {
        /** @var T */
        return Container::get($class);
    }

    /** @return array<string,mixed> */
    protected function user(string $name, bool $admin = false): array
    {
        $n = ++self::$seq;
        $now = gmdate('Y-m-d H:i:s');
        $email = strtolower(preg_replace('/\W+/', '.', $name)) . $n . '@test.local';
        $this->pdo->prepare('INSERT INTO users (email, full_name, password_hash, is_admin, created_at, updated_at) VALUES (?,?,?,?,?,?)')
            ->execute([$email, $name, password_hash('secret123', PASSWORD_DEFAULT), $admin ? 1 : 0, $now, $now]);
        $id = (int) $this->pdo->lastInsertId();
        return ['id' => $id, 'email' => $email, 'full_name' => $name, 'is_admin' => $admin ? 1 : 0];
    }

    protected function project(string $code): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT INTO projects (code, name, description, created_at, updated_at) VALUES (?,?,?,?,?)')->execute([$code, "Dự án {$code}", 'Mô tả', $now, $now]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function member(int $projectId, array $user, string $role): void
    {
        $this->pdo->prepare('INSERT INTO project_members (project_id, user_id, role_code, created_at) VALUES (?,?,?,?)')
            ->execute([$projectId, $user['id'], $role, gmdate('Y-m-d H:i:s')]);
    }

    /**
     * Tạo issue trực tiếp bằng SQL (fixture). Mặc định là Bug Open.
     *
     * @param array<string,mixed> $o
     */
    protected function issue(int $projectId, array $reporter, array $o = []): int
    {
        $number = (int) $this->pdo->query("SELECT COALESCE(MAX(number),0)+1 FROM issues WHERE project_id = {$projectId}")->fetchColumn();
        $code = (string) $this->pdo->query("SELECT code FROM projects WHERE id = {$projectId}")->fetchColumn() . '-' . $number;
        $now = gmdate('Y-m-d H:i:s');
        $d = $o + [
            'type' => 'bug', 'title' => 'Lỗi ' . $code, 'priority' => 'medium', 'severity' => 'major', 'status' => 'open',
            'assignee_id' => null, 'fix_ref' => null, 'fix_revision' => 0, 'steps' => 'B1', 'expected' => 'OK',
        ];
        $this->pdo->prepare('INSERT INTO issues (project_id, number, code, type, title, description, priority, severity, status, reporter_id, assignee_id,
              steps_to_reproduce, expected_result, fix_ref, fix_revision, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$projectId, $number, $code, $d['type'], $d['title'], 'Mô tả', $d['priority'], $d['type'] === 'bug' ? $d['severity'] : null, $d['status'],
                $reporter['id'], $d['assignee_id'], $d['type'] === 'bug' ? $d['steps'] : null, $d['type'] === 'bug' ? $d['expected'] : null, $d['fix_ref'], $d['fix_revision'], $now, $now]);
        $id = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("UPDATE projects SET issue_counter = {$number} WHERE id = {$projectId}");
        return $id;
    }

    protected function dep(int $projectId, int $a, int $b, array $by): void
    {
        $this->pdo->prepare('INSERT INTO dependencies (project_id, issue_id, depends_on_id, created_by, created_at) VALUES (?,?,?,?,?)')
            ->execute([$projectId, $a, $b, $by['id'], gmdate('Y-m-d H:i:s')]);
    }

    protected function release(int $projectId, array $by, string $name = 'v1.0.0'): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT INTO releases (project_id, name, created_by, created_at, updated_at) VALUES (?,?,?,?,?)')->execute([$projectId, $name, $by['id'], $now, $now]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function attach(int $projectId, int $releaseId, int $issueId, array $by, bool $required = true): void
    {
        $this->pdo->prepare('INSERT INTO release_items (project_id, release_id, issue_id, is_required, added_by, added_at) VALUES (?,?,?,?,?,?)')
            ->execute([$projectId, $releaseId, $issueId, $required ? 1 : 0, $by['id'], gmdate('Y-m-d H:i:s')]);
    }

    /** @return array<string,mixed> */
    protected function fetchIssue(int $id): array
    {
        return $this->pdo->query("SELECT * FROM issues WHERE id = {$id}")->fetch();
    }
}
