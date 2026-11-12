<?php /** @var array $user @var array $sidebarProjects @var array $flash @var string $nav @var string $title @var string $content */ ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(base_url()) ?>">
    <title><?= e($title) ?> · ReleaseHub</title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Bỏ qua điều hướng</a>
<aside class="rh-sidebar" id="sidebar" aria-label="Điều hướng chính">
    <a class="rh-brand" href="<?= e(url('')) ?>"><i class="bi bi-rocket-takeoff"></i> ReleaseHub</a>
    <nav class="rh-nav">
        <a href="<?= e(url('')) ?>" class="<?= $nav === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> Tổng quan</a>
        <a href="<?= e(url('projects')) ?>" class="<?= $nav === 'projects' ? 'active' : '' ?>"><i class="bi bi-kanban"></i> Dự án</a>
        <a href="<?= e(url('issues')) ?>" class="<?= $nav === 'issues' ? 'active' : '' ?>"><i class="bi bi-bug"></i> Tất cả công việc</a>
        <div class="nav-label">Dự án của bạn</div>
        <?php foreach ($sidebarProjects as $sp): ?>
            <a href="<?= e(url('projects/' . $sp['id'])) ?>" title="<?= e($sp['name']) ?>">
                <span class="dot" style="background:<?= e($sp['color']) ?>"></span>
                <span class="pname"><span class="pcode fw-semibold"><?= e($sp['code']) ?></span> <span class="text-secondary">· <?= e($sp['name']) ?></span></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="rh-userbox">
        <div class="rh-avatar" aria-hidden="true"><?= e(initials($user['full_name'])) ?></div>
        <div class="flex-grow-1" style="min-width:0">
            <div class="uname"><?= e($user['full_name']) ?></div>
            <small><?= !empty($user['is_admin']) ? 'System Admin' : 'Thành viên' ?></small>
        </div>
        <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-light border-0" title="Đăng xuất" aria-label="Đăng xuất"><i class="bi bi-box-arrow-right"></i></button>
        </form>
    </div>
</aside>
<div class="rh-scrim" id="scrim"></div>
<div class="rh-main">
    <header class="rh-topbar">
        <button class="btn btn-light d-lg-none" id="menuBtn" aria-label="Mở menu" aria-controls="sidebar"><i class="bi bi-list"></i></button>
        <div class="fw-semibold text-truncate"><?= e($title) ?></div>
        <div class="ms-auto small text-muted d-none d-md-block"><i class="bi bi-clock"></i> Giờ Việt Nam (UTC+7)</div>
    </header>
    <main id="main" class="rh-content">
        <?php foreach ($flash as $f): ?>
            <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : e($f['type']) ?> alert-dismissible fade show" role="alert"><?= e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
</div>
<div class="toast-wrap" id="toasts" aria-live="polite" role="status"></div>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach (page_scripts() as $s): ?><script src="<?= e(asset($s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
