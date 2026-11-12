<?php /** @var array $project @var array $members @var string $defaultType */
use ReleaseHub\Domain\Catalog;
echo partial('project_head', ['project' => $project, 'tab' => 'issues', 'role' => $role, 'can' => ['create_issue' => false, 'manage' => false]]);
add_script('js/issue-form.js'); ?>
<div class="row"><div class="col-xl-9"><form class="rh-card p-4" id="issue-form" data-api="api/projects/<?= e($project['id']) ?>/issues" data-success="Đã tạo công việc." novalidate>
    <h1 class="h5 mb-3">Tạo lỗi / công việc</h1>
    <div class="row g-3">
        <div class="col-12"><label class="form-label req">Loại</label><div class="d-flex gap-3 flex-wrap">
            <?php foreach (Catalog::TYPES as $k => $l): ?><div class="form-check"><input class="form-check-input" type="radio" name="type" id="t_<?= e($k) ?>" value="<?= e($k) ?>" <?= $defaultType === $k ? 'checked' : '' ?>><label class="form-check-label" for="t_<?= e($k) ?>"><?= type_badge($k) ?></label></div><?php endforeach; ?></div></div>
        <div class="col-12"><label class="form-label req" for="title">Tiêu đề</label><input class="form-control" id="title" name="title" maxlength="200" required></div>
        <div class="col-12"><label class="form-label" for="description">Mô tả</label><textarea class="form-control" id="description" name="description" rows="3"></textarea></div>
        <div class="col-md-4"><label class="form-label" for="priority">Mức ưu tiên</label><select class="form-select" id="priority" name="priority"><?php foreach (Catalog::PRIORITIES as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === 'medium' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><div class="form-text">Mức ưu tiên: thứ tự cần xử lý.</div></div>
        <div class="col-md-4 bug-only"><label class="form-label req" for="severity">Mức nghiêm trọng</label><select class="form-select" id="severity" name="severity"><?php foreach (Catalog::SEVERITIES as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === 'major' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><div class="form-text">Mức nghiêm trọng: mức ảnh hưởng của lỗi (Blocker chặn phát hành).</div></div>
        <div class="col-md-4"><label class="form-label" for="due_date">Thời hạn</label><input class="form-control" type="date" id="due_date" name="due_date"></div>
        <?php if ($can['assign'] ?? false): ?><div class="col-md-6"><label class="form-label" for="assignee_id">Người xử lý</label><select class="form-select" id="assignee_id" name="assignee_id"><option value="">— chưa phân công —</option><?php foreach ($members as $m): if ($m['role_code'] !== 'viewer'): ?><option value="<?= e($m['user_id']) ?>"><?= e($m['full_name']) ?> (<?= e(Catalog::ROLES[$m['role_code']]) ?>)</option><?php endif; endforeach; ?></select></div><?php endif; ?>
        <div class="col-md-6 bug-only"><label class="form-label" for="environment">Môi trường xảy ra lỗi</label><input class="form-control" id="environment" name="environment" maxlength="255" placeholder="VD: Chrome 124 / Windows 11"></div>
        <div class="col-12 bug-only"><label class="form-label req" for="steps_to_reproduce">Các bước tái hiện</label><textarea class="form-control" id="steps_to_reproduce" name="steps_to_reproduce" rows="3"></textarea></div>
        <div class="col-md-6 bug-only"><label class="form-label req" for="expected_result">Kết quả mong đợi</label><textarea class="form-control" id="expected_result" name="expected_result" rows="2"></textarea></div>
        <div class="col-md-6 bug-only"><label class="form-label" for="actual_result">Kết quả thực tế</label><textarea class="form-control" id="actual_result" name="actual_result" rows="2"></textarea></div>
        <div class="col-md-6"><label class="form-label" for="commit_url">Liên kết commit</label><input class="form-control" id="commit_url" name="commit_url" placeholder="https://github.com/…/commit/…"></div>
        <div class="col-md-6"><label class="form-label" for="pr_url">Liên kết Pull Request</label><input class="form-control" id="pr_url" name="pr_url" placeholder="https://github.com/…/pull/…"></div>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Tạo</button><a class="btn btn-light" href="<?= e(url('projects/' . $project['id'] . '/issues')) ?>">Hủy</a></div>
</form></div></div>
