# Cài đặt và chạy trên XAMPP (Windows)

> Hướng dẫn này đã được đối chiếu với môi trường **Ubuntu + Apache 2.4 + mod_php 8.3 + MariaDB 10.11** (cùng cơ chế `.htaccess`, đường dẫn con `/releasehub/public/`). **Chưa** được chạy trên Windows/XAMPP thật – hãy tự xác minh theo mục "Kiểm tra" cuối trang.

Yêu cầu: XAMPP có **PHP ≥ 8.2** (kiểm tra: `C:\xampp\php\php.exe -v`), Composer, VS Code.
Nếu XAMPP của bạn mang PHP < 8.2, hãy cập nhật XAMPP (bản mới của Apache Friends có PHP 8.2/8.3).

## Các bước
1. **Kiểm tra PHP, Composer, extension** (PowerShell):
   ```powershell
   C:\xampp\php\php.exe -v
   C:\xampp\php\php.exe -m | Select-String -Pattern "pdo_mysql|mbstring|json|openssl"
   composer --version
   ```
   Thiếu `pdo_mysql`/`mbstring`: mở `C:\xampp\php\php.ini`, bỏ dấu `;` ở `extension=pdo_mysql` và `extension=mbstring`, khởi động lại Apache.
   Nếu `php` không có trong PATH, dùng đường dẫn đầy đủ `C:\xampp\php\php.exe` ở mọi lệnh bên dưới.
