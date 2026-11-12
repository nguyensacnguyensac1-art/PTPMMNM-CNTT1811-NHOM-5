<?php /** @var array $filters @var string $sort @var string $dir @var array $result @var array $members @var array $releaseList @var bool $scopeAll @var ?array $project */
use ReleaseHub\Domain\Catalog;
if (!$scopeAll) { echo partial('project_head', ['project' => $project, 'tab' => 'issues', 'role' => $role, 'can' => $can]); }
else { echo '<div class="mb-3"><h1 class="page-title">Tất cả công việc</h1><p class="page-sub">Lỗi, công việc và cải tiến của mọi dự án bạn được xem</p></div>'; }
$f = fn (string $k) => $filters[$k] ?? '';
$base = $scopeAll ? 'issues' : 'projects/' . $project['id'] . '/issues';
$sortLink = function (string $col, string $label) use ($sort, $dir, $base) {
    $next = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $icon = $sort === $col ? ($dir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : '';
    return '<a class="sort" href="' . e(url($base) . qs(['sort' => $col, 'dir' => $next, 'page' => null])) . '">' . e($label) . $icon . '</a>';
}; ?>
<form class="rh-card p-3 mb-3" method="get" role="search" aria-label="Bộ lọc công việc">
    <div class="row g-2 align-items-end">
        <div class="col-md-4 col-xl-3"><label class="form-label" for="q">Tìm kiếm</label><input class="form-control" id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Mã, tiêu đề, mô tả"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="type">Loại</label><select class="form-select" id="type" name="type"><option value="">Tất cả</option><?php foreach (Catalog::TYPES as $k => $l): ?><option value="<?= e($k) ?>" <?= $f('type') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="status">Trạng thái</label><select class="form-select" id="status" name="status"><option value="">Tất cả</option><?php foreach (Catalog::STATUSES as $k => $l): ?><option value="<?= e($k) ?>" <?= $f('status') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="priority">Ưu tiên</label><select class="form-select" id="priority" name="priority"><option value="">Tất cả</option><?php foreach (Catalog::PRIORITIES as $k => $l): ?><option value="<?= e($k) ?>" <?= $f('priority') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="severity">Nghiêm trọng</label><select class="form-select" id="severity" name="severity"><option value="">Tất cả</option><?php foreach (Catalog::SEVERITIES as $k => $l): ?><option value="<?= e($k) ?>" <?= $f('severity') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <?php if (!$scopeAll): ?>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="assignee_id">Người xử lý</label><select class="form-select" id="assignee_id" name="assignee_id"><option value="">Tất cả</option><option value="none" <?= $f('assignee_id') === 'none' ? 'selected' : '' ?>>Chưa phân công</option><?php foreach ($members as $m): ?><option value="<?= e($m['user_id']) ?>" <?= (string) $f('assignee_id') === (string) $m['user_id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="release_id">Phiên bản</label><select class="form-select" id="release_id" name="release_id"><option value="">Tất cả</option><?php foreach ($releaseList as $r): ?><option value="<?= e($r['id']) ?>" <?= (string) $f('release_id') === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="col-auto form-check ms-2 mb-2"><input class="form-check-input" type="checkbox" id="open_only" name="open_only" value="1" <?= $f('open_only') ? 'checked' : '' ?>><label class="form-check-label" for="open_only">Chỉ chưa hoàn thành</label></div>
        <div class="col-auto d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-funnel"></i> Lọc</button><a class="btn btn-light" href="<?= e(url($base)) ?>">Xóa lọc</a></div>
    </div>
</form>
<div class="rh-card">
    <div class="card-head"><span><?= e($result['total']) ?> kết quả</span><small class="text-muted fw-normal">Trang <?= e($result['page']) ?>/<?= e($result['pages']) ?></small></div>
    <?php if (!$result['rows']): ?><div class="empty"><i class="bi bi-search"></i>Không có công việc phù hợp bộ lọc.</div><?php else: ?>
    <div class="table-responsive"><table class="table rh-table">
        <thead><tr><th><?= $sortLink('code', 'Mã') ?></th><th><?= $sortLink('title', 'Tiêu đề') ?></th><th><?= $sortLink('type', 'Loại') ?></th><th><?= $sortLink('status', 'Trạng thái') ?></th>
            <th><?= $sortLink('priority', 'Ưu tiên') ?></th><th><?= $sortLink('severity', 'Nghiêm trọng') ?></th><th>Người xử lý</th><th><?= $sortLink('due_date', 'Hạn') ?></th><th><?= $sortLink('updated_at', 'Cập nhật') ?></th></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $i): ?><tr>
            <td><a class="code-link" href="<?= e(url('issues/' . $i['id'])) ?>"><?= e($i['code']) ?></a></td>
            <td class="title-cell"><a class="text-decoration-none text-body" href="<?= e(url('issues/' . $i['id'])) ?>"><?= e($i['title']) ?></a>
                <?php if ((int) $i['blocked_by_count'] > 0): ?> <span class="rh-badge badge-blocked" title="Đang bị chặn bởi phụ thuộc chưa xong"><i class="bi bi-lock"></i> Bị chặn <?= e($i['blocked_by_count']) ?></span><?php endif; ?></td>
            <td><?= type_badge($i['type']) ?></td><td><?= status_badge($i['status'], $i['type']) ?></td><td><?= priority_badge($i['priority']) ?></td><td><?= severity_badge($i['severity']) ?></td>
            <td><?= e($i['assignee_name'] ?? '—') ?></td><td class="text-nowrap"><?= e(vn_date($i['due_date'])) ?></td><td class="text-nowrap small text-muted"><?= e(vn_dt($i['updated_at'])) ?></td>
        </tr><?php endforeach; ?></tbody></table></div>
    <?php if ($result['pages'] > 1): $pg = $result['page']; ?>
    <nav class="p-3" aria-label="Phân trang"><ul class="pagination pagination-sm mb-0 flex-wrap">
        <li class="page-item <?= $pg <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(url($base) . qs(['page' => $pg - 1])) ?>">Trước</a></li>
        <?php for ($n = max(1, $pg - 3); $n <= min($result['pages'], $pg + 3); $n++): ?><li class="page-item <?= $n === $pg ? 'active' : '' ?>"><a class="page-link" href="<?= e(url($base) . qs(['page' => $n])) ?>"><?= e($n) ?></a></li><?php endfor; ?>
        <li class="page-item <?= $pg >= $result['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(url($base) . qs(['page' => $pg + 1])) ?>">Sau</a></li>
    </ul></nav><?php endif; endif; ?>
</div>
