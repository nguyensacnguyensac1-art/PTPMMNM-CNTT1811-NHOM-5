<?php /** @var array $errors @var array $old @var string $next @var bool $demo */ ?>
<div class="auth-wrap">
    <section class="auth-hero">
        <div class="d-flex align-items-center gap-2 mb-4 fs-4 fw-bold text-white"><i class="bi bi-rocket-takeoff"></i> ReleaseHub</div>
        <h1>Theo dõi lỗi.<br>Lên kế hoạch phát hành.<br>Chốt phiên bản tự tin.</h1>
        <p class="lead mt-3">Quản lý lỗi, công việc, phụ thuộc và phiên bản của nhiều dự án phần mềm trong một nơi.</p>
        <ul class="list-unstyled">
            <li><i class="bi bi-check2-circle me-2"></i>Phát hiện vòng phụ thuộc trước khi lưu</li>
            <li><i class="bi bi-check2-circle me-2"></i>Điều kiện phát hành được máy chủ kiểm tra lại khi chốt</li>
            <li><i class="bi bi-check2-circle me-2"></i>Snapshot bất biến và ghi chú phát hành</li>
        </ul>
    </section>
    <section class="auth-form">
        <div class="auth-box">
            <h2 class="h4 fw-bold mb-1">Đăng nhập</h2>
            <p class="text-muted mb-4">Dùng tài khoản được cấp để tiếp tục.</p>
            <?php if (!empty($errors['form'])): ?><div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle"></i> <?= e($errors['form']) ?></div><?php endif; ?>
            <form method="post" action="<?= e(url('login')) ?>" novalidate>
                <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="mb-3">
                    <label class="form-label req" for="email">Email</label>
                    <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" type="email" autocomplete="username" value="<?= e($old['email']) ?>" required autofocus>
                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label req" for="password">Mật khẩu</label>
                    <input class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" type="password" autocomplete="current-password" required>
                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
                </div>
                <button class="btn btn-primary w-100 py-2" type="submit">Đăng nhập</button>
            </form>
            <?php if ($demo): ?>
                <div class="rh-card mt-4 p-3 small" id="demo-accounts">
                    <div class="fw-semibold mb-1"><i class="bi bi-info-circle"></i> Chế độ demo – mật khẩu chung: <code>ReleaseHub@2026</code></div>
                    <table class="demo-table w-100"><tbody>
                        <tr><th>Admin</th><td>admin@releasehub.local</td></tr>
                        <tr><th>Project Manager</th><td>minh.pm@releasehub.local</td></tr>
                        <tr><th>Release Manager</th><td>binh.rm@releasehub.local</td></tr>
                        <tr><th>Developer</th><td>huy.dev@releasehub.local</td></tr>
                        <tr><th>Tester</th><td>tung.qa@releasehub.local</td></tr>
                        <tr><th>Viewer</th><td>thu.view@releasehub.local</td></tr>
                        <tr><th>Chỉ dự án VOCAB</th><td>user.a@releasehub.local</td></tr>
                        <tr><th>Chỉ dự án PAY</th><td>user.b@releasehub.local</td></tr>
                    </tbody></table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
