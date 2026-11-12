# Workflow lỗi và công việc

Luồng chính của **Bug**: `Open → In Progress → Resolved → Verified → Closed`, thêm `Reopened` khi kiểm thử thất bại hoặc lỗi xuất hiện lại.
Backend (`IssueWorkflowService` + `WorkflowRules`) **từ chối** mọi chuyển trạng thái không có trong bảng, dù gọi API trực tiếp.

## Định nghĩa "hoàn thành"
| Loại | Hoàn thành khi |
|---|---|
| Bug | `Verified` hoặc `Closed` |
| Task / Improvement | `Resolved` (hiển thị là "Hoàn thành") hoặc `Closed` |

## Bản sửa và kiểm thử
- `issues.fix_revision` tăng thêm 1 mỗi lần **ghi nhận bản sửa** (chức năng *Ghi nhận bản sửa*, bắt buộc mã commit hoặc URL PR http/https).
- Mỗi kết quả kiểm thử lưu `fix_revision` và `fix_ref` của bản sửa được kiểm tra. Nếu bản sửa đã đổi từ lúc tester mở màn hình ⇒ **409 `fix_changed`**.
- **Passed hợp lệ** = kết quả kiểm thử *mới nhất* là Passed **và** `fix_revision` của nó bằng `fix_revision` hiện tại của lỗi.
- Chỉ sửa **metadata** (tiêu đề, mô tả, ưu tiên, người xử lý, hạn…) **không** đổi `fix_revision` nên không vô hiệu hóa kiểm thử (có test `testMetadataEditDoesNotInvalidatePassedTest`).
- **Resolved ≠ Passed**: lỗi chỉ "Đã sửa" chưa có Passed thì chưa hoàn thành.

## Bảng chuyển trạng thái – Bug
| Từ | Sang | Vai trò được phép | Cách thực hiện | Ghi chú |
|---|---|---|---|---|
| Mới mở | Đang xử lý | Developer, Project Manager, Release Manager | Chuyển trạng thái | Nhận xử lý lỗi |
| Mở lại | Đang xử lý | Developer, Project Manager, Release Manager | Chuyển trạng thái | Xử lý lại lỗi bị mở lại |
| Đang xử lý | Mới mở | Developer, Project Manager | Chuyển trạng thái | Trả lại hàng đợi |
| Đang xử lý | Đã sửa – chờ kiểm thử | Developer, Project Manager | Ghi nhận bản sửa | Chỉ qua "Ghi nhận bản sửa" (bắt buộc mã commit/PR) |
| Đã sửa – chờ kiểm thử | Đang xử lý | Developer, Project Manager | Chuyển trạng thái | Rút lại bản sửa để làm tiếp |
| Đã sửa – chờ kiểm thử | Đã xác nhận | Tester | Ghi kết quả kiểm thử | Chỉ qua kết quả kiểm thử Passed của bản sửa hiện tại |
| Đã sửa – chờ kiểm thử | Mở lại | Tester | Ghi kết quả kiểm thử | Chỉ qua kết quả kiểm thử Failed |
| Đã xác nhận | Đã đóng | Tester, Project Manager, Release Manager | Chuyển trạng thái | Đóng lỗi đã xác nhận |
| Đã xác nhận | Mở lại | Tester, Project Manager, Release Manager | Chuyển trạng thái | Lỗi xuất hiện lại |
| Đã đóng | Mở lại | Tester, Project Manager, Release Manager | Chuyển trạng thái | Lỗi tái phát |

## Bảng chuyển trạng thái – Task / Cải tiến
| Từ | Sang | Vai trò được phép | Cách thực hiện | Ghi chú |
|---|---|---|---|---|
| Mới mở | Đang xử lý | Developer, Project Manager, Release Manager | Chuyển trạng thái | Bắt đầu thực hiện |
| Mở lại | Đang xử lý | Developer, Project Manager, Release Manager | Chuyển trạng thái | Làm lại |
| Đang xử lý | Mới mở | Developer, Project Manager | Chuyển trạng thái | Trả lại hàng đợi |
| Đang xử lý | Hoàn thành | Developer, Project Manager, Release Manager | Chuyển trạng thái | Hoàn thành |
| Hoàn thành | Đang xử lý | Developer, Project Manager, Release Manager | Chuyển trạng thái | Làm lại phần chưa đạt |
| Hoàn thành | Đã đóng | Project Manager, Release Manager | Chuyển trạng thái | Nghiệm thu và đóng |
| Hoàn thành | Mở lại | Project Manager, Release Manager, Tester | Chuyển trạng thái | Mở lại |
| Đã đóng | Mở lại | Project Manager, Release Manager | Chuyển trạng thái | Mở lại |

Task/Cải tiến **không** có các trường tái hiện lỗi và không có kết quả kiểm thử (`not_a_bug` nếu cố ghi).
System Admin được phép thực hiện mọi chuyển trạng thái hợp lệ trong bảng nhưng vẫn phải đi qua luồng *fix*/*test* khi bảng yêu cầu.
Mở việc `in_progress` mà chưa có người xử lý ⇒ tự gán cho người thực hiện.
