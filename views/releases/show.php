<?php /** @var array $release @var array $project @var array $items @var array $snapshot @var ?array $readiness @var array $candidates @var ?array $integrity @var bool $can_plan @var bool $can_finalize @var array $members */
use ReleaseHub\Domain\Catalog;
$released = $release['status'] === 'released'; $pid = (int) $release['project_id'];
echo partial('project_head', ['project' => $project, 'tab' => 'releases', 'role' => $role, 'can' => $can]);
$ready = $readiness['ready'] ?? false; ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div><h2 class="h4 mb-1"><i class="bi bi-box-seam"></i> Phiên bản <?= e($release['name']) ?>
        <span class="rh-badge <?= $released ? 'st-verified' : 'st-in_progress' ?> align-middle"><i class="bi <?= $released ? 'bi-patch-check' : 'bi-calendar-event' ?>"></i> <?= $released ? 'Đã phát hành' : 'Dự kiến' ?></span></h2>
        <p class="text-muted mb-0"><?= $released ? 'Chốt lúc ' . e(vn_dt($release['released_at'])) . ' bởi ' . e($release['released_by_name'] ?? '—') : 'Ngày dự kiến: ' . e(vn_date($release['planned_date'])) . ' · Phụ trách: ' . e($release['owner_name'] ?? '—') ?></p>
        <?php if ($release['description']): ?><p class="mt-2 mb-0"><?= e($release['description']) ?></p><?php endif; ?></div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light" href="<?= e(url('projects/' . $pid . '/releases')) ?>"><i class="bi bi-arrow-left"></i> Danh sách</a>
        <?php if ($released): ?>
            <a class="btn btn-outline-primary" href="<?= e(url('releases/' . $release['id'] . '/notes.md')) ?>"><i class="bi bi-markdown"></i> Release notes (.md)</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('releases/' . $release['id'] . '/notes.json')) ?>"><i class="bi bi-filetype-json"></i> JSON</a>
        <?php elseif ($can_plan): ?><button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#relEdit"><i class="bi bi-pencil"></i> Sửa thông tin</button><?php endif; ?>
    </div>
</div>
<?php if ($released): ?>
<div class="alert alert-<?= ($integrity['ok'] ?? false) ? 'success' : 'danger' ?> d-flex align-items-center gap-2"><i class="bi <?= ($integrity['ok'] ?? false) ? 'bi-shield-check' : 'bi-shield-exclamation' ?> fs-5"></i>
    <div>Dữ liệu dưới đây là <strong>snapshot bất biến</strong> tại thời điểm chốt; sửa công việc nguồn sau này không làm thay đổi. Checksum: <code><?= e(substr((string) $release['snapshot_checksum'], 0, 16)) ?>…</code> – <?= ($integrity['ok'] ?? false) ? 'khớp với dữ liệu đã lưu.' : 'KHÔNG khớp, cần kiểm tra!' ?></div></div>
<div class="rh-card"><div class="card-head">Thay đổi trong phiên bản (<?= e(count($snapshot)) ?>)</div>
<div class="table-responsive"><table class="table rh-table"><thead><tr><th>Mã</th><th>Nội dung</th><th>Nhóm</th><th>Nghiêm trọng</th><th>Trạng thái khi chốt</th><th>Bản sửa</th><th>Bằng chứng kiểm thử</th><th>Phụ thuộc</th></tr></thead><tbody>
<?php foreach ($snapshot as $s): $ev = $s['test_evidence'] ? json_decode((string) $s['test_evidence'], true) : null; $deps = json_decode((string) $s['dependency_codes'], true) ?: []; ?>
<tr><td class="code-link"><?= e($s['issue_code']) ?></td><td class="title-cell"><div class="fw-semibold"><?= e($s['title']) ?></div><div class="small text-muted"><?= e(mb_strimwidth((string) $s['change_summary'], mb_strlen($s['title']) + 3, 140, '…')) ?></div></td>
    <td><?= e(Catalog::SNAPSHOT_CATEGORIES[$s['category']] ?? $s['category']) ?></td><td><?= severity_badge($s['severity']) ?></td><td><?= status_badge($s['status'], $s['type']) ?><?= (int) $s['is_required'] === 0 ? ' <span class="badge text-bg-light">không bắt buộc</span>' : '' ?></td>
    <td class="small"><?php if ($s['fix_ref']): ?><?php if (preg_match('#^https?://#', (string) $s['fix_ref'])): ?><a href="<?= e($s['fix_ref']) ?>" target="_blank" rel="noopener noreferrer">#<?= e($s['fix_revision']) ?> <i class="bi bi-box-arrow-up-right"></i></a><?php else: ?><code><?= e($s['fix_ref']) ?></code><?php endif; else: ?>—<?php endif; ?></td>
    <td class="small"><?php if ($ev): ?><span class="rh-badge <?= $ev['result'] === 'passed' ? 'st-verified' : 'st-reopened' ?>"><?= $ev['result'] === 'passed' ? 'Passed' : 'Failed' ?></span> <?= e($ev['tester']) ?> · <?= e(vn_dt($ev['tested_at'], 'd/m/Y')) ?><?php else: ?>—<?php endif; ?></td>
    <td class="small"><?= e($deps ? implode(', ', $deps) : '—') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php else: ?>
