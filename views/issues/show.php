<?php /** @var array $issue @var array $project @var string $role @var array $can @var array $transitions @var array $comments @var array $tests @var array $history @var array $depends_on @var array $blocks @var array $releases @var bool $has_valid_pass @var array $members @var array $candidates */
use ReleaseHub\Domain\Catalog;
add_script('js/issue.js');
$isBug = $issue['type'] === 'bug'; $id = (int) $issue['id']; $v = (int) $issue['version']; $archived = $project['archived_at'] !== null;
echo partial('project_head', ['project' => $project, 'tab' => 'issues', 'role' => $role, 'can' => ['create_issue' => false, 'manage' => false]]);
$link = fn (?string $u) => ($u && preg_match('#^https?://#i', $u)) ? '<a href="' . e($u) . '" target="_blank" rel="noopener noreferrer">' . e(mb_strimwidth($u, 0, 48, '…')) . ' <i class="bi bi-box-arrow-up-right"></i></a>' : ($u ? '<code>' . e($u) . '</code>' : '—'); ?>
<div id="issue-root" data-id="<?= e($id) ?>" data-version="<?= e($v) ?>" data-type="<?= e($issue['type']) ?>"></div>
<div class="row g-3">
<div class="col-lg-8">
    <div class="rh-card mb-3"><div class="card-body-p">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2"><span class="fw-bold"><?= e($issue['code']) ?></span><?= type_badge($issue['type']) ?><?= status_badge($issue['status'], $issue['type']) ?><?= priority_badge($issue['priority']) ?><?= severity_badge($issue['severity']) ?>
            <?php if ((int) array_sum(array_map(fn ($d) => $d['is_complete'] ? 0 : 1, $depends_on)) > 0): ?><span class="rh-badge badge-blocked"><i class="bi bi-lock"></i> Đang bị chặn bởi phụ thuộc chưa xong</span><?php endif; ?></div>
        <h2 class="h4"><?= e($issue['title']) ?></h2>
        <?php if ($issue['description']): ?><p class="pre-wrap"><?= e($issue['description']) ?></p><?php endif; ?>
        <?php if ($isBug): ?><dl class="kv row mt-3 mb-0">
            <div class="col-md-6"><dt>Môi trường</dt><dd><?= e($issue['environment'] ?: '—') ?></dd></div>
            <div class="col-12"><dt>Các bước tái hiện</dt><dd class="pre-wrap"><?= e($issue['steps_to_reproduce'] ?: '—') ?></dd></div>
            <div class="col-md-6"><dt>Kết quả mong đợi</dt><dd class="pre-wrap"><?= e($issue['expected_result'] ?: '—') ?></dd></div>
            <div class="col-md-6"><dt>Kết quả thực tế</dt><dd class="pre-wrap"><?= e($issue['actual_result'] ?: '—') ?></dd></div></dl><?php endif; ?>
    </div></div>

    <?php if ($isBug): ?>
    <div class="rh-card mb-3"><div class="card-head">Bản sửa &amp; kiểm thử lại
        <?php if ((int) $issue['fix_revision'] > 0): ?><span class="rh-badge <?= $has_valid_pass ? 'st-verified' : 'st-resolved' ?>"><i class="bi <?= $has_valid_pass ? 'bi-patch-check' : 'bi-hourglass-split' ?>"></i> <?= $has_valid_pass ? 'Passed trên bản sửa hiện tại' : 'Chưa có Passed cho bản sửa hiện tại' ?></span><?php endif; ?></div>
        <div class="card-body-p">
            <dl class="kv row mb-0"><div class="col-md-4"><dt>Bản sửa hiện tại</dt><dd><?= (int) $issue['fix_revision'] > 0 ? '#' . e($issue['fix_revision']) : 'Chưa có' ?></dd></div>
                <div class="col-md-8"><dt>Mã commit / PR</dt><dd><?= $link($issue['fix_ref']) ?></dd></div></dl>
            <?php if ($can['fix']): ?><form class="border rounded p-3 mb-3 bg-light" data-api="api/issues/<?= e($id) ?>/fix" data-reload data-success="Đã ghi nhận bản sửa, lỗi chuyển sang chờ kiểm thử." novalidate>
                <input type="hidden" name="expected_version" value="<?= e($v) ?>"><h3 class="h6">Ghi nhận bản sửa</h3>
                <div class="mb-2"><label class="form-label req" for="fix_ref">Mã commit (7–40 ký tự hex) hoặc liên kết PR/commit</label><input class="form-control" id="fix_ref" name="fix_ref" required placeholder="https://github.com/…/pull/12"></div>
                <div class="mb-2"><label class="form-label" for="fnote">Ghi chú</label><input class="form-control" id="fnote" name="note"></div>
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-wrench-adjustable"></i> Ghi nhận &amp; chuyển “Đã sửa”</button></form><?php endif; ?>
            <?php if ($can['test']): ?><form class="border rounded p-3 bg-light" data-api="api/issues/<?= e($id) ?>/tests" data-reload data-success="Đã ghi kết quả kiểm thử." novalidate>
                <input type="hidden" name="fix_revision" value="<?= e($issue['fix_revision']) ?>"><h3 class="h6">Kết quả kiểm thử bản sửa #<?= e($issue['fix_revision']) ?></h3>
                <div class="d-flex gap-3 mb-2"><div class="form-check"><input class="form-check-input" type="radio" name="result" id="rp" value="passed" checked><label class="form-check-label" for="rp"><span class="rh-badge st-verified">Passed</span></label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="result" id="rf" value="failed"><label class="form-check-label" for="rf"><span class="rh-badge st-reopened">Failed</span></label></div></div>
                <div class="mb-2"><label class="form-label" for="tnote">Ghi chú (bắt buộc khi Failed)</label><textarea class="form-control" id="tnote" name="note" rows="2"></textarea></div>
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-clipboard-check"></i> Ghi kết quả</button></form><?php endif; ?>
            <?php if ($tests): ?><h3 class="h6 mt-3">Lịch sử kiểm thử</h3><div class="table-responsive"><table class="table rh-table table-sm"><thead><tr><th>Kết quả</th><th>Bản sửa</th><th>Người kiểm tra</th><th>Thời điểm</th><th>Ghi chú</th></tr></thead><tbody>
            <?php foreach ($tests as $t): ?><tr><td><span class="rh-badge <?= $t['result'] === 'passed' ? 'st-verified' : 'st-reopened' ?>"><?= $t['result'] === 'passed' ? 'Passed' : 'Failed' ?></span></td>
                <td>#<?= e($t['fix_revision']) ?><?= (int) $t['fix_revision'] !== (int) $issue['fix_revision'] ? ' <span class="badge text-bg-secondary">bản cũ</span>' : '' ?></td><td><?= e($t['tester_name']) ?></td><td class="text-nowrap"><?= e(vn_dt($t['tested_at'])) ?></td><td class="small"><?= e($t['note']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </div></div>
    <?php endif; ?>

    <div class="rh-card mb-3"><div class="card-head">Bình luận (<?= e(count($comments)) ?>)</div><div class="card-body-p">
        <?php foreach ($comments as $c): ?><div class="comment"><div class="d-flex justify-content-between"><strong><?= e($c['author_name']) ?></strong><small class="text-muted"><?= e(vn_dt($c['created_at'])) ?></small></div><div class="pre-wrap"><?= e($c['body']) ?></div></div><?php endforeach; ?>
        <?php if (!$comments): ?><p class="text-muted mb-2">Chưa có bình luận.</p><?php endif; ?>
        <?php if ($can['comment']): ?><form class="mt-3" data-api="api/issues/<?= e($id) ?>/comments" data-reload data-success="Đã gửi bình luận." novalidate><label class="form-label" for="body">Thêm bình luận</label><textarea class="form-control mb-2" id="body" name="body" rows="3" maxlength="5000" required></textarea><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-send"></i> Gửi</button></form><?php endif; ?>
    </div></div>

    <div class="rh-card"><div class="card-head">Dòng thời gian</div><div class="card-body-p"><ul class="timeline">
        <?php foreach ($history as $h): ?><li><div><strong><?= e($h['actor_name'] ?? 'Hệ thống') ?></strong> <?= e(history_text($h)) ?></div><small class="text-muted"><?= e(vn_dt($h['created_at'])) ?></small></li><?php endforeach; ?></ul></div></div>
