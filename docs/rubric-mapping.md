# Đối chiếu rubric

> Tự đánh giá **theo bằng chứng hiện có**, không tự ghi đạt tối đa. Cột "Còn thiếu / rủi ro" nêu điều nhóm cần bổ sung. Điểm cuối do giảng viên quyết định.

| Tiêu chí (điểm) | Bằng chứng – mã nguồn | Test | Màn hình / cách demo | Còn thiếu / rủi ro |
|---|---|---|---|---|
| **Năm nhóm chức năng (3,0)** | N1: `ProjectService`, `IssueService`, `MemberService`, `IssueRepository::search` · N2: `IssueWorkflowService`, `WorkflowRules` · N3: `DependencyService`, `DependencyGraphService` · N4: `ReleaseService`, `ReleaseReadinessService` · N5: `ReleaseSnapshotService` | `WorkflowTest`, `DependencyServiceTest`, `ReleaseFlowTest`, `SeedTest`, E2E | Dự án, Công việc (lọc/sắp xếp/phân trang), Chi tiết lỗi, Kanban, Phụ thuộc, Phiên bản, Lịch sử phát hành, Thành viên (`docs/demo-script.md` bước 2–8) | Chưa có upload tệp (không bắt buộc); không có màn hình quản lý người dùng; commit/PR chỉ lưu và kiểm tra URL, chưa tích hợp GitHub API |
| **Đồ thị phụ thuộc & điều kiện phát hành (1,0)** | `DependencyGraphService` (DFS lặp O(V+E), truy vết), `ReleaseReadinessService` (6 điều kiện), `fix_revision` | `DependencyGraphServiceTest` (kể cả chuỗi 20.000 đỉnh), `ReleaseFlowTest` (9 ca điều kiện) | Phụ thuộc → thử thêm cạnh vòng; Phiên bản bị chặn với giải thích + liên kết | Chính sách "Blocker chỉ xét trong phiên bản" là lựa chọn của nhóm (`release-policy.md`) – cần giải thích được khi vấn đáp |
| **Xung đột & dữ liệu đã chốt (1,0)** | `IssueService::update` (`UPDATE … WHERE version = ?`, 409), `issue.js` (đối chiếu, giữ bản nháp), `release_snapshot_items` + trigger + checksum | `OptimisticLockTest`, `ConcurrencyTest`, `ReleaseFlowTest::testEditingIssueAfterRelease…`, E2E 2 phiên | Mở một lỗi ở hai trình duyệt; chốt rồi sửa công việc nguồn | Khóa lạc quan áp dụng cho công việc (đúng yêu cầu); dự án/phiên bản không có form đối chiếu xung đột |
| **Dữ liệu & an toàn (1,0)** | PDO prepared, `Database`, migration/seeder/SQL, `AuthorizationService`, CSRF (`Csrf`, `App`), `e()`, `Validate`, `.htaccess` | `AuthorizationTest`, `SecurityHttpTest`, `EscapingTest`, `ValidateTest`, `ApacheAccessTest`, `SeedTest` | Đăng nhập sai/đúng; dùng `user.a` truy cập dự án PAY; mở `.env` ⇒ 403 | Chưa có giới hạn đăng nhập sai, 2FA, quên mật khẩu; chưa chạy trên XAMPP thật; XSS test ở mức view đại diện |
| **Kiểm thử, sửa lỗi, hồi quy (1,5)** | `tests/`, `docs/test-plan.md`, `docs/test-results.md` (có mục "lỗi đã phát hiện và sửa") | 87 PHPUnit (360 assertion) + 32 E2E đã chạy thật | Bước 10 demo: chạy test | PHPUnit 10/11 và Windows chưa chạy; chưa có CI (có thể thêm GitHub Actions) |
| **Mã nguồn mở & bàn giao (1,5)** | `LICENSE` (MIT), `README.md`, `CONTRIBUTING.md`, `THIRD_PARTY_NOTICES.md`, `CHANGELOG.md`, templates `.github/`, `.gitignore`, `.env.example`, `docs/*` | — | README "Nộp bài trên GitHub" | **Nhóm phải điền**: tên chủ bản quyền, URL kho, quyền đọc cho giảng viên, GitHub Release cuối kỳ, mã commit 40 ký tự, tên ZIP; `composer.lock` chưa có; lịch sử commit/issue/PR phải là thật |
| **Đóng góp cá nhân (1,0)** | `docs/contributions.md` (mẫu để điền) | — | Mỗi thành viên giải thích được phần mình | **Nhóm phải điền theo thực tế**; không có số liệu commit giả trong kho |

## Đối chiếu yêu cầu kỹ thuật & tình huống sử dụng
| Yêu cầu (đề) | Có chưa | Ghi chú |
|---|---|---|
| Hai yêu cầu kỹ thuật (đồ thị có vòng; khóa lạc quan/xung đột) | Có | Xem hàng 2 và 3 |
| Hai tình huống sử dụng (vòng phụ thuộc; hai người cùng sửa) | Có, có thể demo | `demo-script.md` bước 3, 6 |
| Đối chiếu 5 bài lab | **Chưa** | Zip không có nội dung lab – xem cuối `docs/architecture.md`, nhóm điền bảng |
| Phân biệt yêu cầu gốc và phần mở rộng | Có | Gốc: 5 nhóm chức năng, 2 yêu cầu kỹ thuật, 2 tình huống, 5 phép thử. **Mở rộng do nhóm thêm**: `is_required` (việc không bắt buộc), tải JSON, lưu trữ dự án, checksum snapshot, nhật ký audit, dashboard có biểu đồ, E2E |
