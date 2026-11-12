<?php /** @var array $project @var array $cards @var string $role */
use ReleaseHub\Domain\Catalog;
add_script('js/kanban.js');
echo partial('project_head', ['project' => $project, 'tab' => 'board', 'role' => $role, 'can' => $can]);
$cols = Catalog::STATUS_ORDER; $canMove = $role !== 'viewer' && !$project['archived_at']; ?>
<div class="alert alert-info py-2 small"><i class="bi bi-info-circle"></i> Kéo thả thẻ hoặc dùng nút <i class="bi bi-three-dots"></i> để đổi trạng thái. Máy chủ kiểm tra quy tắc workflow và vai trò; "Đã sửa" chỉ đạt qua <em>Ghi nhận bản sửa</em>, "Đã xác nhận/Mở lại do test" chỉ qua <em>kết quả kiểm thử</em>.</div>
<div class="kb-wrap"><div class="kb-board" id="board" data-can-move="<?= $canMove ? '1' : '0' ?>" data-labels='<?= e(json_encode(Catalog::STATUSES, JSON_UNESCAPED_UNICODE)) ?>'>
<?php foreach ($cols as $s): $list = array_values(array_filter($cards, fn ($c) => $c['status'] === $s)); ?>
    <section class="kb-col" data-status="<?= e($s) ?>" aria-label="<?= e(Catalog::STATUSES[$s]) ?>">
        <h6><span><?= e(Catalog::STATUSES[$s]) ?></span><span class="count"><?= e(count($list)) ?></span></h6>
        <?php foreach ($list as $c): ?>
        <article class="kb-card" draggable="<?= $canMove ? 'true' : 'false' ?>" data-id="<?= e($c['id']) ?>" data-version="<?= e($c['version']) ?>" data-type="<?= e($c['type']) ?>">
            <div class="d-flex justify-content-between align-items-center"><a class="code-link small" href="<?= e(url('issues/' . $c['id'])) ?>"><?= e($c['code']) ?></a>
                <?php if ($canMove): ?><div class="dropdown"><button class="btn btn-sm btn-light py-0" data-bs-toggle="dropdown" aria-label="Đổi trạng thái <?= e($c['code']) ?>"><i class="bi bi-three-dots"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end small"><li><h6 class="dropdown-header">Chuyển sang…</h6></li>
                    <?php foreach ($cols as $t): if ($t !== $s): ?><li><button class="dropdown-item" data-move="<?= e($t) ?>"><?= e(Catalog::STATUSES[$t]) ?></button></li><?php endif; endforeach; ?></ul></div><?php endif; ?></div>
            <div class="t"><?= e($c['title']) ?></div>
            <div class="d-flex flex-wrap gap-1 align-items-center"><?= type_badge($c['type']) ?> <?= severity_badge($c['severity']) ?>
                <?php if ((int) $c['blocked_by_count'] > 0): ?><span class="rh-badge badge-blocked"><i class="bi bi-lock"></i> <?= e($c['blocked_by_count']) ?></span><?php endif; ?></div>
            <div class="small text-muted mt-1"><i class="bi bi-person"></i> <?= e($c['assignee_name'] ?? 'Chưa phân công') ?></div>
        </article>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?></div></div>
