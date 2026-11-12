# Chính sách phát hành

*Lưu ý thuật ngữ:* "phiên bản" ở đây là phiên bản của **sản phẩm được quản lý bên trong ReleaseHub** (VD `VOCAB v1.1.0`), không phải GitHub Release dùng để nộp bài.

## Điều kiện để một phiên bản "sẵn sàng" (`ReleaseReadinessService`)
Tất cả điều kiện sau phải đạt; mỗi điều kiện trả: đạt/chưa đạt, công việc liên quan, giải thích và liên kết xử lý.
1. **release_open** – phiên bản chưa được chốt.
2. **has_items** – có ít nhất một công việc.
3. **same_project** – mọi công việc thuộc đúng dự án (ngoài việc kiểm tra ở service còn có khóa ngoại phức hợp ở CSDL).
4. **required_complete** – mọi công việc **bắt buộc** (`release_items.is_required = 1`) đã hoàn thành (định nghĩa trong `workflow.md`). Công việc không bắt buộc có thể chưa xong; nó vẫn vào snapshot và được ghi chú "không bắt buộc, chưa hoàn thành khi chốt".
5. **dependencies_done** – mọi phụ thuộc của công việc bắt buộc đã hoàn thành, **kể cả phụ thuộc nằm ngoài phiên bản**.
6. **blockers_retested** – mỗi lỗi mức **Blocker** trong phiên bản có kết quả **Passed mới nhất trên bản sửa hiện tại** và trạng thái Verified/Closed. Lý do thất bại được nêu cụ thể: chưa có kiểm thử (Resolved ≠ Passed), Failed, Passed của bản sửa cũ, chưa xác nhận.

*Quyết định chính sách:* điều kiện 6 áp dụng cho Blocker **nằm trong phiên bản** (kể cả không bắt buộc); Blocker không gắn vào phiên bản nào không chặn phiên bản đó. Một công việc chỉ nằm trong **một** phiên bản dự kiến tại một thời điểm.

## Chốt phát hành (`ReleaseSnapshotService::finalize`)
Chỉ Release Manager / System Admin. Trong **một transaction**: khóa hàng dự án (`SELECT … FOR UPDATE`) → khóa hàng phiên bản → đánh giá lại điều kiện → sao chép dữ liệu hiện tại vào `release_snapshot_items` → đặt `status = released`, `released_at`, `released_by`, `snapshot_checksum`, `readiness_report`.
- Mọi thao tác ghi làm đổi điều kiện (đổi trạng thái, ghi bản sửa/kiểm thử, thêm/gỡ phụ thuộc, gắn/gỡ công việc…) cũng khóa cùng hàng dự án nên **không thể xen vào giữa kiểm tra và chốt**.
- Chốt lặp / bấm hai lần / nhiều request đồng thời: đúng một request thành công, còn lại nhận **409 `already_released`**; không tạo snapshot trùng.
- Điều kiện không đạt ⇒ **409 `not_ready`** kèm báo cáo; không có dòng snapshot nào được ghi (rollback).

## Snapshot bất biến
Mỗi dòng lưu bản sao đầy đủ: mã, tiêu đề, loại, nhóm, tóm tắt thay đổi, priority, severity, trạng thái, bản sửa, người xử lý, mã phụ thuộc, **bằng chứng kiểm thử** (kết quả, người kiểm tra, thời điểm, ghi chú, bản sửa) và người/thời điểm chốt. Màn hình lịch sử và export **chỉ đọc snapshot**, không đọc dữ liệu hiện tại.
Bảo vệ 3 lớp: (1) service không có luồng sửa/xóa; (2) **trigger MySQL** chặn UPDATE/DELETE snapshot và mọi thay đổi `releases`/`release_items` của phiên bản đã chốt; (3) `snapshot_checksum` (SHA-256) cho phép phát hiện sửa trực tiếp ngoài luồng (màn hình hiển thị khớp/không khớp).

## Xuất release notes
`/releases/{id}/notes.md` (Markdown) và `/releases/{id}/notes.json`; có tiêu đề dự án, phiên bản, thời điểm chốt (giờ Việt Nam), người chốt, thay đổi chia nhóm *Sửa lỗi / Cải tiến / Công việc*. Chỉ thành viên dự án được tải; phiên bản chưa chốt ⇒ 409 `not_released`.