2. Mở **XAMPP Control Panel**.
3. **Start Apache và MySQL** (cả hai phải xanh).
4. Đặt mã nguồn vào `C:\xampp\htdocs\releasehub` (thư mục này phải chứa `public\`, `app\`, `.htaccess`…).
5. Mở thư mục bằng VS Code: `code "C:\xampp\htdocs\releasehub"` (đường dẫn có dấu cách thì đặt trong dấu nháy kép).
6. **Cài phụ thuộc** (chỉ để chạy test; ứng dụng không cần thư viện PHP runtime):
   ```powershell
   cd "C:\xampp\htdocs\releasehub"
   composer install
   ```
   Nếu `composer` không nhận: `C:\xampp\php\php.exe "C:\ProgramData\ComposerSetup\bin\composer.phar" install`. Sau lần đầu, **commit `composer.lock`** (kho hiện chưa có).
   *Ứng dụng vẫn chạy khi chưa `composer install`* nhờ autoloader dự phòng trong `app\bootstrap.php`.
7. **Tạo `.env`**:
   ```powershell
   Copy-Item .env.example .env
   ```
   Cấu hình mặc định cho XAMPP: `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=releasehub`, `DB_USERNAME=root`, `DB_PASSWORD=` (trống). MySQL ở cổng 3307 hoặc root có mật khẩu: sửa trong `.env`, **không** sửa mã nguồn.
   > `root` không mật khẩu chỉ phù hợp máy phát triển cục bộ; không dùng khi triển khai công khai. Đặt `APP_DEMO_MODE=false` khi triển khai thật.
8. Mở **http://localhost/phpmyadmin**.
9. Tạo database **`releasehub`**, đối chiếu (collation) **`utf8mb4_unicode_ci`**.
10. **Khởi tạo dữ liệu – CHỌN MỘT cách, không làm cả hai** (làm cả hai sẽ lỗi trùng hoặc nhân đôi dữ liệu):
    - **Cách A – migration + seeder:**
      ```powershell
      C:\xampp\php\php.exe bin\migrate.php
      C:\xampp\php\php.exe bin\seed.php
      ```
    - **Cách B – import SQL:** trong phpMyAdmin chọn database `releasehub` → **Import** → chọn `database\releasehub.sql` → Go (tệp ~0,5 MB, trigger dùng `DELIMITER` – phpMyAdmin hỗ trợ).
    Hai cách cho dữ liệu tương đương (16 dự án, 320 công việc, 244 phụ thuộc, 48 phiên bản).
11. Mở **http://localhost/releasehub/public/** (hoặc `http://localhost/releasehub/` – tự chuyển sang `public/`). Apache cổng khác (ví dụ 8080): `http://localhost:8080/releasehub/public/`.
12. Đăng nhập tài khoản demo (xem README), ví dụ `admin@releasehub.local` / `ReleaseHub@2026`.
13. **Kiểm tra lưu thật:** mở một lỗi → *Sửa* → đổi tiêu đề → Lưu → nhấn **F5** (tải lại) → mở phpMyAdmin → bảng `issues` → cột `title`/`version` đã đổi, bảng `issue_histories` có dòng mới.
14. **Chạy kiểm thử** với database riêng:
    - phpMyAdmin: tạo database `releasehub_test` (utf8mb4_unicode_ci). Bộ test tự dựng lại schema trên database này; tên bắt buộc kết thúc bằng `_test`, nếu không test từ chối chạy.
    - ```powershell
      vendor\bin\phpunit
      ```
    - Kiểm tra chặn tệp nội bộ qua Apache (test bị *bỏ qua* nếu thiếu biến):
      ```powershell
      $env:APP_TEST_URL = "http://localhost/releasehub"; vendor\bin\phpunit
      ```
    Test đồng thời mở thêm tiến trình PHP con (`PHP_BINARY`) nên cần `php` chạy được từ dòng lệnh.

## Bảo vệ tệp nội bộ
`.htaccess` ở thư mục gốc chặn (HTTP 403) `.env`, `*.sql`, `*.log`, `*.md`, `composer.*`, `vendor/`, `app/`, `bin/`, `database/`, `docs/`, `storage/`, `tests/`, `views/`. Cần `mod_rewrite` (XAMPP bật sẵn) và `AllowOverride All` cho `htdocs` (mặc định của XAMPP). Nếu bạn đã sửa `httpd.conf` và thấy `.env` mở được: kiểm tra hai điều này rồi **Restart Apache**.
Kiểm tra thủ công: mở `http://localhost/releasehub/.env` và `http://localhost/releasehub/database/releasehub.sql` – phải thấy "403 Forbidden".

### Tùy chọn nâng cao: VirtualHost trỏ vào `public`
Thêm vào `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    ServerName releasehub.local
    DocumentRoot "C:/xampp/htdocs/releasehub/public"
    <Directory "C:/xampp/htdocs/releasehub/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Thêm `127.0.0.1 releasehub.local` vào `C:\Windows\System32\drivers\etc\hosts`, restart Apache, mở `http://releasehub.local/`. Khi đó đặt `APP_URL=http://releasehub.local`. Base URL tự nhận đúng (không cần sửa mã).

## Xử lý lỗi thường gặp
| Triệu chứng | Nguyên nhân / cách xử lý |
|---|---|
| "Không thể kết nối cơ sở dữ liệu" / `Connection refused` | MySQL chưa Start hoặc **sai cổng**: xem cổng trong XAMPP (Config → my.ini) rồi sửa `DB_PORT` trong `.env`. Chi tiết kỹ thuật nằm trong `storage\logs\app.log`. |
| `Access denied for user 'root'` | `root` có mật khẩu: điền `DB_PASSWORD` trong `.env`. |
| `Unknown database 'releasehub'` | Chưa tạo database ở bước 9 (hoặc `DB_DATABASE` sai tên). |
| `could not find driver` | Thiếu `extension=pdo_mysql` trong `php.ini` (bước 1). Lưu ý `php.ini` của **CLI** và Apache là cùng tệp trong XAMPP (`C:\xampp\php\php.ini`). |
| Apache không Start / "Port 80 in use" | Cổng 80/443 bị chiếm (IIS, Skype, WSL…): đổi `Listen 8080` trong `httpd.conf` và dùng `http://localhost:8080/releasehub/public/`. |
| Trang 404, mất CSS, liên kết sai | Sai URL (thiếu `/public/`) hoặc `mod_rewrite`/`AllowOverride` chưa bật. Mở đúng `http://localhost/releasehub/public/`. |
| `vendor/autoload.php` not found | Chưa `composer install` – chỉ ảnh hưởng chạy test; ứng dụng dùng autoloader dự phòng. |
| `Class "PDO" not found` / `mb_strlen` undefined | Thiếu extension PHP (bước 1). |
| Đăng nhập xong quay lại trang đăng nhập | Thư mục lưu phiên PHP không ghi được (`session.save_path`) hoặc trình duyệt chặn cookie. |
| MySQL không khởi động | **Xem log trước**: `C:\xampp\mysql\data\mysql_error.log` và xung đột cổng 3306 (dịch vụ MySQL khác). **Không xóa** thư mục `data` hay tệp `ibdata1` để "sửa nhanh" – sẽ mất dữ liệu. |
| Test báo "TEST_DB_DATABASE phải kết thúc bằng _test" | Cố ý: bảo vệ database thật. Đặt `TEST_DB_DATABASE=releasehub_test` trong `.env`. |

## Kiểm tra bạn nên tự làm (vì chưa có bằng chứng trên máy Windows của bạn)
1. `C:\xampp\php\php.exe -v` ≥ 8.2 và `php -m` có `pdo_mysql`.
2. Đăng nhập được; dashboard hiện biểu đồ (không cần Internet – tắt WiFi để thử).
3. Sửa một lỗi, F5, kiểm tra trong phpMyAdmin (bước 13).
4. `http://localhost/releasehub/.env` → 403.
5. `vendor\bin\phpunit` → ghi lại số test đạt vào `docs/test-results.md`.