</div>

<div class="col-lg-4">
    <div class="rh-card mb-3"><div class="card-head">Chi tiết <?php if ($can['edit'] && !$archived): ?><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil"></i> Sửa</button><?php endif; ?></div>
        <div class="card-body-p"><dl class="kv mb-0">
            <dt>Người xử lý</dt><dd><?= e($issue['assignee_name'] ?? 'Chưa phân công') ?></dd><dt>Người tạo</dt><dd><?= e($issue['reporter_name'] ?? '—') ?></dd>
            <dt>Thời hạn</dt><dd><?= e(vn_date($issue['due_date'])) ?></dd><dt>Tạo lúc</dt><dd><?= e(vn_dt($issue['created_at'])) ?></dd><dt>Cập nhật</dt><dd><?= e(vn_dt($issue['updated_at'])) ?> <span class="text-muted">(phiên bản dữ liệu <?= e($v) ?>)</span></dd>
            <dt>Commit</dt><dd><?= $link($issue['commit_url']) ?></dd><dt>Pull Request</dt><dd><?= $link($issue['pr_url']) ?></dd>
            <dt>Phiên bản phát hành</dt><dd><?php foreach ($releases as $r): ?><a class="me-2" href="<?= e(url('releases/' . $r['id'])) ?>"><?= e($r['name']) ?></a><?php endforeach; ?><?= $releases ? '' : '—' ?></dd></dl></div></div>

    <?php if (!$archived): ?><div class="rh-card mb-3"><div class="card-head">Chuyển trạng thái</div><div class="card-body-p d-flex flex-wrap gap-2">
        <?php $any = false; foreach ($transitions as $t): $any = true;
            if ($t['via'] === 'transition'): ?><button class="btn btn-outline-primary btn-sm" data-post="api/issues/<?= e($id) ?>/transition" data-body='<?= e(json_encode(['to' => $t['to'], 'expected_version' => $v])) ?>' data-success="Đã chuyển trạng thái." title="<?= e($t['note']) ?>">→ <?= e(Catalog::statusLabel($t['to'], $issue['type'])) ?></button>
            <?php else: ?><span class="small text-muted w-100">→ <?= e(Catalog::statusLabel($t['to'], $issue['type'])) ?>: <?= e($t['note']) ?></span><?php endif; endforeach; ?>
        <?php if (!$any): ?><span class="text-muted small">Không có chuyển trạng thái nào dành cho vai trò của bạn ở trạng thái hiện tại.</span><?php endif; ?></div></div><?php endif; ?>

    <div class="rh-card"><div class="card-head">Phụ thuộc</div><div class="card-body-p">
        <h3 class="h6">Phụ thuộc vào (phải xong trước)</h3>
        <?php foreach ($depends_on as $d): ?><div class="d-flex justify-content-between align-items-center mb-1"><div><a class="code-link" href="<?= e(url('issues/' . $d['id'])) ?>"><?= e($d['code']) ?></a> <?= status_badge($d['status'], $d['type']) ?></div>
            <?php if ($can['dependency']): ?><button class="btn btn-sm btn-link text-danger" aria-label="Gỡ phụ thuộc <?= e($d['code']) ?>" data-post="api/dependencies/<?= e($d['dependency_id']) ?>/delete" data-confirm="Gỡ phụ thuộc vào <?= e($d['code']) ?>?" data-danger><i class="bi bi-x-lg"></i></button><?php endif; ?></div><?php endforeach; ?>
        <?php if (!$depends_on): ?><p class="text-muted small">Không có.</p><?php endif; ?>
        <h3 class="h6 mt-3">Đang chặn</h3>
        <?php foreach ($blocks as $d): ?><div class="mb-1"><a class="code-link" href="<?= e(url('issues/' . $d['id'])) ?>"><?= e($d['code']) ?></a> <?= status_badge($d['status'], $d['type']) ?></div><?php endforeach; ?>
        <?php if (!$blocks): ?><p class="text-muted small">Không có.</p><?php endif; ?>
        <?php if ($can['dependency'] && !$archived): ?><form class="mt-3" id="dep-add" novalidate><label class="form-label" for="dep_on">Thêm phụ thuộc vào</label><div class="input-group"><select class="form-select" id="dep_on" name="depends_on_id"><option value="">— chọn —</option><?php foreach ($candidates as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['code']) ?> – <?= e(mb_strimwidth($c['title'], 0, 36, '…')) ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Thêm</button></div>
            <div id="dep-err" class="cycle-chain mt-2 d-none" role="alert"></div></form><?php endif; ?></div></div>
