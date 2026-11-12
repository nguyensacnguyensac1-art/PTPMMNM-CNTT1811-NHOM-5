<?php
declare(strict_types=1);

// Tiến trình con cho kiểm thử đồng thời: có kết nối DB RIÊNG. Dùng: php worker.php <scenario> <startAtMicrotime> <jsonParams>
require __DIR__ . '/../../app/bootstrap.php';

use ReleaseHub\Core\Container;
use ReleaseHub\Core\Database;
use ReleaseHub\Core\Env;
use ReleaseHub\Core\HttpException;

$testDb = Env::get('TEST_DB_DATABASE', 'releasehub_test');
if (!preg_match('/_test$/', (string) $testDb)) {
    exit(2);
}
Env::set('DB_DATABASE', (string) $testDb);
$pdo = Database::connect((string) $testDb);
Database::setConnection($pdo);

[$script, $scenario, $startAt, $json] = $argv;
$p = json_decode($json, true);
$user = $pdo->query('SELECT id, email, full_name, is_admin FROM users WHERE id = ' . (int) $p['actor'])->fetch();

// Rào chắn thời gian: cả hai tiến trình cùng bắt đầu một thời điểm
while (microtime(true) < (float) $startAt) {
    usleep(200);
}
try {
    $result = match ($scenario) {
        'add_edge' => Container::get(\ReleaseHub\Services\DependencyService::class)->add($user, (int) $p['issue'], (int) $p['dep']),
        'finalize' => Container::get(\ReleaseHub\Services\ReleaseSnapshotService::class)->finalize($user, (int) $p['release']),
        'edit' => Container::get(\ReleaseHub\Services\IssueService::class)->update($user, (int) $p['issue'], $p['fields'], $p['version']),
        'transition' => Container::get(\ReleaseHub\Services\IssueWorkflowService::class)->transition($user, (int) $p['issue'], $p['to'], $p['version']),
        'fix' => Container::get(\ReleaseHub\Services\IssueWorkflowService::class)->recordFix($user, (int) $p['issue'], $p['ref'], $p['version']),
        default => throw new RuntimeException('scenario?'),
    };
    echo json_encode(['ok' => true]);
} catch (HttpException $e) {
    echo json_encode(['ok' => false, 'code' => $e->errorCode, 'status' => $e->status]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'code' => 'exception', 'message' => $e->getMessage()]);
}
