# Đóng góp cá nhân

> **Nhóm điền theo thực tế.** Kho này **không** chứa lịch sử commit/issue/review/đóng góp bịa sẵn. Dùng `git shortlog -sne`, trang *Insights → Contributors* và các Pull Request thật để điền.

| Thành viên | MSSV | Phần phụ trách (module / tài liệu / test) | Commit / PR tiêu biểu (mã đầy đủ hoặc liên kết) | Review đã thực hiện | Có thể giải thích khi vấn đáp |
|---|---|---|---|---|---|
| `[điền]` | `[điền]` | `[điền]` | `[điền]` | `[điền]` | `[điền]` |
| `[điền]` | `[điền]` | `[điền]` | `[điền]` | `[điền]` | `[điền]` |

## Gợi ý phân chia để mỗi người có phần rõ ràng (không bắt buộc)
Đồ thị phụ thuộc + test · Điều kiện phát hành + snapshot · Khóa lạc quan + giao diện xung đột · Phân quyền + bảo mật · Seeder/dữ liệu 16 dự án · Giao diện/UX · Tài liệu, XAMPP, kịch bản demo.

## Quy trình nộp trên GitHub
1. Tạo kho (đặt tên theo yêu cầu của giảng viên), đẩy mã nguồn; đảm bảo `.env` **không** có trong kho.
2. *Settings → Collaborators*: mời tài khoản giảng viên với quyền **Read** (hoặc để kho công khai nếu được phép).
3. Làm theo `CONTRIBUTING.md` (nhánh → PR → review) để lịch sử thật phản ánh đóng góp từng người.
4. Cuối kỳ: tạo **GitHub Release** (ví dụ tag `v1.0.0-final`) cho *chính sản phẩm ReleaseHub*. Đây khác với "phiên bản" `v1.x.x` là **dữ liệu bên trong** ReleaseHub.
5. Ghi **mã commit đầy đủ 40 ký tự** (`git rev-parse HEAD`) của bản nộp vào báo cáo/README.
6. Đóng gói ZIP theo **tên nhóm thực tế** (kiểm tra file yêu cầu; *Đề tài số 5* không phải *nhóm số 5*), không kèm `vendor/`, `.env`, `storage/logs/*`.
