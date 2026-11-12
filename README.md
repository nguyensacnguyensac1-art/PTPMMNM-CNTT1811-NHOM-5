# ReleaseHub – Hệ thống theo dõi lỗi và quản lý kế hoạch phát hành phần mềm

Bài tập lớn môn Phát triển phần mềm mã nguồn mở – **Đề tài số 5**. Một ứng dụng web (PHP 8.2+, MVC, MySQL/MariaDB, Bootstrap 5) quản lý lỗi, công việc, phụ thuộc và kế hoạch phát hành của **16 dự án phần mềm mẫu**.
Giao diện tiếng Việt; mã nguồn đặt tên tiếng Anh. Toàn bộ dữ liệu mẫu là **dữ liệu giả**.

> **Trạng thái kiểm chứng (trung thực):** đã chạy thật trên Ubuntu 24.04 + PHP 8.3.6 + MariaDB 10.11.14 + Apache 2.4.58, PHPUnit 9.6.17 (77 test) và 32 kịch bản E2E Chromium.
> **Chưa** kiểm tra trên Windows/XAMPP thật, chưa chạy với PHPUnit 10/11, chưa có `composer.lock`. Xem `docs/test-results.md`.

## Tính năng chính
- Dự án, thành viên theo vai trò **riêng từng dự án** (PM, Release Manager, Developer, Tester, Viewer) + System Admin.
- Bug / Task / Improvement; priority ≠ severity (Blocker là mức nghiêm trọng); tìm kiếm, lọc, sắp xếp, phân trang phía máy chủ; Kanban.
- Workflow `Open → In Progress → Resolved → Verified → Closed` + `Reopened`; bản sửa (`fix_revision`) và kết quả kiểm thử gắn với bản sửa hiện tại.
- Phụ thuộc "A phụ thuộc B"; **DFS tự viết** từ chối tự phụ thuộc, cạnh trùng, liên kết chéo dự án, vòng (trả chuỗi `C → A → B → C`); đồ thị SVG tự vẽ + danh sách thay thế.
- Kế hoạch phát hành, điều kiện phát hành do `ReleaseReadinessService` kiểm tra lại **trong transaction khi chốt**; snapshot bất biến (trigger + checksum); release notes Markdown/JSON từ snapshot.
- Khóa lạc quan (`version`, HTTP 409, giữ bản nháp, không ép lưu); khóa theo dự án cho thao tác đồng thời.

## Chạy nhanh trên XAMPP (Windows) – tóm tắt
Chi tiết từng bước, xử lý lỗi: **[`docs/xampp-setup.md`](docs/xampp-setup.md)**.
```powershell
# 1) Start Apache + MySQL trong XAMPP Control Panel; đặt mã nguồn tại C:\xampp\htdocs\releasehub
cd C:\xampp\htdocs\releasehub
composer install                      # hoặc: C:\xampp\php\php.exe <đường-dẫn>\composer.phar install
Copy-Item .env.example .env           # sửa DB_PORT / DB_PASSWORD nếu khác mặc định
# 2) phpMyAdmin (http://localhost/phpmyadmin): tạo database `releasehub`, collation utf8mb4_unicode_ci
# 3) CHỌN MỘT trong hai cách khởi tạo (KHÔNG làm cả hai):
#    Cách A: C:\xampp\php\php.exe bin\migrate.php ; C:\xampp\php\php.exe bin\seed.php
#    Cách B: Import database\releasehub.sql bằng phpMyAdmin
# 4) Mở http://localhost/releasehub/public/
```
Tài khoản demo (mật khẩu chung `ReleaseHub@2026`, chỉ hiện khi `APP_DEMO_MODE=true`):

| Vai trò | Email |
|---|---|
| System Admin | `admin@releasehub.local` |
| Project Manager (nhiều dự án) | `minh.pm@releasehub.local` |
| Release Manager (VOCAB, IOT, EDU… / PAY, HEALTH, SHOP…) | `binh.rm@…` / `ha.rm@…` |
| Developer / Tester / Viewer | `huy.dev@…` / `tung.qa@…` / `thu.view@…` |
| Chỉ thuộc dự án VOCAB / chỉ thuộc dự án PAY | `user.a@…` / `user.b@…` |
| Nhiều dự án, vai trò khác nhau (PM·Viewer·Tester·RM) | `multi@releasehub.local` |

