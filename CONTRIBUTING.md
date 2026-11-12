# Đóng góp cho ReleaseHub

1. Tạo nhánh từ `main`: `feature/<mô-tả-ngắn>` hoặc `fix/<mô-tả-ngắn>`.
2. Mỗi commit một thay đổi có ý nghĩa; thông điệp commit nêu **làm gì và vì sao** (tiếng Việt hoặc tiếng Anh, nhất quán).
3. Chạy kiểm thử trước khi mở Pull Request (cần database `releasehub_test`, xem `docs/xampp-setup.md`):
   ```powershell
   vendor\bin\phpunit
   ```
4. Quy tắc mã: PHP 8.2+, `declare(strict_types=1)`, PSR-12, tên tiếng Anh; nghiệp vụ nằm trong `app/Services`, truy vấn SQL nằm trong `app/Repositories`, controller chỉ điều phối.
5. Không đưa `.env`, mật khẩu, khóa bí mật, dữ liệu thật vào kho. Chỉ dùng dữ liệu giả.
6. Thay đổi quy tắc workflow/phân quyền/điều kiện phát hành phải cập nhật `docs/` và thêm test.
7. Mở Pull Request theo mẫu `.github/pull_request_template.md`, liên kết issue liên quan; cần ít nhất một thành viên khác review.
