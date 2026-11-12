<?php /** @var ?array $project @var array $members */
$edit = $project !== null; $icons = ['bi-kanban', 'bi-translate', 'bi-credit-card', 'bi-house-gear', 'bi-heart-pulse', 'bi-mortarboard', 'bi-bag', 'bi-airplane', 'bi-robot', 'bi-briefcase', 'bi-box-seam', 'bi-ticket-perforated', 'bi-wallet2', 'bi-recycle', 'bi-cloud-arrow-up']; ?>
<?php if ($edit): echo partial('project_head', ['project' => $project, 'tab' => 'settings', 'role' => $role, 'can' => $can]); endif; ?>
<div class="row"><div class="col-lg-8">
<form class="rh-card p-4" data-api="<?= e($edit ? 'api/projects/' . $project['id'] : 'api/projects') ?>" data-success="<?= $edit ? 'Đã cập nhật dự án.' : 'Đã tạo dự án.' ?>" novalidate>
    <h1 class="h5 mb-3"><?= $edit ? 'Cài đặt dự án' : 'Tạo dự án mới' ?></h1>
    <div class="row g-3">
        <?php if (!$edit): ?><div class="col-md-4"><label class="form-label req" for="code">Mã dự án</label><input class="form-control text-uppercase" id="code" name="code" maxlength="16" placeholder="VD: VOCAB" required><div class="form-text">2–16 chữ/số, không đổi được sau khi tạo.</div></div><?php endif; ?>
        <div class="col-md-<?= $edit ? '12' : '8' ?>"><label class="form-label req" for="name">Tên dự án</label><input class="form-control" id="name" name="name" maxlength="150" value="<?= e($project['name'] ?? '') ?>" required></div>
        <div class="col-12"><label class="form-label" for="description">Mô tả</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="5000"><?= e($project['description'] ?? '') ?></textarea></div>
        <div class="col-md-4"><label class="form-label" for="color">Màu nhận diện</label><input class="form-control form-control-color w-100" type="color" id="color" name="color" value="<?= e($project['color'] ?? '#4f46e5') ?>"></div>
        <div class="col-md-4"><label class="form-label" for="icon">Biểu tượng</label>
            <select class="form-select" id="icon" name="icon"><?php foreach ($icons as $i): ?><option value="<?= e($i) ?>" <?= ($project['icon'] ?? 'bi-kanban') === $i ? 'selected' : '' ?>><?= e(substr($i, 3)) ?></option><?php endforeach; ?></select></div>
        <?php if ($edit): ?><div class="col-md-4"><label class="form-label" for="owner_id">Người phụ trách</label>
            <select class="form-select" id="owner_id" name="owner_id"><?php foreach ($members as $m): ?><option value="<?= e($m['user_id']) ?>" <?= (int) $project['owner_id'] === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Lưu</button><a class="btn btn-light" href="<?= e(url($edit ? 'projects/' . $project['id'] : 'projects')) ?>">Hủy</a></div>
</form>
<?php if ($edit): $arch = $project['archived_at'] !== null; ?>
<div class="rh-card p-4 mt-3 border-danger-subtle"><h2 class="h6 text-danger">Vùng nguy hiểm</h2>
    <p class="small text-muted mb-2">Dự án không bị xóa để bảo toàn lịch sử. Khi lưu trữ, không thể tạo thêm công việc hoặc phiên bản.</p>
    <button class="btn btn-outline-danger" data-post="api/projects/<?= e($project['id']) ?>/archive" data-body='{"archived":<?= $arch ? 'false' : 'true' ?>}'
        data-confirm="<?= $arch ? 'Khôi phục dự án này?' : 'Lưu trữ dự án này? Mọi dữ liệu vẫn được giữ nguyên.' ?>" data-danger><?= $arch ? 'Khôi phục dự án' : 'Lưu trữ dự án' ?></button></div>
<?php endif; ?>
</div></div>
