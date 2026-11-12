<?php /** @var array $project @var string $tab @var string $role @var array $can */
$id = (int) $project['id'];
$tabs = ['overview' => ['Tổng quan', 'projects/' . $id], 'issues' => ['Công việc', 'projects/' . $id . '/issues'], 'board' => ['Kanban', 'projects/' . $id . '/board'],
    'graph' => ['Phụ thuộc', 'projects/' . $id . '/graph'], 'releases' => ['Phiên bản', 'projects/' . $id . '/releases'],
    'members' => ['Thành viên', 'projects/' . $id . '/members'], 'history' => ['Lịch sử', 'projects/' . $id . '/history']]; ?>
<div class="proj-head">
    <div class="proj-icon" style="background:<?= e($project['color']) ?>"><i class="bi <?= e($project['icon']) ?>"></i></div>
    <div class="flex-grow-1" style="min-width:0">
        <h1 class="page-title text-truncate"><?= e($project['name']) ?> <?php if ($project['archived_at']): ?><span class="badge text-bg-secondary fs-6">Đã lưu trữ</span><?php endif; ?></h1>
        <p class="page-sub"><span class="fw-semibold"><?= e($project['code']) ?></span> · Vai trò của bạn: <span class="fw-semibold"><?= e(role_label($role)) ?></span></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!empty($can['create_issue']) && !$project['archived_at']): ?><a class="btn btn-primary" href="<?= e(url('projects/' . $id . '/issues/new')) ?>"><i class="bi bi-plus-lg"></i> Tạo công việc</a><?php endif; ?>
        <?php if (!empty($can['manage'])): ?><a class="btn btn-outline-secondary" href="<?= e(url('projects/' . $id . '/edit')) ?>"><i class="bi bi-gear"></i> Cài đặt</a><?php endif; ?>
    </div>
</div>
<nav class="proj-tabs" aria-label="Mục của dự án">
    <?php foreach ($tabs as $key => [$label, $path]): ?>
        <a href="<?= e(url($path)) ?>" class="<?= ($tab ?? '') === $key ? 'active' : '' ?>" <?= ($tab ?? '') === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
