<?php /** @var array $project @var array $list @var array $members */
echo partial('project_head', ['project' => $project, 'tab' => 'releases', 'role' => $role, 'can' => $can]); ?>
<div class="row g-3">
<div class="col-lg-8"><div class="rh-card"><div class="card-head">Kế hoạch phát hành <a class="btn btn-sm btn-outline-primary" href="<?= e(url('projects/' . $project['id'] . '/release-history')) ?>"><i class="bi bi-clock-history"></i> Lịch sử phát hành</a></div>
<?php if (!$list): ?><div class="empty"><i class="bi bi-box-seam"></i>Chưa có phiên bản nào.</div><?php else: ?>
<div class="table-responsive"><table class="table rh-table"><thead><tr><th>Phiên bản</th><th>Trạng thái</th><th>Ngày</th><th>Người phụ trách</th><th style="min-width:160px">Tiến độ</th></tr></thead><tbody>
<?php foreach ($list as $r): $pct = $r['item_count'] ? (int) round($r['done_count'] * 100 / $r['item_count']) : 0; ?><tr>
    <td><a class="fw-semibold" href="<?= e(url('releases/' . $r['id'])) ?>"><?= e($r['name']) ?></a></td>
    <td><span class="rh-badge <?= $r['status'] === 'released' ? 'st-verified' : 'st-in_progress' ?>"><i class="bi <?= $r['status'] === 'released' ? 'bi-patch-check' : 'bi-calendar-event' ?>"></i> <?= $r['status'] === 'released' ? 'Đã phát hành' : 'Dự kiến' ?></span></td>
    <td class="small text-nowrap"><?= $r['status'] === 'released' ? e(vn_dt($r['released_at'])) : e(vn_date($r['planned_date'])) ?></td><td class="small"><?= e($r['status'] === 'released' ? ($r['released_by_name'] ?? '—') : ($r['owner_name'] ?? '—')) ?></td>
    <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" role="progressbar" aria-label="Tiến độ <?= e($r['name']) ?>" aria-valuenow="<?= e($pct) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar <?= $r['status'] === 'released' ? 'bg-success' : '' ?>" style="width:<?= e($pct) ?>%"></div></div><small><?= e($r['done_count']) ?>/<?= e($r['item_count']) ?></small></div></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>
<?php if ($can['plan'] && !$project['archived_at']): ?><div class="col-lg-4"><form class="rh-card p-3" data-api="api/projects/<?= e($project['id']) ?>/releases" novalidate><h2 class="h6">Tạo phiên bản dự kiến</h2>
    <div class="mb-2"><label class="form-label req" for="name">Mã phiên bản</label><input class="form-control" id="name" name="name" maxlength="40" placeholder="VD: v1.3.0" required></div>
    <div class="mb-2"><label class="form-label" for="planned_date">Ngày dự kiến</label><input class="form-control" type="date" id="planned_date" name="planned_date"></div>
    <div class="mb-2"><label class="form-label" for="owner_id">Người phụ trách</label><select class="form-select" id="owner_id" name="owner_id"><option value="">— chưa chọn —</option><?php foreach ($members as $m): if ($m['role_code'] !== 'viewer'): ?><option value="<?= e($m['user_id']) ?>"><?= e($m['full_name']) ?></option><?php endif; endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label" for="description">Mô tả</label><textarea class="form-control" id="description" name="description" rows="3"></textarea></div>
    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-lg"></i> Tạo phiên bản</button></form></div><?php endif; ?>
</div>
