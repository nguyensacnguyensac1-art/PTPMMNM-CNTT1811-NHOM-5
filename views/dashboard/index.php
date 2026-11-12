<?php
/** @var array $d @var array $user */
add_script('vendor/chartjs/chart.umd.js');
add_script('js/dashboard.js');
$k = $d['kpi'];
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
    <div><h1 class="page-title">Xin chào, <?= e($user['full_name']) ?> 👋</h1>
        <p class="page-sub">Tổng quan <?= e($d['project_count']) ?> dự án bạn có quyền truy cập.</p></div>
    <a class="btn btn-primary" href="<?= e(url('projects')) ?>"><i class="bi bi-kanban"></i> Xem dự án</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><a class="rh-card kpi" href="<?= e(url('issues?type=bug&open_only=1')) ?>"><span class="ic ic-red"><i class="bi bi-bug"></i></span><div><div class="num"><?= e($k['open_bugs']) ?></div><div class="lbl">Lỗi đang mở</div></div></a></div>
    <div class="col-6 col-xl-3"><a class="rh-card kpi" href="<?= e(url('issues?severity=blocker&open_only=1')) ?>"><span class="ic ic-amber"><i class="bi bi-sign-stop"></i></span><div><div class="num"><?= e($k['open_blockers']) ?></div><div class="lbl">Blocker chưa xử lý xong</div></div></a></div>
    <div class="col-6 col-xl-3"><a class="rh-card kpi" href="<?= e(url('issues?status=resolved&type=bug')) ?>"><span class="ic ic-sky"><i class="bi bi-hourglass-split"></i></span><div><div class="num"><?= e($k['awaiting_test']) ?></div><div class="lbl">Chờ kiểm thử lại</div></div></a></div>
    <div class="col-6 col-xl-3"><div class="rh-card kpi"><span class="ic ic-indigo"><i class="bi bi-box-seam"></i></span><div><div class="num"><?= e(count($d['upcoming_releases'])) ?></div><div class="lbl">Phiên bản sắp phát hành</div></div></div></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="rh-card h-100"><div class="card-head">Trạng thái công việc</div><div class="card-body-p"><?php if ($k['total_items']): ?><canvas id="chStatus" height="240" aria-label="Biểu đồ trạng thái công việc" role="img"></canvas><?php else: ?><div class="empty"><i class="bi bi-inbox"></i>Chưa có dữ liệu.</div><?php endif; ?></div></div></div>
    <div class="col-lg-4"><div class="rh-card h-100"><div class="card-head">Lỗi đang mở theo mức nghiêm trọng</div><div class="card-body-p"><canvas id="chSeverity" height="240" aria-label="Biểu đồ lỗi theo mức nghiêm trọng" role="img"></canvas></div></div></div>
    <div class="col-lg-4"><div class="rh-card h-100"><div class="card-head">Theo dự án</div><div class="card-body-p"><canvas id="chProjects" height="240" aria-label="Biểu đồ theo dự án" role="img"></canvas></div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-6"><div class="rh-card h-100"><div class="card-head">Phiên bản sắp phát hành</div>
        <?php if (!$d['upcoming_releases']): ?><div class="empty"><i class="bi bi-box-seam"></i>Chưa có phiên bản dự kiến.</div><?php else: ?>
        <div class="table-responsive"><table class="table rh-table"><thead><tr><th>Dự án</th><th>Phiên bản</th><th>Ngày dự kiến</th><th class="text-end">Việc</th></tr></thead><tbody>
        <?php foreach ($d['upcoming_releases'] as $r): ?><tr>
            <td><span class="fw-semibold"><?= e($r['project_code']) ?></span></td>
            <td><a href="<?= e(url('releases/' . $r['id'])) ?>"><?= e($r['name']) ?></a></td>
            <td><?= e(vn_date($r['planned_date'])) ?></td><td class="text-end"><?= e($r['item_count']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></div></div>
    <div class="col-lg-6"><div class="rh-card h-100"><div class="card-head">Việc của tôi</div>
        <?php if (!$d['my_items']): ?><div class="empty"><i class="bi bi-emoji-smile"></i>Không có việc nào đang giao cho bạn.</div><?php else: ?>
        <div class="table-responsive"><table class="table rh-table"><tbody>
        <?php foreach ($d['my_items'] as $i): ?><tr>
            <td><a class="code-link" href="<?= e(url('issues/' . $i['id'])) ?>"><?= e($i['code']) ?></a></td>
            <td class="title-cell"><?= e($i['title']) ?></td><td><?= status_badge($i['status'], $i['type']) ?></td>
            <td class="text-nowrap small"><?= e(vn_date($i['due_date'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></div></div>
    <div class="col-12"><div class="rh-card"><div class="card-head">Hoạt động gần đây</div>
        <?php if (!$d['activity']): ?><div class="empty"><i class="bi bi-activity"></i>Chưa có hoạt động.</div><?php else: ?>
        <ul class="list-group list-group-flush">
        <?php foreach ($d['activity'] as $a): ?><li class="list-group-item d-flex flex-wrap gap-2 justify-content-between">
            <span><strong><?= e($a['actor_name'] ?? 'Hệ thống') ?></strong> <?= e(history_text($a)) ?> –
              <a href="<?= e(url('issues/' . $a['issue_id'])) ?>"><?= e($a['issue_code']) ?></a> <span class="text-muted"><?= e(mb_strimwidth($a['issue_title'], 0, 60, '…')) ?></span></span>
            <small class="text-muted"><?= e(vn_dt($a['created_at'])) ?></small></li><?php endforeach; ?>
        </ul><?php endif; ?></div></div>
</div>
<script type="application/json" id="dash-data"><?= json_encode($d, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
