# Kết quả kiểm thử (ghi đúng những gì đã chạy)

## Môi trường thực tế đã dùng
Ubuntu 24.04 (sandbox, **không phải Windows/XAMPP**) · PHP 8.3.6 CLI + mod_php · MariaDB 10.11.14 · Apache 2.4.58 · PHPUnit **9.6.17** · Chromium (Playwright 1.56) · ứng dụng mount tại `http://127.0.0.1/releasehub/public/` (cùng cấu trúc thư mục con như XAMPP).

## Lệnh đã chạy và kết quả
| Lệnh | Kết quả |
|---|---|
| `APP_TEST_URL=http://127.0.0.1/releasehub phpunit` | **OK – 87 tests, 360 assertions** (31 s), 0 bỏ qua |
| `phpunit` (không đặt `APP_TEST_URL`) | OK – 87 tests, 332 assertions, **21 bị bỏ qua** (18 `ApacheAccessTest` + 3 `SecurityHttpTest`: cần Apache nên *không tính là đã kiểm chứng* khi chạy kiểu này) |
| `BASE=http://127.0.0.1/releasehub/public python3 tests/e2e/e2e.py` (DB vừa seed) | **32/32 PASS** (cũng đạt 32/32 trên máy chủ dev `php -S`) |
| `php bin/migrate.php` → `php bin/seed.php` (chạy 2 lần) | 16 dự án · 320 công việc · 244 phụ thuộc · 48 phiên bản; lần hai không thêm dữ liệu |
| `php bin/build-sql.php` rồi import `releasehub.sql` vào DB trống, so fingerprint từng bảng | Tương đương dữ liệu migration+seeder (trừ `password_hash`, `applied_at`); `bin/migrate.php` sau import: "Không có migration mới"; đăng nhập admin bằng DB import đạt |
| Cài lại từ **ZIP sạch** giải nén vào thư mục có dấu cách (Linux): `.env` từ `.env.example` → Cách A (`migrate`+`seed`) và Cách B (import SQL vào DB trống, rồi `migrate` ⇒ "Không có migration mới") → `php -S … public/router.php` → đăng nhập admin 302, dashboard 200, 16 thẻ dự án → `phpunit` | Đạt: 320 công việc ở cả hai DB; 87 tests OK (21 bỏ qua do không có Apache) |
| `php bin/reset.php` (không tham số) | Từ chối, in cảnh báo, exit 1 (không xóa gì) |

## Kiểm tra thủ công bằng curl/HTTP (đã làm)
- POST không CSRF ⇒ 403 `csrf_failed`; sai mật khẩu ⇒ 422; đúng ⇒ 302; API chưa đăng nhập bị chặn.
- `session_regenerate_id`: cookie `releasehub_sid` **đổi** sau đăng nhập; cookie có `HttpOnly`.
- `next=//evil.com/x` sau đăng nhập ⇒ chuyển về trang nội bộ (không open-redirect).
- `user.a` (chỉ VOCAB): `/projects/2`, `/issues/21`, `/releases/4`, `/api/projects/2/graph`, ghi `/api/issues/21` ⇒ 403; danh sách dự án chỉ 1 dự án.
- Hai lần POST sửa cùng `expected_version` ⇒ lần hai 409 kèm `current`/`draft`.
- Thêm phụ thuộc tạo vòng qua API ⇒ 422 `cycle_detected` với `cycle_text`.
- Apache: `.env`, `database/*.sql`, `storage/logs/app.log`, `composer.json/lock`, `app/`, `vendor/`, `bin/`, `tests/`, `docs/`, `README.md`, `public/.htaccess` ⇒ **403**; `/releasehub/` ⇒ 302 `public/`; `public/login` và CSS ⇒ 200.

