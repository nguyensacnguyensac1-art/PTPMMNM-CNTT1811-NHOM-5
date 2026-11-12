# Kiến trúc

## Phân lớp (MVC mở rộng)
```
Request ─► public/index.php ─► Core\App (route, CSRF, xác thực, xử lý lỗi)
        ─► Controller (mỏng) ─► Service (NGHIỆP VỤ + phân quyền + transaction) ─► Repository (SQL/PDO)
        ◄─ View (PHP + Bootstrap)  hoặc  JSON (Fetch API)
```
| Lớp | Thư mục | Trách nhiệm |
|---|---|---|
| Controller | `app/Controllers` | Đọc request, gọi service, trả view/JSON. Không chứa quy tắc nghiệp vụ. |
| Service | `app/Services` | Quy tắc nghiệp vụ, kiểm tra quyền (`AuthorizationService`), transaction, khóa. |
| Repository | `app/Repositories` | Câu lệnh SQL; luôn dùng prepared statements (`ATTR_EMULATE_PREPARES = false`). |
| View | `views/`, `public/assets` | Hiển thị; escape bằng `e()`; JS chỉ gọi API, không chứa nghiệp vụ. |
| Domain | `app/Domain` | Danh mục nhãn, ma trận quyền, bảng workflow (dùng chung cho backend, UI và tài liệu). |

Service tối thiểu theo đề: `DependencyGraphService`, `IssueWorkflowService`, `ReleaseReadinessService`, `ReleaseSnapshotService`, `AuthorizationService` (+ `IssueService`, `DependencyService`, `ReleaseService`, `ProjectService`, `MemberService`, `DashboardService`, `AuthService`).

## Cơ chế trọng tâm
**A. Đồ thị phụ thuộc** – Quy ước "A phụ thuộc B ⇒ B xong trước". Thêm cạnh A→B tạo vòng ⇔ B đã tới được A. `findCycleIfAdded` dùng **DFS lặp** (ngăn xếp tường minh, không đệ quy) với mảng `parent` để truy vết, trả `[A, B, …, A]`. Độ phức tạp **O(V+E)** thời gian, O(V) bộ nhớ (test với chuỗi 20.000 đỉnh). Thư viện **không** tham gia kiểm tra; đồ thị vẽ bằng SVG tự viết. Từ chối: tự phụ thuộc, cạnh trùng, liên kết chéo dự án (service **và** khóa ngoại phức hợp `(issue_id, project_id)`), vòng (HTTP 422 `cycle_detected` + `cycle_text`).

**Đồng thời / khóa theo dự án** – Mọi thao tác ghi dữ liệu của một dự án chạy trong transaction và bắt đầu bằng `SELECT id FROM projects WHERE id = ? FOR UPDATE` (`Database::lockProject`). Nhờ đó (1) hai request thêm cạnh không thể cùng tạo vòng (request sau đọc đồ thị đã có cạnh của request trước); (2) không có thay đổi nào xen giữa lúc kiểm tra điều kiện và chốt. Đánh đổi: các ghi trong cùng một dự án bị tuần tự hóa (chấp nhận được cho quy mô nhóm nhỏ; khác dự án chạy song song). Deadlock (SQLSTATE 40001) được thử lại tối đa 3 lần.

**B. Điều kiện phát hành** – `ReleaseReadinessService` (xem `release-policy.md`). Kết quả kiểm thử gắn với `fix_revision`; sửa metadata không vô hiệu hóa.

**C. Khóa lạc quan** – `issues.version` (INT). Form mang `expected_version`; `UPDATE issues SET …, version = version + 1 WHERE id = ? AND version = ?`; 0 dòng ⇒ **409** kèm bản mới nhất, bản nháp và danh sách trường khác biệt. Dữ liệu và lịch sử cùng một transaction; giao diện giữ nội dung đang nhập, cho đối chiếu và chỉ lưu khi người dùng chủ động nhấn lại.

**D. Snapshot bất biến** – xem `release-policy.md`.

## Bảo mật
| Yêu cầu | Cách làm |
|---|---|
| Băm mật khẩu | `password_hash(PASSWORD_DEFAULT)` / `password_verify` / `password_needs_rehash`; đối phó dò email bằng băm giả |
| SQL injection | PDO prepared statements thật; cột sắp xếp/lọc qua danh sách trắng |
| CSRF | Token theo phiên (`hash_equals`) cho mọi POST: field `_csrf` hoặc header `X-CSRF-Token`; sai ⇒ 403 (mã lỗi `csrf_failed`; không dùng 419 vì Apache đổi mã không chuẩn thành 500) |
| XSS | `e()` (`htmlspecialchars`) cho mọi nội dung; JS dùng `textContent`/`RH.esc`; JSON nhúng dùng `JSON_HEX_TAG` |
| Phiên | Cookie `HttpOnly`, `SameSite=Lax`, `use_strict_mode`; **`session_regenerate_id(true)` sau đăng nhập**; chống open-redirect ở `next` |
| Lỗi nội bộ | Ghi `storage/logs/app.log`, người dùng chỉ thấy thông báo chung; `APP_DEBUG=false` mặc định |
| URL commit/PR | Chỉ `http`/`https`, có host, không khoảng trắng (chặn `javascript:`…) |
| Tệp nội bộ | `.htaccess` gốc chặn `.env`, `.sql`, log, `vendor`, `app`, `database`…; kiểm thử thực tế bằng `ApacheAccessTest` |
| Phân quyền | Xem `permissions.md` |

## Cấu hình & URL
`.env` nạp bởi `Core\Env` (biến môi trường thật ưu tiên). Base URL được suy ra từ `SCRIPT_NAME` (chỉ khi là `index.php`) nên CSS/JS/liên kết/API chạy đúng trong `/releasehub/public/` mà không hardcode đường dẫn máy. `APP_URL` dùng cho liên kết tuyệt đối (nếu cần).

## Phần tự phát triển và thư viện
Toàn bộ backend PHP và logic JS là **tự viết**, không dùng framework. Thư viện frontend/dev xem `THIRD_PARTY_NOTICES.md`. `composer.json` chỉ khai báo PHPUnit ở mục dev.

## Đối chiếu với 5 bài lab
Gói `Open-Source-Software-Development-main.zip` mà nhóm cung cấp **không chứa nội dung 5 bài lab**: nó là một ứng dụng quản lý điểm sinh viên bằng PHP thuần (mysqli, mật khẩu lưu dạng văn bản, kết nối root cố định trong mã) và README chỉ trỏ tới các nhánh `lab1…lab5` trên GitHub. Vì vậy **chưa có bảng liên hệ lab ↔ phần áp dụng** — không bịa. Bảng bên dưới để nhóm điền sau khi đọc tài liệu từng lab:

| Lab | Nội dung lab (điền) | Phần áp dụng trong ReleaseHub (điền) |
|---|---|---|
| 1 | `[chưa có tài liệu]` | `[điền]` |
| 2 | `[chưa có tài liệu]` | `[điền]` |
| 3 | `[chưa có tài liệu]` | `[điền]` |
| 4 | `[chưa có tài liệu]` | `[điền]` |
| 5 | `[chưa có tài liệu]` | `[điền]` |

Ứng dụng mẫu trong zip **không được tái sử dụng** (khác miền bài toán, không có MVC/PDO/CSRF); ReleaseHub xây mới và không xóa/ghi đè bất kỳ tệp nào của nhóm.
