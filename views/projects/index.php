<?php /** @var array $items @var array $filters @var bool $canCreate */ ?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
    <div><h1 class="page-title">Dự án</h1><p class="page-sub"><?= e(count($items)) ?> dự án bạn có quyền truy cập</p></div>
    <?php if ($canCreate): ?><a class="btn btn-primary" href="<?= e(url('projects/new')) ?>"><i class="bi bi-plus-lg"></i> Tạo dự án</a><?php endif; ?>
</div>
<form class="rh-card p-3 mb-3 row g-2 align-items-end" method="get" role="search">
    <div class="col-md-6"><label class="form-label" for="q">Tìm kiếm</label><input class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Mã, tên hoặc mô tả dự án"></div>
    <div class="col-md-3"><label class="form-label" for="status">Trạng thái</label>
        <select class="form-select" id="status" name="status"><?php foreach (['active' => 'Đang hoạt động', 'archived' => 'Đã lưu trữ', 'all' => 'Tất cả'] as $k => $l): ?><option value="<?= e($k) ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Lọc</button><a class="btn btn-light" href="<?= e(url('projects')) ?>">Xóa lọc</a></div>
</form>
<?php if (!$items): ?>
    <div class="rh-card empty"><i class="bi bi-folder2-open"></i>Không có dự án phù hợp.</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($items as $p): $pct = $p['issue_total'] ? (int) round($p['issue_done'] * 100 / $p['issue_total']) : 0; ?>
        <div class="col-md-6 col-xl-4">
            <a class="rh-card project-card" href="<?= e(url('projects/' . $p['id'])) ?>">
                <div class="d-flex align-items-center gap-2">
                    <span class="proj-icon" style="background:<?= e($p['color']) ?>;width:40px;height:40px"><i class="bi <?= e($p['icon']) ?>"></i></span>
                    <div style="min-width:0"><div class="fw-bold text-truncate"><?= e($p['name']) ?></div><small class="text-muted"><?= e($p['code']) ?><?= $p['my_role'] ? ' · ' . e(role_label($p['my_role'])) : '' ?></small></div>
                </div>
                <div class="desc"><?= e($p['description']) ?></div>
                <div>
                    <div class="d-flex justify-content-between small mb-1"><span>Tiến độ</span><strong><?= e($pct) ?>%</strong></div>
                    <div class="progress" role="progressbar" aria-label="Tiến độ <?= e($p['code']) ?>" aria-valuenow="<?= e($pct) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= e($pct) ?>%"></div></div>
                </div>
                <div class="d-flex flex-wrap gap-3 small text-muted mt-auto">
                    <span><i class="bi bi-bug"></i> <?= e($p['open_bugs']) ?> lỗi mở</span><span><i class="bi bi-box-seam"></i> <?= e($p['planned_releases']) ?> phiên bản dự kiến</span><span><i class="bi bi-people"></i> <?= e($p['member_count']) ?></span>
                    <?php if ($p['owner_name']): ?><span><i class="bi bi-person"></i> <?= e($p['owner_name']) ?></span><?php endif; ?>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
