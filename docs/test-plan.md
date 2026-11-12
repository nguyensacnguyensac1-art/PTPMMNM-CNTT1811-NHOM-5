# Kế hoạch kiểm thử

Chiến lược: (1) unit test thuật toán thuần; (2) integration test dịch vụ với **MySQL/MariaDB thật** trong database riêng `*_test` (tự dựng lại schema từ migration; từ chối chạy nếu tên không kết thúc `_test` ⇒ không đụng database thật); (3) test **đồng thời thật** bằng nhiều tiến trình PHP với kết nối DB độc lập, cùng xuất phát tại một mốc thời gian (`tests/helpers/worker.php`) – *không* coi hai lời gọi nối tiếp là race; (4) test qua Apache thật (tùy chọn `APP_TEST_URL`); (5) E2E trình duyệt Chromium (Playwright) cho giao diện/JS.

## Năm phép thử bắt buộc
| # | Đầu vào | Bước chạy | Mong đợi | Test | Kết quả đã chạy |
|---|---|---|---|---|---|
| 1 | A→B, B→C có sẵn; thêm C→A | `DependencyService::add` | Từ chối 422 `cycle_detected`, chuỗi `CYC-3 → CYC-1 → CYC-2 → CYC-3`, không lưu cạnh | `DependencyServiceTest::testCycleRejectedWithExactChain`, `DependencyGraphServiceTest`, E2E "Hiện đúng chuỗi gây vòng" | Đạt |
| 2 | Release có Blocker chưa kiểm thử lại | `evaluate` rồi `finalize` | `ready=false`, `blockers_retested` chưa đạt; chốt ⇒ 409 `not_ready`, không có snapshot | `ReleaseFlowTest::testUnverifiedBlockerPreventsReadiness` | Đạt |
| 3 | Hai người cùng mở version 1, A lưu trước, B lưu sau | `IssueService::update` | B nhận 409 `version_conflict` kèm bản mới + bản nháp; dữ liệu của A không bị ghi đè | `OptimisticLockTest`, `ConcurrencyTest::testConcurrentEditsOnlyOneWinsTheOtherGets409`, E2E 2 phiên | Đạt |
| 4 | Thành viên chỉ thuộc A đổi ID sang việc của B | 21 đường đọc/ghi qua service + HTTP | 403 ở mọi đường; dữ liệu không đổi | `AuthorizationTest` (3 test), kiểm tra HTTP thủ công và E2E | Đạt |
| 5 | Chốt rồi sửa công việc nguồn | `finalize`, `update`, `transition` | Snapshot, release notes, checksum không đổi; trigger chặn sửa trực tiếp | `ReleaseFlowTest::testEditingIssueAfterReleaseDoesNotChangeSnapshot`, `…ImmutableAtDatabaseLevel`, E2E | Đạt |

## Các ca bổ sung (đã có test tự động)
| Nhóm | Ca | Test |
|---|---|---|
| Phụ thuộc | tự phụ thuộc, cạnh trùng, khác dự án (service + FK phức hợp ở DB), vượt quyền | `DependencyServiceTest` |
| Điều kiện phát hành | Blocker Resolved nhưng chưa Passed; Passed của bản sửa cũ không hợp lệ; metadata không vô hiệu hóa; phụ thuộc ngoài phiên bản vẫn chặn; bắt buộc vs không bắt buộc; Failed ⇒ Reopened | `ReleaseFlowTest` |
| Đồng thời | hai request thêm cạnh không tạo vòng (6 vòng lặp); 3 request chốt ⇒ đúng 1 thành công; chốt đua với mở lại (8 vòng) không tạo snapshot sai | `ConcurrencyTest` |
| Chốt | chốt lặp không trùng; chỉ Release Manager chốt; release notes từ snapshot; người ngoài/phiên bản chưa chốt bị chặn | `ReleaseFlowTest` |
| Workflow | chuyển trạng thái sai bị từ chối; Resolved/Verified chỉ qua luồng fix/test; hạn chế theo vai trò; version cũ ⇒ 409; task không có trường tái hiện | `WorkflowTest` |
| Phân quyền | Viewer chỉ đọc; vai trò khác nhau theo dự án; System Admin; PM cuối cùng không bị gỡ; danh sách/tìm kiếm/dashboard/export không lộ dữ liệu | `AuthorizationTest` |
| Seed | đúng 16/320/≥128/48; chạy lại không nhân đôi; không trùng tiêu đề/bước tái hiện; đồ thị không vòng; snapshot hợp lệ; đăng nhập demo được; phân bố kịch bản | `SeedTest` |
| Validation / XSS / CSRF | URL chỉ http(s), ngày, enum, độ dài; escape ở view đại diện; POST thiếu/giả CSRF ⇒ 403; ẩn danh ⇒ chuyển tới đăng nhập | `ValidateTest`, `EscapingTest`, `SecurityHttpTest` |
| Lưu thật | tải lại trang giữ dữ liệu (Kanban, chốt), ghi DB đọc lại | E2E + mọi integration test |
| Chặn tệp nội bộ | 17 tệp/thư mục nội bộ ⇒ 403/404; cổng public ⇒ 200 | `ApacheAccessTest` (cần `APP_TEST_URL`) |
| Giao diện | đăng nhập, biểu đồ, Kanban kéo thả + hoàn nguyên + menu thay thế, xung đột, đồ thị, chốt, tải release notes, 403, **không tràn ngang** ở 1366×768 / 1920×1080 / 390 px, không request ra ngoài | `tests/e2e/e2e.py` |

## Chưa kiểm thử (ghi rõ để không hiểu nhầm là đã đạt)
- Windows + XAMPP thật; PHPUnit 10/11 (chỉ chạy 9.6.17); Firefox/Safari; thiết bị di động thật; trình đọc màn hình.
- Kiểm thử tải/hiệu năng; quét bảo mật tự động; rò rỉ thông tin qua thời gian phản hồi.
- Ca giới hạn số lần đăng nhập sai (chưa có tính năng).
