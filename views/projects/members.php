<?php /** @var array $project @var array $list @var array $candidates */
use ReleaseHub\Domain\Catalog;
echo partial('project_head', ['project' => $project, 'tab' => 'members', 'role' => $role, 'can' => $can]); ?>
<div class="row g-3">
<div class="col-lg-8"><div class="rh-card"><div class="card-head">Thành viên (<?= e(count($list)) ?>)</div>
    <div class="table-responsive"><table class="table rh-table"><thead><tr><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Tham gia</th><?php if ($can['members']): ?><th></th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($list as $m): ?><tr>
        <td><span class="rh-avatar d-inline-grid me-2" style="width:28px;height:28px;font-size:.7rem" aria-hidden="true"><?= e(initials($m['full_name'])) ?></span><?= e($m['full_name']) ?></td>
        <td class="small"><?= e($m['email']) ?></td>
        <td><?php if ($can['members']): ?><select class="form-select form-select-sm js-role" style="min-width:160px" aria-label="Vai trò của <?= e($m['full_name']) ?>" data-path="api/projects/<?= e($project['id']) ?>/members/<?= e($m['user_id']) ?>/role" data-current="<?= e($m['role_code']) ?>">
            <?php foreach (Catalog::ROLES as $k => $l): ?><option value="<?= e($k) ?>" <?= $m['role_code'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <?php else: ?><?= e(Catalog::ROLES[$m['role_code']] ?? $m['role_code']) ?><?php endif; ?></td>
        <td class="small text-muted"><?= e(vn_dt($m['created_at'], 'd/m/Y')) ?></td>
        <?php if ($can['members']): ?><td class="text-end"><button class="btn btn-sm btn-outline-danger" data-post="api/projects/<?= e($project['id']) ?>/members/<?= e($m['user_id']) ?>/remove" data-confirm="Gỡ <?= e($m['full_name']) ?> khỏi dự án? Các công việc đang giao cho họ sẽ được bỏ phân công." data-danger data-success="Đã gỡ thành viên."><i class="bi bi-person-dash"></i> Gỡ</button></td><?php endif; ?>
    </tr><?php endforeach; ?></tbody></table></div></div></div>
<?php if ($can['members']): ?><div class="col-lg-4"><form class="rh-card p-3" data-api="api/projects/<?= e($project['id']) ?>/members" data-reload data-success="Đã thêm thành viên." novalidate>
    <h2 class="h6">Thêm thành viên</h2>
    <div class="mb-2"><label class="form-label req" for="email">Người dùng</label><input class="form-control" list="cand" id="email" name="email" placeholder="Nhập hoặc chọn email" required>
        <datalist id="cand"><?php foreach ($candidates as $c): ?><option value="<?= e($c['email']) ?>"><?= e($c['full_name']) ?></option><?php endforeach; ?></datalist></div>
    <div class="mb-3"><label class="form-label req" for="role">Vai trò</label><select class="form-select" id="role" name="role"><?php foreach (Catalog::ROLES as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === 'developer' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus"></i> Thêm</button>
    <p class="form-text mt-2 mb-0">Mỗi người có một vai trò trong mỗi dự án và có thể có vai trò khác ở dự án khác.</p></form></div><?php endif; ?>
</div>
<script>document.querySelectorAll('.js-role').forEach(function(s){s.addEventListener('change',async function(){var r=await RH.api('POST',s.dataset.path,{role:s.value});if(!r.ok){RH.fail(r);s.value=s.dataset.current;return;}s.dataset.current=s.value;RH.toast('Đã đổi vai trò.','success');});});</script>
