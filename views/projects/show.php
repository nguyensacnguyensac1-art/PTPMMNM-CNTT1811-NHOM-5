<?php /** @var array $project @var array $status_counts @var int $total @var int $done @var int $percent @var array $blockers @var array $releases */
echo partial('project_head', ['project' => $project, 'tab' => 'overview', 'role' => $role, 'can' => $can]);
$order = ['open', 'reopened', 'in_progress', 'resolved', 'verified', 'closed']; ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="rh-card mb-3"><div class="card-head">Giới thiệu</div><div class="card-body-p"><p class="mb-0"><?= e($project['description'] ?: 'Chưa có mô tả.') ?></p>
            <p class="small text-muted mt-2 mb-0">Người phụ trách: <?= e($project['owner_name'] ?? '—') ?> · Tạo ngày <?= e(vn_dt($project['created_at'], 'd/m/Y')) ?></p></div></div>
        <div class="rh-card mb-3"><div class="card-head">Tiến độ công việc <span class="fw-normal text-muted"><?= e($done) ?>/<?= e($total) ?> hoàn thành</span></div>
            <div class="card-body-p">
                <div class="progress mb-3" style="height:12px" role="progressbar" aria-label="Tiến độ" aria-valuenow="<?= e($percent) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= e($percent) ?>%"></div></div>
                <div class="d-flex flex-wrap gap-2">
                <?php foreach ($order as $s): ?><a class="text-decoration-none" href="<?= e(url('projects/' . $project['id'] . '/issues?status=' . $s)) ?>"><?= status_badge($s) ?> <strong><?= e($status_counts[$s] ?? 0) ?></strong></a><?php endforeach; ?></div>
            </div></div>
        <div class="rh-card"><div class="card-head">Công việc chưa xong đang chặn công việc khác <a class="btn btn-sm btn-outline-primary" href="<?= e(url('projects/' . $project['id'] . '/graph')) ?>">Xem đồ thị</a></div>
            <?php if (!$blockers): ?><div class="empty"><i class="bi bi-check2-circle"></i>Không có công việc nào đang chặn.</div><?php else: ?>
            <div class="table-responsive"><table class="table rh-table"><thead><tr><th>Công việc</th><th>Trạng thái</th><th>Đang chặn</th></tr></thead><tbody>
            <?php foreach (array_slice($blockers, 0, 8) as $b): ?><tr><td><a class="code-link" href="<?= e(url('issues/' . $b['id'])) ?>"><?= e($b['code']) ?></a> <?= e($b['title']) ?></td>
                <td><?= status_badge($b['status'], $b['type']) ?></td><td class="small"><?= e($b['blocking_count']) ?> việc: <?= e($b['blocking_codes']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?></div>
    </div>
    <div class="col-lg-4">
        <div class="rh-card"><div class="card-head">Phiên bản <a class="small" href="<?= e(url('projects/' . $project['id'] . '/releases')) ?>">Tất cả</a></div>
            <ul class="list-group list-group-flush">
            <?php foreach ($releases as $r): ?><li class="list-group-item">
                <div class="d-flex justify-content-between"><a class="fw-semibold" href="<?= e(url('releases/' . $r['id'])) ?>"><?= e($r['name']) ?></a>
                    <span class="rh-badge <?= $r['status'] === 'released' ? 'st-verified' : 'st-in_progress' ?>"><?= $r['status'] === 'released' ? 'Đã phát hành' : 'Dự kiến' ?></span></div>
                <small class="text-muted"><?= $r['status'] === 'released' ? 'Chốt ' . e(vn_dt($r['released_at'])) : 'Dự kiến ' . e(vn_date($r['planned_date'])) ?> · <?= e($r['item_count']) ?> việc</small></li><?php endforeach; ?>
            <?php if (!$releases): ?><li class="list-group-item text-muted">Chưa có phiên bản.</li><?php endif; ?>
            </ul></div>
    </div>
</div>
