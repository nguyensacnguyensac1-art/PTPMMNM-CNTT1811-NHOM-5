# Phân quyền

## Nguyên tắc
- Quyền được kiểm tra **ở backend** (`AuthorizationService`) cho **mọi** thao tác đọc/ghi: xem, bình luận, kết quả kiểm thử, phụ thuộc, phiên bản, export. Ẩn nút trên giao diện chỉ để tiện dùng, không phải cơ chế bảo vệ.
- **Mỗi người có đúng MỘT vai trò trong mỗi dự án** (ràng buộc `UNIQUE(project_id, user_id)`), nhưng có thể có vai trò **khác nhau ở các dự án khác nhau** (ví dụ `multi@releasehub.local`: PM ở VOCAB, Viewer ở PAY, Tester ở IOT, Release Manager ở HEALTH). Không có "gộp quyền" nhiều vai trò trong cùng một dự án; muốn nhiều quyền hơn thì đổi vai trò.
- Không phải thành viên của dự án ⇒ **HTTP 403** với mọi thao tác, kể cả khi đổi ID trên URL/API.
- **System Admin** (`users.is_admin = 1`) có mọi quyền ở mọi dự án và thấy cả 16 dự án. Kiểm thử cách ly **không** dùng tài khoản admin (xem `tests/Integration/AuthorizationTest.php`); admin có test riêng `testSystemAdminSeesEverythingButIsolationCaseUsesNonAdmin`.
- Tạo dự án mới: System Admin hoặc người đang là Project Manager của ít nhất một dự án. Người tạo tự thành PM của dự án mới.
- Dự án phải còn ít nhất một Project Manager (không gỡ/hạ vai trò PM cuối cùng).

## Ma trận quyền (sinh tự động từ `app/Domain/Permissions.php`)
| Quyền | Project Manager | Release Manager | Developer | Tester | Viewer | System Admin |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| Xem dự án, công việc, phiên bản (`project.view`) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Sửa / lưu trữ dự án (`project.manage`) | ✔ |  |  |  |  | ✔ |
| Quản lý thành viên và vai trò (`member.manage`) | ✔ |  |  |  |  | ✔ |
| Tạo lỗi / công việc (`issue.create`) | ✔ | ✔ | ✔ | ✔ |  | ✔ |
| Sửa nội dung công việc (`issue.edit`) | ✔ | ✔ | ✔ |  |  | ✔ |
| Phân công người xử lý (`issue.assign`) | ✔ | ✔ |  |  |  | ✔ |
| Chuyển trạng thái (theo bảng workflow) (`issue.transition`) | ✔ | ✔ | ✔ | ✔ |  | ✔ |
| Ghi nhận bản sửa (`issue.fix`) | ✔ |  | ✔ |  |  | ✔ |
| Bình luận (`comment.create`) | ✔ | ✔ | ✔ | ✔ |  | ✔ |
| Ghi kết quả kiểm thử (`test.record`) |  |  |  | ✔ |  | ✔ |
| Thêm / gỡ phụ thuộc (`dependency.manage`) | ✔ | ✔ | ✔ |  |  | ✔ |
| Xem & xuất ghi chú phát hành (`release.view`) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Lập kế hoạch phiên bản, gắn / gỡ công việc (`release.plan`) | ✔ | ✔ |  |  |  | ✔ |
| Chốt phát hành (`release.finalize`) |  | ✔ |  |  |  | ✔ |

Ghi chú: "Chuyển trạng thái" còn bị giới hạn thêm theo bảng workflow (`docs/workflow.md`): có quyền `issue.transition` chưa chắc được phép *mọi* chuyển trạng thái.
Chốt phát hành (`release.finalize`) chỉ dành cho Release Manager và System Admin; Project Manager lập kế hoạch nhưng không chốt.
