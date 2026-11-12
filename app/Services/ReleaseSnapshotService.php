<?php
declare(strict_types=1);

namespace ReleaseHub\Services;

use PDO;
use ReleaseHub\Core\ConflictException;
use ReleaseHub\Core\Database;
use ReleaseHub\Core\HttpException;
use ReleaseHub\Core\NotFoundException;
use ReleaseHub\Domain\Catalog;
use ReleaseHub\Repositories\HistoryRepository;
use ReleaseHub\Repositories\ReleaseRepository;
use ReleaseHub\Repositories\TestResultRepository;
use ReleaseHub\Support\Time;

/**
 * Chốt phát hành: kiểm tra điều kiện lần cuối + tạo snapshot BẤT BIẾN trong CÙNG MỘT transaction,
 * dưới khóa dự án (mọi thao tác làm đổi điều kiện đều dùng cùng khóa nên dữ liệu không đổi giữa kiểm tra và chốt).
 */
final class ReleaseSnapshotService
{
    private const CHECKSUM_FIELDS = [
        'issue_id', 'issue_code', 'title', 'type', 'category', 'change_summary', 'priority', 'severity', 'status',
        'is_required', 'assignee_name', 'fix_ref', 'fix_revision', 'dependency_codes', 'test_evidence',
    ];