<div class="row g-3">
<div class="col-xl-5">
    <div class="rh-card mb-3">
        <div class="card-head">Điều kiện phát hành
            <span class="rh-badge <?= $ready ? 'st-verified' : 'st-reopened' ?>"><i class="bi <?= $ready ? 'bi-check-circle' : 'bi-x-circle' ?>"></i> <?= $ready ? 'Sẵn sàng' : 'Chưa đủ điều kiện' ?></span></div>
        <div class="card-body-p pb-2"><div class="d-flex justify-content-between small mb-1"><span>Hoàn thành</span><strong><?= e($readiness['progress']['done']) ?>/<?= e($readiness['progress']['total']) ?> (<?= e($readiness['progress']['percent']) ?>%)</strong></div>
            <div class="progress mb-2" role="progressbar" aria-label="Tiến độ phiên bản" aria-valuenow="<?= e($readiness['progress']['percent']) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= e($readiness['progress']['percent']) ?>%"></div></div></div>
        <?php foreach ($readiness['conditions'] as $c): ?><div class="cond <?= $c['passed'] ? 'ok' : 'bad' ?>"><i class="bi ico <?= $c['passed'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
            <div><div class="fw-semibold"><?= e($c['label']) ?> <span class="visually-hidden"><?= $c['passed'] ? '(đạt)' : '(chưa đạt)' ?></span></div><div class="small text-muted"><?= e($c['message']) ?></div>
            <?php if ($c['items']): ?><ul><?php foreach ($c['items'] as $it): ?><li><a href="<?= e(url(ltrim($it['path'], '/'))) ?>"><?= e($it['code']) ?></a> – <?= e($it['reason']) ?></li><?php endforeach; ?></ul><?php endif; ?></div></div><?php endforeach; ?>
        <?php if ($can_finalize): ?><div class="p-3 border-top">
            <button class="btn btn-success w-100" <?= $ready ? '' : 'disabled' ?> data-post="api/releases/<?= e($release['id']) ?>/finalize" data-success="Đã chốt phiên bản và tạo snapshot."
                data-confirm="Chốt phiên bản <?= e($release['name']) ?>? Danh sách thay đổi sẽ được lưu thành snapshot bất biến và không thể hoàn tác. Máy chủ sẽ kiểm tra lại điều kiện ngay lúc chốt."><i class="bi bi-lock"></i> Chốt phát hành</button>
            <?php if (!$ready): ?><div class="form-text text-center">Cần xử lý các điều kiện chưa đạt ở trên trước khi chốt.</div><?php endif; ?></div>
        <?php else: ?><div class="p-3 border-top small text-muted"><i class="bi bi-info-circle"></i> Chỉ Release Manager (hoặc System Admin) mới được chốt phát hành.</div><?php endif; ?>
    </div>