</div></div>

<?php if ($can['edit'] && !$archived): ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editT" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="edit-form" novalidate>
    <div class="modal-header"><h5 class="modal-title" id="editT">Sửa <?= e($issue['code']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
    <div class="modal-body"><input type="hidden" name="expected_version" value="<?= e($v) ?>">
        <div id="conflict-alert" class="alert alert-warning d-none" role="alert"></div>
        <div class="row g-3">
            <div class="col-12"><label class="form-label req" for="e_title">Tiêu đề</label><input class="form-control" id="e_title" name="title" value="<?= e($issue['title']) ?>" maxlength="200" required></div>
            <div class="col-12"><label class="form-label" for="e_desc">Mô tả</label><textarea class="form-control" id="e_desc" name="description" rows="3"><?= e($issue['description']) ?></textarea></div>
            <div class="col-md-4"><label class="form-label" for="e_pri">Mức ưu tiên</label><select class="form-select" id="e_pri" name="priority"><?php foreach (Catalog::PRIORITIES as $k => $l): ?><option value="<?= e($k) ?>" <?= $issue['priority'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
            <?php if ($isBug): ?><div class="col-md-4"><label class="form-label" for="e_sev">Mức nghiêm trọng</label><select class="form-select" id="e_sev" name="severity"><?php foreach (Catalog::SEVERITIES as $k => $l): ?><option value="<?= e($k) ?>" <?= $issue['severity'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="col-md-4"><label class="form-label" for="e_due">Thời hạn</label><input class="form-control" type="date" id="e_due" name="due_date" value="<?= e($issue['due_date']) ?>"></div>
            <?php if ($can['assign']): ?><div class="col-md-6"><label class="form-label" for="e_asg">Người xử lý</label><select class="form-select" id="e_asg" name="assignee_id"><option value="">— chưa phân công —</option><?php foreach ($members as $m): if ($m['role_code'] !== 'viewer'): ?><option value="<?= e($m['user_id']) ?>" <?= (int) $issue['assignee_id'] === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?></option><?php endif; endforeach; ?></select></div><?php endif; ?>
            <?php if ($isBug): ?><div class="col-md-6"><label class="form-label" for="e_env">Môi trường</label><input class="form-control" id="e_env" name="environment" value="<?= e($issue['environment']) ?>"></div>
            <div class="col-12"><label class="form-label req" for="e_steps">Các bước tái hiện</label><textarea class="form-control" id="e_steps" name="steps_to_reproduce" rows="3"><?= e($issue['steps_to_reproduce']) ?></textarea></div>
            <div class="col-md-6"><label class="form-label req" for="e_exp">Kết quả mong đợi</label><textarea class="form-control" id="e_exp" name="expected_result" rows="2"><?= e($issue['expected_result']) ?></textarea></div>
            <div class="col-md-6"><label class="form-label" for="e_act">Kết quả thực tế</label><textarea class="form-control" id="e_act" name="actual_result" rows="2"><?= e($issue['actual_result']) ?></textarea></div><?php endif; ?>
            <div class="col-md-6"><label class="form-label" for="e_cu">Liên kết commit</label><input class="form-control" id="e_cu" name="commit_url" value="<?= e($issue['commit_url']) ?>"></div>
            <div class="col-md-6"><label class="form-label" for="e_pu">Liên kết PR</label><input class="form-control" id="e_pu" name="pr_url" value="<?= e($issue['pr_url']) ?>"></div>
        </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Lưu thay đổi</button></div></form></div></div>

<div class="modal fade" id="conflictModal" tabindex="-1" aria-labelledby="conflictT" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header bg-warning-subtle"><h5 class="modal-title" id="conflictT"><i class="bi bi-exclamation-triangle"></i> Xung đột chỉnh sửa</h5></div>
    <div class="modal-body"><p id="conflict-msg" class="mb-3"></p><div id="conflict-diff"></div>
        <p class="small text-muted mb-0">Nội dung bạn đang nhập vẫn được giữ trong biểu mẫu. Hệ thống <strong>không tự động ghi đè</strong>: bạn xem lại rồi tự quyết định lưu tiếp hay tải bản mới.</p></div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" id="conflict-copy"><i class="bi bi-clipboard"></i> Sao chép nội dung của tôi</button><button class="btn btn-outline-danger" id="conflict-reload">Bỏ thay đổi &amp; tải bản mới</button><button class="btn btn-primary" id="conflict-keep">Giữ nội dung của tôi và xem lại</button></div></div></div></div>
<?php endif; ?>
