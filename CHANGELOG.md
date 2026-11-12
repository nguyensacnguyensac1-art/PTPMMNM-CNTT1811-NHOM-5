# Changelog
Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/). Đây là lịch sử của **chính sản phẩm ReleaseHub** (khác với "phiên bản" bên trong ReleaseHub).

## [Chưa phát hành]
### Đã thêm
- Quản lý dự án, thành viên theo vai trò từng dự án, lỗi/công việc/cải tiến, bình luận, Kanban.
- Workflow Open → In Progress → Resolved → Verified → Closed (+ Reopened); ghi nhận bản sửa (fix_revision) và kết quả kiểm thử gắn với bản sửa.
- Phụ thuộc với thuật toán DFS tự viết, từ chối tự phụ thuộc/trùng/chéo dự án/vòng và trả chuỗi vòng.
- Kế hoạch phát hành, điều kiện phát hành, chốt với snapshot bất biến (trigger + checksum), release notes Markdown/JSON.
- Khóa lạc quan (HTTP 409), khóa theo dự án cho thao tác đồng thời.
- Seeder xác định 16 dự án / 320 công việc / 244 phụ thuộc / 48 phiên bản và `database/releasehub.sql` tương đương.
- 77 test PHPUnit (gồm 4 test đồng thời đa tiến trình) và 32 kịch bản E2E Playwright.
