<?php /** @var int $status @var string $message */
$titles = [403 => 'Không có quyền truy cập', 404 => 'Không tìm thấy', 500 => 'Lỗi hệ thống']; ?>
<div class="<?= isset($user) && $user ? '' : 'container py-5' ?>" style="max-width:680px;margin:auto">
    <div class="rh-card empty mt-4">
        <i class="bi <?= $status === 403 ? 'bi-shield-lock' : ($status === 404 ? 'bi-search' : 'bi-exclamation-octagon') ?>"></i>
        <h1 class="h4"><?= e($status) ?> – <?= e($titles[$status] ?? 'Đã xảy ra lỗi') ?></h1>
        <p><?= e($message) ?></p>
        <a class="btn btn-primary" href="<?= e(url('')) ?>"><i class="bi bi-house"></i> Về trang chủ</a>
    </div>
</div>
