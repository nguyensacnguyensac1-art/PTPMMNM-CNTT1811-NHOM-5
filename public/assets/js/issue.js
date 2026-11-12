/* Chi tiết lỗi: sửa với khóa lạc quan (409 -> màn hình đối chiếu), thêm phụ thuộc. */
(function () {
    var root = document.getElementById('issue-root');
    if (!root) { return; }
    var id = root.dataset.id;
    var labels = { title: 'Tiêu đề', description: 'Mô tả', priority: 'Mức ưu tiên', severity: 'Mức nghiêm trọng', due_date: 'Thời hạn', environment: 'Môi trường',
        steps_to_reproduce: 'Các bước tái hiện', expected_result: 'Kết quả mong đợi', actual_result: 'Kết quả thực tế', commit_url: 'Liên kết commit', pr_url: 'Liên kết PR', assignee_id: 'Người xử lý' };

    var form = document.getElementById('edit-form');
    var lastConflict = null;
    if (form) {
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            RH.clearErrors(form);
            var btn = form.querySelector('[type=submit]'); RH.busy(btn, true);
            var res = await RH.api('POST', 'api/issues/' + id, RH.formData(form));
            RH.busy(btn, false);
            if (res.ok) { RH.toast(res.data.issue.unchanged ? 'Không có thay đổi nào để lưu.' : 'Đã lưu thay đổi.', 'success'); location.reload(); return; }
            if (res.status === 409 && res.data.error === 'version_conflict') { showConflict(res.data); return; }
            RH.fail(res, form);
        });
        document.getElementById('conflict-keep').addEventListener('click', function () {
            bootstrap.Modal.getInstance(document.getElementById('conflictModal')).hide();
            if (!lastConflict) { return; }
            form.querySelector('[name=expected_version]').value = lastConflict.current.version; // chỉ cập nhật số phiên bản, KHÔNG tự lưu
            lastConflict.differing_fields.forEach(function (f) { var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('border-warning'); } });
            var a = document.getElementById('conflict-alert');
            a.classList.remove('d-none');
            a.textContent = 'Bản mới nhất (phiên bản ' + lastConflict.current.version + ') đã được tải. Các trường viền vàng khác với người lưu trước — hãy kiểm tra rồi nhấn "Lưu thay đổi" nếu bạn muốn ghi đè.';
        });
        document.getElementById('conflict-reload').addEventListener('click', function () { location.reload(); });
        document.getElementById('conflict-copy').addEventListener('click', function () {
            var txt = Object.keys(lastConflict.draft).map(function (k) { return (labels[k] || k) + ': ' + (lastConflict.draft[k] == null ? '' : lastConflict.draft[k]); }).join('\n');
            (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () { RH.toast('Đã sao chép nội dung của bạn.', 'success'); }, function () { RH.toast('Không sao chép được; hãy chọn và sao chép thủ công từ biểu mẫu.', 'warning'); });
        });
    }
    function showConflict(d) {
        lastConflict = d;
        document.getElementById('conflict-msg').textContent = d.message;
        var rows = d.differing_fields.map(function (f) {
            return '<tr><th scope="row">' + RH.esc(labels[f] || f) + '</th><td class="diff-box mine pre-wrap">' + RH.esc(d.draft[f] == null || d.draft[f] === '' ? '(trống)' : d.draft[f]) +
                '</td><td class="diff-box theirs pre-wrap">' + RH.esc(d.current[f] == null || d.current[f] === '' ? '(trống)' : d.current[f]) + '</td></tr>';
        }).join('');
        document.getElementById('conflict-diff').innerHTML = rows
            ? '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Trường</th><th>Của bạn (chưa lưu)</th><th>Bản mới nhất đã lưu</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
            : '<div class="alert alert-info">Nội dung bạn nhập trùng với bản mới nhất, nhưng phiên bản dữ liệu đã đổi do thao tác khác (ví dụ chuyển trạng thái).</div>';
        new bootstrap.Modal(document.getElementById('conflictModal')).show();
    }

    var dep = document.getElementById('dep-add');
    if (dep) {
        dep.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            var box = document.getElementById('dep-err'); box.classList.add('d-none');
            var v = dep.querySelector('[name=depends_on_id]').value;
            if (!v) { RH.toast('Hãy chọn công việc.', 'warning'); return; }
            var res = await RH.api('POST', 'api/issues/' + id + '/dependencies', { depends_on_id: v });
            if (res.ok) { location.reload(); return; }
            if (res.data && res.data.error === 'cycle_detected') { box.textContent = 'Tạo vòng phụ thuộc: ' + res.data.cycle_text; box.classList.remove('d-none'); }
            RH.fail(res);
        });
    }
})();