## Lệnh thường dùng
| Việc | Lệnh |
|---|---|
| Áp dụng migration mới (không xóa dữ liệu) | `php bin/migrate.php` |
| Nạp dữ liệu mẫu (chạy lại không nhân đôi) | `php bin/seed.php` |
| Sinh lại `database/releasehub.sql` (dùng DB tạm `releasehub_sqlbuild`) | `php bin/build-sql.php` |
| **Reset** (xóa sạch rồi dựng lại, cần gõ tên DB để xác nhận) | `php bin/reset.php --confirm=releasehub` |
| Chạy test (DB `releasehub_test` riêng, tự dựng lại schema) | `vendor\bin\phpunit` |
| Kiểm tra chặn tệp nội bộ qua Apache | `$env:APP_TEST_URL="http://localhost/releasehub"; vendor\bin\phpunit --filter ApacheAccessTest` |
| E2E (tùy chọn, cần Python + Playwright) | `python tests/e2e/e2e.py` |
| Máy chủ dev không cần XAMPP | `php -S localhost:8000 -t public public/router.php` |

## Cấu trúc
```
app/Core         Env, Database(PDO), Router, Request/Response, Session, CSRF, View, Container, exceptions
app/Controllers  Mỏng: nhận request → gọi Service → trả view/JSON
app/Services     Nghiệp vụ: DependencyGraph, IssueWorkflow, ReleaseReadiness, ReleaseSnapshot, Authorization, ...
app/Repositories Toàn bộ SQL (PDO prepared statements)
app/Domain       Catalog (nhãn), Permissions (ma trận quyền), WorkflowRules (bảng chuyển trạng thái)
views/  public/  Giao diện PHP + Bootstrap 5 + JS (Fetch API); tài nguyên chạy cục bộ trong public/assets
database/        migrations/*.sql, Seeders/, releasehub.sql
docs/            Tài liệu kiến trúc, CSDL, phân quyền, workflow, chính sách phát hành, test, rubric, demo, XAMPP
```

## Tài liệu
[Kiến trúc](docs/architecture.md) · [CSDL & ERD](docs/database.md) · [Phân quyền](docs/permissions.md) · [Workflow](docs/workflow.md) · [Chính sách phát hành](docs/release-policy.md) ·
[Kế hoạch test](docs/test-plan.md) · [Kết quả test](docs/test-results.md) · [Đối chiếu rubric](docs/rubric-mapping.md) · [Kịch bản demo](docs/demo-script.md) ·
[Đóng góp cá nhân](docs/contributions.md) · [Cài đặt XAMPP](docs/xampp-setup.md)

## Nộp bài trên GitHub (điền theo thực tế của nhóm)
- Kho GitHub: `[điền URL]`; cấp quyền **Read** cho tài khoản giảng viên `[điền]`.
- **GitHub Release cuối kỳ** (tag, ví dụ `v1.0.0-final`) dùng để nộp *chính sản phẩm ReleaseHub*; khác với "phiên bản" (`v1.0.0`, `v1.1.0`…) là dữ liệu **bên trong** ReleaseHub.
- Ghi **mã commit đầy đủ (40 ký tự)** của bản nộp: `[điền]`.
- Tệp ZIP đặt tên theo **nhóm thực tế** của bạn: `[Nhóm-XX]_[MSSV]_ReleaseHub.zip` (không nhầm *Đề tài số 5* với *nhóm số 5*; kiểm tra lại tên nhóm trong file yêu cầu).

## Giới hạn đã biết
Không có: upload tệp đính kèm, quên/đổi mật khẩu, giới hạn số lần đăng nhập sai, gửi email, màn hình quản lý người dùng (người dùng tạo bằng seeder/SQL; thành viên dự án thêm bằng email của người dùng đã có), kiểm thử tải. Xem thêm `docs/test-results.md`.

## Giấy phép
MIT – xem [`LICENSE`](LICENSE) (điền tên chủ sở hữu bản quyền). Thư viện bên thứ ba: [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md).