</div>
<div class="col-xl-7">
    <div class="rh-card mb-3"><div class="card-head">Công việc trong phiên bản (<?= e(count($items)) ?>)</div>
    <?php if (!$items): ?><div class="empty"><i class="bi bi-inbox"></i>Chưa gắn công việc nào.</div><?php else: ?>
    <div class="table-responsive"><table class="table rh-table"><thead><tr><th>Mã</th><th>Tiêu đề</th><th>Trạng thái</th><th>Nghiêm trọng</th><th>Bắt buộc</th><?php if ($can_plan): ?><th></th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><a class="code-link" href="<?= e(url('issues/' . $i['issue_id'])) ?>"><?= e($i['code']) ?></a></td><td class="title-cell"><?= e($i['title']) ?></td>
        <td><?= status_badge($i['status'], $i['type']) ?></td><td><?= severity_badge($i['severity']) ?></td>
        <td><?php if ($can_plan): ?><div class="form-check form-switch"><input class="form-check-input js-req" type="checkbox" data-path="api/releases/<?= e($release['id']) ?>/items/<?= e($i['issue_id']) ?>/required" aria-label="Bắt buộc cho <?= e($i['code']) ?>" <?= (int) $i['is_required'] ? 'checked' : '' ?>></div><?php else: ?><?= (int) $i['is_required'] ? 'Có' : 'Không' ?><?php endif; ?></td>
        <?php if ($can_plan): ?><td class="text-end"><button class="btn btn-sm btn-outline-danger" aria-label="Gỡ <?= e($i['code']) ?>" data-post="api/releases/<?= e($release['id']) ?>/items/<?= e($i['issue_id']) ?>/remove" data-confirm="Gỡ <?= e($i['code']) ?> khỏi phiên bản?" data-danger data-success="Đã gỡ công việc."><i class="bi bi-x-lg"></i></button></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?></div>
    <?php if ($can_plan): ?><form class="rh-card p-3" data-api="api/releases/<?= e($release['id']) ?>/items" data-reload data-success="Đã gắn công việc." novalidate>
        <h2 class="h6">Gắn công việc (cùng dự án)</h2>
        <div class="row g-2 align-items-end"><div class="col-md-8"><label class="form-label" for="issue_id">Công việc</label><select class="form-select" id="issue_id" name="issue_id"><option value="">— chọn —</option><?php foreach ($candidates as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['code']) ?> – <?= e(mb_strimwidth($c['title'], 0, 60, '…')) ?> (<?= e(Catalog::statusLabel($c['status'], $c['type'])) ?>)</option><?php endforeach; ?></select></div>
        <div class="col-md-2 form-check ms-2 mb-2"><input class="form-check-input" type="checkbox" id="required" name="required" checked><label class="form-check-label" for="required">Bắt buộc</label></div>
        <div class="col-auto"><button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Gắn</button></div></div></form><?php endif; ?>
</div></div>
<?php if ($can_plan): ?>
<div class="modal fade" id="relEdit" tabindex="-1" aria-labelledby="relEditT" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" data-api="api/releases/<?= e($release['id']) ?>" data-reload data-success="Đã cập nhật phiên bản." novalidate>
    <div class="modal-header"><h5 class="modal-title" id="relEditT">Sửa thông tin phiên bản</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
    <div class="modal-body"><div class="mb-2"><label class="form-label req" for="rname">Mã phiên bản</label><input class="form-control" id="rname" name="name" value="<?= e($release['name']) ?>" maxlength="40" required></div>
    <div class="mb-2"><label class="form-label" for="rdate">Ngày dự kiến</label><input class="form-control" type="date" id="rdate" name="planned_date" value="<?= e($release['planned_date']) ?>"></div>
    <div class="mb-2"><label class="form-label" for="rowner">Người phụ trách</label><select class="form-select" id="rowner" name="owner_id"><option value="">— chưa chọn —</option><?php foreach ($members as $m): if ($m['role_code'] !== 'viewer'): ?><option value="<?= e($m['user_id']) ?>" <?= (int) $release['owner_id'] === (int) $m['user_id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?></option><?php endif; endforeach; ?></select></div>
    <div class="mb-2"><label class="form-label" for="rdesc">Mô tả</label><textarea class="form-control" id="rdesc" name="description" rows="3"><?= e($release['description']) ?></textarea></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button><button class="btn btn-primary" type="submit">Lưu</button></div></form></div></div>
<?php endif; ?>
<script>document.querySelectorAll('.js-req').forEach(function(c){c.addEventListener('change',async function(){var r=await RH.api('POST',c.dataset.path,{required:c.checked});if(!r.ok){RH.fail(r);c.checked=!c.checked;return;}location.reload();});});</script>
<?php endif; ?>
