<?php /** @var array $project @var array $rows @var int $page @var int $pages @var int $total */
echo partial('project_head', ['project' => $project, 'tab' => 'history', 'role' => $role, 'can' => $can]); ?>
<div class="rh-card"><div class="card-head">Nhật ký thay đổi (<?= e($total) ?>) <a class="btn btn-sm btn-outline-primary" href="<?= e(url('projects/' . $project['id'] . '/release-history')) ?>"><i class="bi bi-clock-history"></i> Lịch sử phát hành</a></div>
<?php if (!$rows): ?><div class="empty"><i class="bi bi-activity"></i>Chưa có hoạt động.</div><?php else: ?>
<div class="table-responsive"><table class="table rh-table"><thead><tr><th>Thời điểm</th><th>Người thực hiện</th><th>Công việc</th><th>Thay đổi</th></tr></thead><tbody>
<?php foreach ($rows as $h): ?><tr><td class="text-nowrap small"><?= e(vn_dt($h['created_at'])) ?></td><td><?= e($h['actor_name'] ?? 'Hệ thống') ?></td>
    <td><a class="code-link" href="<?= e(url('issues/' . $h['issue_id'])) ?>"><?= e($h['issue_code']) ?></a> <span class="text-muted small"><?= e(mb_strimwidth($h['issue_title'], 0, 50, '…')) ?></span></td><td><?= e(history_text($h)) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($pages > 1): ?><nav class="p-3"><ul class="pagination pagination-sm mb-0"><li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(url('projects/' . $project['id'] . '/history?page=' . ($page - 1))) ?>">Trước</a></li>
<li class="page-item disabled"><span class="page-link"><?= e($page) ?>/<?= e($pages) ?></span></li><li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(url('projects/' . $project['id'] . '/history?page=' . ($page + 1))) ?>">Sau</a></li></ul></nav><?php endif; endif; ?></div>
