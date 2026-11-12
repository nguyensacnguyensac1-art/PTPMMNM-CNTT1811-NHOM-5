<?php /** @var array $project @var array $graph @var array $issueOptions @var bool $canManage */
add_script('js/graph.js');
echo partial('project_head', ['project' => $project, 'tab' => 'graph', 'role' => $role, 'can' => $can]);
$byId = array_column($graph['nodes'], null, 'id'); $codes = array_column($issueOptions, 'code', 'id'); $titles = array_column($issueOptions, 'title', 'id'); ?>
<div class="row g-3">
<div class="col-xl-8">
    <div class="rh-card mb-3"><div class="card-head">Đồ thị phụ thuộc <span class="small fw-normal text-muted"><?= e(count($graph['nodes'])) ?> công việc · <?= e(count($graph['edges'])) ?> quan hệ</span></div>
        <div class="card-body-p">
            <div class="legend mb-2" aria-label="Chú giải">
                <span><i style="border-color:#94a3b8;background:#fff"></i> Chưa xong</span><span><i style="border-color:#22c55e;background:#f0fdf4"></i> Đã xong ✓</span><span><i style="border-color:#dc2626;background:#fff"></i> Lỗi Blocker</span>
                <span><i class="bi bi-arrow-right" style="border:0;background:none;width:auto"></i> Việc phải xong <strong>trước</strong> → việc phụ thuộc vào nó</span></div>
            <p class="small text-muted">Nhấn vào một nút để làm nổi bật chuỗi liên quan; nhấn đúp (hoặc Enter) để mở công việc.</p>
            <div class="graph-wrap" id="graph" tabindex="0" aria-label="Vùng đồ thị có thể cuộn"></div>
        </div></div>
    <div class="rh-card"><div class="card-head">Danh sách quan hệ (thay thế cho đồ thị)</div>
        <?php if (!$graph['edges']): ?><div class="empty"><i class="bi bi-diagram-3"></i>Chưa có quan hệ nào.</div><?php else: ?>
        <div class="table-responsive"><table class="table rh-table"><thead><tr><th>Công việc</th><th></th><th>Phụ thuộc vào (phải xong trước)</th><th>Trạng thái của việc tiền đề</th></tr></thead><tbody>
        <?php foreach ($graph['edges'] as $e): $a = $byId[$e['issue_id']] ?? null; $b = $byId[$e['depends_on_id']] ?? null; if (!$a || !$b) { continue; } ?>
            <tr><td><a class="code-link" href="<?= e(url('issues/' . $a['id'])) ?>"><?= e($a['code']) ?></a> <span class="small text-muted"><?= e(mb_strimwidth($a['title'], 0, 40, '…')) ?></span></td><td><i class="bi bi-arrow-left"></i></td>
                <td><a class="code-link" href="<?= e(url('issues/' . $b['id'])) ?>"><?= e($b['code']) ?></a> <span class="small text-muted"><?= e(mb_strimwidth($b['title'], 0, 40, '…')) ?></span></td>
                <td><?= status_badge($b['status'], $b['type']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div><?php endif; ?></div>
</div>
<div class="col-xl-4">
    <?php if ($canManage && !$project['archived_at']): ?>
    <form class="rh-card p-3 mb-3" id="dep-form" novalidate><h2 class="h6">Thêm quan hệ phụ thuộc</h2>
        <div class="mb-2"><label class="form-label" for="issue_id">Công việc A</label><select class="form-select" id="issue_id" name="issue_id"><option value="">— chọn —</option><?php foreach ($issueOptions as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['code']) ?> – <?= e(mb_strimwidth($o['title'], 0, 50, '…')) ?></option><?php endforeach; ?></select></div>
        <div class="text-center text-muted small my-1">phụ thuộc vào (B phải xong trước)</div>
        <div class="mb-3"><label class="form-label" for="depends_on_id">Công việc B</label><select class="form-select" id="depends_on_id" name="depends_on_id"><option value="">— chọn —</option><?php foreach ($issueOptions as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['code']) ?> – <?= e(mb_strimwidth($o['title'], 0, 50, '…')) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-link-45deg"></i> Thêm quan hệ</button>
        <div id="cycle-box" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
        <p class="form-text mt-2 mb-0">Từ chối: tự phụ thuộc, quan hệ trùng, khác dự án và quan hệ tạo vòng.</p></form>
    <?php endif; ?>
    <div class="rh-card"><div class="card-head">Công việc đang chặn tiến độ</div>
        <?php if (!$graph['blockers']): ?><div class="empty"><i class="bi bi-check2-circle"></i>Không có công việc nào đang chặn.</div><?php else: ?>
        <ul class="list-group list-group-flush"><?php foreach ($graph['blockers'] as $b): ?><li class="list-group-item"><a class="code-link" href="<?= e(url('issues/' . $b['id'])) ?>"><?= e($b['code']) ?></a> <?= status_badge($b['status'], $b['type']) ?>
            <div class="small text-muted"><?= e(mb_strimwidth($b['title'], 0, 60, '…')) ?></div><div class="small">Chặn <?= e($b['blocking_count']) ?> việc: <?= e($b['blocking_codes']) ?></div></li><?php endforeach; ?></ul><?php endif; ?></div>
</div></div>
<script type="application/json" id="graph-data"><?= json_encode(['nodes' => $graph['nodes'], 'edges' => $graph['edges']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
