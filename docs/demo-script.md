# Kịch bản bảo vệ (8–10 phút)

Chuẩn bị: Apache + MySQL chạy, DB vừa khởi tạo (không chạy cả hai cách), mở sẵn **2 trình duyệt/hồ sơ** (A và B) tại `http://localhost/releasehub/public/`, một cửa sổ phpMyAdmin. Mật khẩu chung `ReleaseHub@2026`.

| # | Thời gian | Làm gì | Tài khoản | Điểm cần nói |
|---|---|---|---|---|
| 1 | 0:00–1:00 | Giới thiệu: kiến trúc MVC/PDO, XAMPP, phpMyAdmin (16 dự án, 320 việc); mở Dashboard | `admin@…` | Số liệu thật theo quyền; tài nguyên chạy cục bộ; `.env` bị chặn (mở `/releasehub/.env` ⇒ 403) |
| 2 | 1:00–2:30 | Tạo lỗi → phân công → sửa → kiểm thử lại → xác nhận | PM `minh.pm` tạo & giao cho `huy.dev`; Dev ghi nhận bản sửa; Tester `tung.qa` ghi Passed | Mỗi bước backend kiểm tra vai trò; `fix_revision`; lịch sử/timeline; kiểm tra phpMyAdmin |
| 3 | 2:30–3:30 | Tab **Phụ thuộc** VOCAB: thêm VOCAB-3 phụ thuộc VOCAB-5 | `huy.dev` | Bị từ chối, hiện chuỗi `VOCAB-3 → VOCAB-5 → VOCAB-3`; DFS tự viết O(V+E); thử tự phụ thuộc/trùng |
| 4 | 3:30–4:30 | Phiên bản **PAY v1.1.0** bị Blocker chặn | `ha.rm` | Danh sách điều kiện, lý do cụ thể; nút Chốt khóa; thử gọi API chốt trực tiếp ⇒ 409 `not_ready` |
| 5 | 4:30–5:30 | Kiểm thử Passed cho bản sửa hiện tại rồi tạo bản sửa mới ⇒ Passed cũ vô hiệu | Tester + Dev | Resolved ≠ Passed; Passed gắn `fix_revision`; sửa metadata không vô hiệu hóa |
| 6 | 5:30–6:30 | Mở **cùng một lỗi** ở trình duyệt A và B; A lưu, B lưu | `huy.dev` (A), `lan.dev` (B) | 409, màn hình đối chiếu, giữ bản nháp, không tự ép lưu; optimistic locking `WHERE version = ?` |
| 7 | 6:30–7:15 | Dùng tài khoản chỉ thuộc VOCAB vào dự án PAY / sửa ID | `user.a` | 403 ở UI và API; danh sách dự án chỉ 1 |
| 8 | 7:15–8:15 | Chốt **VOCAB v1.1.0** (sẵn sàng) → xuất release notes `.md` | `binh.rm` | Kiểm tra lại trong transaction; chốt lặp không trùng; snapshot |
| 9 | 8:15–9:00 | Sửa tiêu đề công việc nguồn sau phát hành, mở lại Lịch sử phát hành/release notes | `minh.pm` | Snapshot cũ không đổi; thử `UPDATE release_snapshot_items` trong phpMyAdmin ⇒ trigger chặn |
| 10 | 9:00–10:00 | Chạy `vendor\bin\phpunit`; giới thiệu `docs/`, LICENSE, THIRD_PARTY | — | 87 test, 4 test đồng thời đa tiến trình; phần chưa kiểm chứng (Windows/PHPUnit 10) |

Dự phòng nếu hỏng giữa chừng: `php bin/reset.php --confirm=releasehub` (xóa sạch và dựng lại dữ liệu mẫu – chỉ dùng trước giờ demo).

## Câu hỏi vấn đáp thường gặp (trả lời ngắn)
- **MVC của em ở đâu?** Controller nhận request và trả response; Service giữ nghiệp vụ và phân quyền; Repository giữ SQL; View hiển thị. JS chỉ gọi API.
- **Vì sao PDO + prepared statements?** Tách dữ liệu khỏi câu lệnh nên chống SQL injection; tắt `EMULATE_PREPARES` để MySQL thật sự chuẩn bị câu lệnh; `ERRMODE_EXCEPTION` để lỗi không bị bỏ sót.
- **Phát hiện vòng thế nào?** Thêm A→B (A phụ thuộc B) tạo vòng ⇔ từ B đi theo các cạnh đã có tới được A. DFS lặp có mảng `parent` truy vết ra chuỗi. Mỗi đỉnh/cạnh duyệt một lần ⇒ **O(V+E)**.
- **Vì sao DFS lặp, không đệ quy?** Tránh tràn ngăn xếp với chuỗi dài (có test 20.000 đỉnh).
- **Hai request cùng thêm cạnh thì sao?** Mỗi request trong transaction và khóa hàng dự án (`FOR UPDATE`); request sau đọc đồ thị đã có cạnh của request trước nên bị từ chối. Có test đa tiến trình.
- **Optimistic locking là gì, khác pessimistic thế nào?** Không giữ khóa khi người dùng đang gõ; chỉ lúc lưu mới `UPDATE … WHERE version = ?` và tăng version; 0 dòng ⇒ 409. Pessimistic khóa trước, gây chờ lâu. Ta dùng khóa hàng (pessimistic) chỉ trong thời gian ngắn của một transaction.
- **Transaction để làm gì ở chốt phát hành?** Kiểm tra điều kiện, tạo snapshot và đổi trạng thái là một khối nguyên tử; lỗi giữa chừng thì rollback, không có snapshot dở dang.
- **Snapshot bất biến được bảo đảm thế nào?** Lưu bản sao dữ liệu (không chỉ `issue_id`), trigger chặn sửa/xóa, checksum SHA-256 phát hiện sửa trái phép, giao diện/export chỉ đọc snapshot.
- **Phân quyền thế nào, có tin vào giao diện không?** Không; mọi service gọi `AuthorizationService`. Mỗi người một vai trò mỗi dự án, có thể khác ở dự án khác; không phải thành viên ⇒ 403.
- **Passed của bản sửa cũ có hợp lệ không?** Không: kết quả gắn `fix_revision`; bản sửa mới tăng revision nên cần kiểm thử lại; sửa metadata thì không.
- **Hạn chế còn lại?** Xem README mục "Giới hạn đã biết" và `test-results.md`.
- **Sửa một yêu cầu nhỏ tại chỗ** (gợi ý): thêm trạng thái/nhãn mới ⇒ `Catalog` + `WorkflowRules`; đổi ma trận quyền ⇒ `Permissions`; thêm điều kiện phát hành ⇒ thêm khối trong `ReleaseReadinessService::evaluate` và một test trong `ReleaseFlowTest`.