## Lỗi được phát hiện nhờ kiểm thử và đã sửa
1. **CSRF trả 419 bị Apache đổi thành 500** (chỉ lộ khi chạy qua Apache) ⇒ đổi sang 403 `csrf_failed`.
2. Máy chủ dev `php -S` trả HTML 404 cho CSS/JS (E2E phát hiện) ⇒ thêm `public/router.php`; Apache/XAMPP không bị ảnh hưởng.
3. `SCRIPT_NAME` sai của `php -S` làm hỏng base URL với URL có đuôi tệp (`notes.md`) ⇒ `Request::detectBasePath()` chỉ tin `index.php`.
4. SQL danh sách phiên bản sai (500 ở bộ lọc công việc theo dự án) – phát hiện bằng smoke test HTTP.
5. Redirect `/releasehub/` làm lộ đường dẫn vật lý khi thư mục là symlink ⇒ thay bằng `index.php` redirect tương đối.
6. Seed: hai bước tái hiện trùng trong JOB (`SeedTest`) ⇒ sửa dữ liệu.
7. Seed không xác định: `audit_logs.created_at` của lần chốt dùng giờ hiện tại (so fingerprint phát hiện) ⇒ truyền mốc thời gian chốt.
8. Hai test của tôi sai (tiêu đề quá ngắn, dọn dữ liệu bị trigger bất biến chặn) ⇒ sửa test.

## Chi tiết từng test PHPUnit (testdox)
```
Escaping (ReleaseHub\Tests\Unit\Escaping)
 ✔ Helper escapes everything
 ✔ Badges escape unknown values
 ✔ Project list view escapes project fields
 ✔ Error view escapes message
 ✔ History text is plain text for view to escape

Validate (ReleaseHub\Tests\Unit\Validate)
 ✔ Url accepts only http and https
 ✔ Date enum and string limits

Apache Access (ReleaseHub\Tests\Integration\ApacheAccess)
 ✔ Internal file is denied with data set ".env"
 ✔ Internal file is denied with data set ".env.example"
 ✔ Internal file is denied with data set "database/releasehub.sql"
 ✔ Internal file is denied with data set "database/migrations/001_create_schema.sql"
 ✔ Internal file is denied with data set "storage/logs/app.log"
 ✔ Internal file is denied with data set "composer.json"
 ✔ Internal file is denied with data set "composer.lock"
 ✔ Internal file is denied with data set "phpunit.xml"
 ✔ Internal file is denied with data set "app/Core/Env.php"
 ✔ Internal file is denied with data set "app/routes.php"
 ✔ Internal file is denied with data set "vendor/autoload.php"
 ✔ Internal file is denied with data set "tests/bootstrap.php"
 ✔ Internal file is denied with data set "bin/seed.php"
 ✔ Internal file is denied with data set "views/layouts/app.php"
 ✔ Internal file is denied with data set "docs/architecture.md"
 ✔ Internal file is denied with data set "README.md"
 ✔ Internal file is denied with data set "public/.htaccess"
 ✔ Public entry is reachable

Authorization (ReleaseHub\Tests\Integration\Authorization)
 ✔ Member of a only cannot edit issue of b
 ✔ Every write and read path is isolated
 ✔ Lists and dashboard do not leak other projects
 ✔ Roles differ per project for same user
 ✔ Viewer is read only everywhere
 ✔ System admin sees everything but isolation case uses non admin
 ✔ Last project manager cannot be removed

Concurrency (ReleaseHub\Tests\Integration\Concurrency)
 ✔ Two requests cannot together create a cycle
 ✔ Concurrent finalize creates exactly one release
 ✔ Finalize racing with reopen never produces inconsistent snapshot
 ✔ Concurrent edits only one wins the other gets 409

Dependency Service (ReleaseHub\Tests\Integration\DependencyService)
 ✔ Cycle rejected with exact chain
 ✔ Self duplicate and cross project rejected
 ✔ Database itself rejects cross project edge
 ✔ Viewer and outsider cannot add or remove
 ✔ Remove then reverse edge allowed

Optimistic Lock (ReleaseHub\Tests\Integration\OptimisticLock)
 ✔ Second save gets conflict and does not overwrite
 ✔ History written in same transaction and rolled back on conflict
 ✔ Missing or invalid expected version rejected
 ✔ Validation failure keeps data and reports fields

Release Flow (ReleaseHub\Tests\Integration\ReleaseFlow)
 ✔ Unverified blocker prevents readiness
 ✔ Resolved is not passed
 ✔ Passed on old fix is invalid for new fix
 ✔ Metadata edit does not invalidate passed test
 ✔ Stale test revision rejected
 ✔ Failed test reopens and blocks
 ✔ Dependency outside release still blocks
 ✔ Required incomplete blocks but optional does not
 ✔ Attach rules
 ✔ Only release manager may finalize
 ✔ Double finalize creates no duplicates
 ✔ Editing issue after release does not change snapshot
 ✔ Snapshot and released data are immutable at database level
 ✔ Release notes come from snapshot and are grouped
 ✔ Notes blocked for outsider and unreleased

Security Http (ReleaseHub\Tests\Integration\SecurityHttp)
 ✔ Post without csrf token is rejected
 ✔ Post with forged csrf token is rejected
 ✔ Anonymous page redirects to login

Seed (ReleaseHub\Tests\Integration\Seed)
 ✔ Seed counts and idempotency
 ✔ No copied issue sets across projects
 ✔ Bugs have reproduction data and tasks do not
 ✔ Demo login works with real authentication
 ✔ All graphs acyclic and snapshots valid
 ✔ Release scenarios are present
 ✔ Membership variety

Workflow (ReleaseHub\Tests\Integration\Workflow)
 ✔ Happy path bug
 ✔ Invalid transitions rejected by backend
 ✔ Resolved and verified only through fix and test flows
 ✔ Role restrictions on transitions
 ✔ Developer cannot record test and tester cannot record fix
 ✔ Stale version on transition is conflict
 ✔ Invalid fix reference rejected
 ✔ Task workflow needs no reproduction fields
 ✔ Rule table is published and consistent
```