    public function __construct(
        private PDO $pdo,
        private ReleaseRepository $releases,
        private TestResultRepository $tests,
        private HistoryRepository $history,
        private ReleaseReadinessService $readiness,
        private AuthorizationService $auth
    ) {
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function finalize(array $actor, int $releaseId, string $ip = '', ?string $at = null): array
    {
        $rel = $this->releases->find($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
        $projectId = (int) $rel['project_id'];
        $this->auth->authorize($actor, 'release.finalize', $projectId);

        return Database::transaction(function (PDO $pdo) use ($actor, $releaseId, $projectId, $ip, $at) {
            Database::lockProject($pdo, $projectId);
            $cur = $this->releases->findForUpdate($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
            if ($cur['status'] === 'released') {
                // Nhấn hai lần / chốt lặp: không tạo bản phát hành trùng.
                throw new ConflictException('Phiên bản này đã được chốt trước đó.', 'already_released', ['release' => $this->releases->find($releaseId)]);
            }
            $report = $this->readiness->evaluate($releaseId);
            if (!$report['ready']) {
                throw new HttpException(409, 'Phiên bản chưa đủ điều kiện phát hành.', 'not_ready', ['report' => $report]);
            }
            $now = $at ?? Database::now();
            $rows = [];
            foreach ($this->releases->items($releaseId) as $i) {
                $latest = $this->tests->latest((int) $i['issue_id']);
                $row = [
                    'release_id' => $releaseId,
                    'project_id' => $projectId,
                    'issue_id' => (int) $i['issue_id'],
                    'issue_code' => $i['code'],
                    'title' => $i['title'],
                    'type' => $i['type'],
                    'category' => Catalog::categoryFor((string) $i['type']),
                    'change_summary' => $this->summary($i),
                    'priority' => $i['priority'],
                    'severity' => $i['severity'],
                    'status' => $i['status'],
                    'is_required' => (int) $i['is_required'],
                    'assignee_name' => $i['assignee_name'],
                    'fix_ref' => $i['fix_ref'],
                    'fix_revision' => (int) $i['fix_revision'],
                    'dependency_codes' => json_encode($this->releases->dependencyCodes((int) $i['issue_id']), JSON_UNESCAPED_UNICODE),
                    'test_evidence' => $latest ? json_encode([
                        'result' => $latest['result'], 'tester' => $latest['tester_name'], 'tested_at' => $latest['tested_at'],
                        'fix_revision' => (int) $latest['fix_revision'], 'fix_ref' => $latest['fix_ref'], 'note' => $latest['note'],
                    ], JSON_UNESCAPED_UNICODE) : null,
                    'finalized_by' => $actor['full_name'],
                    'finalized_at' => $now,
                ];
                $this->releases->insertSnapshot($row);
                $rows[] = $row;
            }
            $checksum = $this->checksum($rows);
            if ($this->releases->markReleased($releaseId, (int) $actor['id'], $checksum, (string) json_encode($report, JSON_UNESCAPED_UNICODE), $now) !== 1) {
                throw new ConflictException('Phiên bản này đã được chốt trước đó.', 'already_released');
            }
            $this->history->audit($projectId, (int) $actor['id'], 'release.finalize', 'release', $releaseId, ['items' => count($rows), 'checksum' => $checksum], $ip, $now);
            return $this->releases->find($releaseId) ?? [];
        }, $this->pdo);
    }

    /** Đối chiếu checksum đã lưu với snapshot hiện có. @return array{ok:bool,stored:?string,actual:string} */
    public function verifyIntegrity(int $releaseId): array
    {
        $rel = $this->releases->find($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
        $actual = $this->checksum($this->releases->snapshotItems($releaseId));
        return ['ok' => $rel['snapshot_checksum'] === $actual, 'stored' => $rel['snapshot_checksum'], 'actual' => $actual];
    }

    /** @param array<string,mixed> $actor */
    public function markdown(array $actor, int $releaseId): string
    {
        [$rel, $items] = $this->loadForExport($actor, $releaseId);
        $md = "# {$rel['project_name']} ({$rel['project_code']}) – Phiên bản {$rel['name']}\n\n";
        $md .= '- **Thời điểm chốt:** ' . Time::vn($rel['released_at']) . " (giờ Việt Nam, UTC+7)\n";
        $md .= '- **Người chốt:** ' . ($rel['released_by_name'] ?? $items[0]['finalized_by'] ?? '—') . "\n";
        $md .= '- **Tổng số thay đổi:** ' . count($items) . "\n";
        if (!empty($rel['description'])) {
            $md .= "\n" . trim((string) $rel['description']) . "\n";
        }
        foreach (Catalog::SNAPSHOT_CATEGORIES as $cat => $label) {
            $group = array_values(array_filter($items, static fn ($i) => $i['category'] === $cat));
            if (!$group) {
                continue;
            }
            $md .= "\n## {$label}\n\n";
            foreach ($group as $i) {
                $line = "- **{$i['issue_code']}** {$i['title']}";
                if ($i['severity']) {
                    $line .= ' – mức ' . (Catalog::SEVERITIES[$i['severity']] ?? $i['severity']);
                }
                if ($i['fix_ref']) {
                    $line .= ' – bản sửa: ' . $i['fix_ref'];
                }
                $ev = $i['test_evidence'] ? json_decode((string) $i['test_evidence'], true) : null;
                if (is_array($ev)) {
                    $line .= ' – kiểm thử ' . ($ev['result'] === 'passed' ? 'Đạt' : 'Không đạt') . ' bởi ' . $ev['tester'] . ' (' . Time::vn($ev['tested_at'], 'd/m/Y') . ')';
                }
                if ((int) $i['is_required'] === 0 && !Catalog::isComplete((string) $i['type'], (string) $i['status'])) {
                    $line .= ' – *không bắt buộc, chưa hoàn thành khi chốt*';
                }
                $md .= $line . "\n";
            }
        }
        return $md;
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function exportJson(array $actor, int $releaseId): array
    {
        [$rel, $items] = $this->loadForExport($actor, $releaseId);
        return [
            'project' => ['code' => $rel['project_code'], 'name' => $rel['project_name']],
            'release' => $rel['name'],
            'released_at_utc' => $rel['released_at'],
            'released_by' => $rel['released_by_name'],
            'checksum' => $rel['snapshot_checksum'],
            'changes' => array_map(static fn ($i) => [
                'code' => $i['issue_code'], 'title' => $i['title'], 'type' => $i['type'], 'category' => $i['category'],
                'priority' => $i['priority'], 'severity' => $i['severity'], 'status' => $i['status'],
                'required' => (bool) $i['is_required'], 'fix_ref' => $i['fix_ref'], 'fix_revision' => (int) $i['fix_revision'],
                'dependencies' => json_decode((string) $i['dependency_codes'], true) ?: [],
                'test_evidence' => $i['test_evidence'] ? json_decode((string) $i['test_evidence'], true) : null,
            ], $items),
        ];
    }

    /** @param array<string,mixed> $actor @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>} */
    private function loadForExport(array $actor, int $releaseId): array
    {
        $rel = $this->releases->find($releaseId) ?? throw new NotFoundException('Không tìm thấy phiên bản.');
        $this->auth->authorize($actor, 'release.view', (int) $rel['project_id']);
        if ($rel['status'] !== 'released') {
            throw new ConflictException('Phiên bản chưa được chốt nên chưa có ghi chú phát hành.', 'not_released');
        }
        return [$rel, $this->releases->snapshotItems($releaseId)];
    }

    /** @param array<string,mixed> $i */
    private function summary(array $i): string
    {
        $text = trim((string) ($i['description'] ?? ''));
        $text = $text === '' ? (string) $i['title'] : $i['title'] . ' — ' . mb_substr($text, 0, 300);
        return $text;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function checksum(array $rows): string
    {
        usort($rows, static fn ($a, $b) => (int) $a['issue_id'] <=> (int) $b['issue_id']);
        $canon = array_map(static function ($r) {
            $o = [];
            foreach (self::CHECKSUM_FIELDS as $f) {
                $o[$f] = $r[$f] === null ? null : (string) $r[$f];
            }
            return $o;
        }, $rows);
        return hash('sha256', (string) json_encode($canon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
