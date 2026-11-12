<?php /** @var array $project @var array $released */
echo partial('project_head', ['project' => $project, 'tab' => 'releases', 'role' => $role, 'can' => $can]); ?>
<div class="rh-card"><div class="card-head">Lịch sử phát hành <small class="fw-normal text-muted">Dữ liệu lấy từ snapshot bất biến tại thời điểm chốt</small></div>
<?php if (!$released): ?><div class="empty"><i class="bi bi-clock-history"></i>Chưa có phiên bản nào được chốt.</div><?php else: ?>
<div class="table-responsive"><table class="table rh-table"><thead><tr><th>Phiên bản</th><th>Thời điểm chốt</th><th>Người chốt</th><th class="text-end">Thay đổi</th><th>Xuất</th></tr></thead><tbody>
<?php foreach ($released as $r): ?><tr><td><a class="fw-semibold" href="<?= e(url('releases/' . $r['id'])) ?>"><i class="bi bi-patch-check text-success"></i> <?= e($r['name']) ?></a></td>
    <td><?= e(vn_dt($r['released_at'])) ?></td><td><?= e($r['released_by_name'] ?? '—') ?></td><td class="text-end"><?= e($r['item_count']) ?></td>
    <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('releases/' . $r['id'] . '/notes.md')) ?>"><i class="bi bi-markdown"></i> Markdown</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('releases/' . $r['id'] . '/notes.json')) ?>"><i class="bi bi-filetype-json"></i> JSON</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div>