## Chi tiết E2E (Chromium)
```
PASS đăng nhập chuyển tới dashboard
PASS dashboard vẽ 3 biểu đồ Chart.js
PASS dashboard không lỗi console
PASS không có request tới host bên ngoài (chạy offline)
PASS responsive 1366x768: không tràn ngang
PASS responsive 1920x1080: không tràn ngang
PASS responsive mobile 390: không tràn ngang
PASS Kanban: kéo Open→Resolved bị backend từ chối, thẻ hoàn nguyên
PASS Kanban: có thông báo lý do
PASS Kanban: kéo Open→In Progress thành công và lưu DB
PASS Kanban: tải lại trang vẫn giữ trạng thái (dữ liệu thật)
PASS Kanban: menu thay thế đổi trạng thái được
PASS Phiên A lưu thành công
PASS Phiên B (lưu sau) thấy màn hình xung đột
PASS Xung đột hiển thị bản của Lan và bản mới nhất của Huy
PASS DB không bị ghi đè bởi lượt lưu sau
PASS Giữ bản nháp trong form sau khi xung đột
PASS expected_version được cập nhật nhưng chưa tự lưu
PASS Sau khi xem lại, người dùng chủ động lưu thì ghi được
PASS Đồ thị phụ thuộc có vẽ SVG
PASS Hiện đúng chuỗi gây vòng trên giao diện
PASS Nhấn nút làm nổi bật chuỗi liên quan
PASS Bản bị Blocker chặn: nút Chốt bị khóa
PASS Hiển thị nguyên nhân chặn Blocker
PASS Bản bị phụ thuộc chặn: nút Chốt bị khóa và nêu lý do
PASS Bản sẵn sàng: nút Chốt khả dụng
PASS Chốt thành công và chuyển sang trạng thái Đã phát hành
PASS Snapshot được tạo đúng số mục
PASS Trang phát hành vẫn hiển thị snapshot cũ
PASS Tải release notes Markdown từ snapshot
PASS Thành viên chỉ thuộc A vào dự án B: 403 có thông báo
PASS Danh sách dự án của user.a chỉ có 1 dự án

32/32 PASS
```

## Chưa chạy / chưa kiểm chứng (và cách chạy lại)
| Hạng mục | Lý do | Cách chạy lại |
|---|---|---|
| Windows + XAMPP thật, PowerShell | Không có trong môi trường làm việc | Làm theo `docs/xampp-setup.md`, ghi kết quả vào đây |
| PHPUnit 10/11 | Chỉ có 9.6.17; `composer.json` cho phép `^9.6 \|\| ^10.5` nhưng chưa thử | `composer install && vendor\bin\phpunit` |
| `composer.lock` | Sandbox không truy cập được packagist | `composer install` rồi commit `composer.lock` |
| Cài lại từ bản sao sạch **trên máy Windows** | Chưa thể | Giải nén ZIP vào thư mục mới, làm lại README |
| Firefox/Safari/thiết bị thật, trình đọc màn hình, kiểm thử tải | Ngoài phạm vi | Thủ công |
| Ảnh chụp màn hình | Không đính kèm; kết quả E2E thay cho minh họa (không thay thế kiểm thử nghiệp vụ) | `python tests/e2e/e2e.py` (có thể thêm `page.screenshot`) |
