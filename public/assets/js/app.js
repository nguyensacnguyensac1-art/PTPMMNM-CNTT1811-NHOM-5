/* ReleaseHub – tiện ích dùng chung: Fetch API + CSRF, toast, xác nhận, form AJAX */
(function () {
    'use strict';
    var meta = function (n) { var m = document.querySelector('meta[name="' + n + '"]'); return m ? m.content : ''; };
    var base = meta('base-url');
    var RH = window.RH = {};

    RH.url = function (p) { return base + '/' + String(p).replace(/^\//, ''); };
    RH.esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    };

    /** Gọi API JSON. Luôn trả về {ok,status,data}; không ném lỗi mạng. */
    RH.api = async function (method, path, body) {
        var opt = { method: method, headers: { 'Accept': 'application/json', 'X-CSRF-Token': meta('csrf-token'), 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' };
        if (body !== undefined) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
        var res;
        try { res = await fetch(RH.url(path), opt); }
        catch (e) { return { ok: false, status: 0, data: { message: 'Không kết nối được máy chủ. Hãy kiểm tra mạng rồi thử lại.' } }; }
        var data = {};
        try { data = await res.json(); } catch (e) { data = { message: 'Phản hồi không hợp lệ từ máy chủ.' }; }
        return { ok: res.ok && data.ok !== false, status: res.status, data: data };
    };

    RH.toast = function (msg, type) {
        var wrap = document.getElementById('toasts');
        if (!wrap) { return; }
        var icons = { success: 'bi-check-circle', danger: 'bi-x-octagon', warning: 'bi-exclamation-triangle', info: 'bi-info-circle' };
        var el = document.createElement('div');
        el.className = 'rh-toast ' + (type || 'info');
        el.innerHTML = '<i class="bi ' + (icons[type] || icons.info) + '"></i><div></div>';
        el.lastChild.textContent = msg;
        wrap.appendChild(el);
        setTimeout(function () { el.remove(); }, type === 'danger' ? 8000 : 4500);
    };

    RH.confirm = function (message, opt) {
        opt = opt || {};
        return new Promise(function (resolve) {
            var id = 'cf' + Date.now();
            var html = '<div class="modal fade" id="' + id + '" tabindex="-1" aria-modal="true" role="dialog"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
                '<div class="modal-header"><h5 class="modal-title">' + RH.esc(opt.title || 'Xác nhận') + '</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>' +
                '<div class="modal-body"></div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Hủy</button>' +
                '<button class="btn ' + (opt.danger ? 'btn-danger' : 'btn-primary') + '" id="' + id + 'ok">' + RH.esc(opt.okText || 'Đồng ý') + '</button></div></div></div></div>';
            document.body.insertAdjacentHTML('beforeend', html);
            var el = document.getElementById(id);
            el.querySelector('.modal-body').textContent = message;
            var modal = new bootstrap.Modal(el);
            var done = false;
            el.querySelector('#' + id + 'ok').addEventListener('click', function () { done = true; modal.hide(); });
            el.addEventListener('hidden.bs.modal', function () { el.remove(); resolve(done); });
            modal.show();
        });
    };

    RH.busy = function (btn, on) {
        if (!btn) { return; }
        if (on) { btn.dataset.html = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spin"></span> Đang xử lý…'; }
        else { btn.disabled = false; if (btn.dataset.html) { btn.innerHTML = btn.dataset.html; } }
    };

    RH.clearErrors = function (form) {
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        form.querySelectorAll('.js-err').forEach(function (e) { e.remove(); });
    };
    RH.showErrors = function (form, errors) {
        RH.clearErrors(form);
        var first = null;
        Object.keys(errors || {}).forEach(function (k) {
            var f = form.querySelector('[name="' + k + '"]');
            if (!f) { return; }
            f.classList.add('is-invalid');
            var d = document.createElement('div');
            d.className = 'invalid-feedback js-err';
            d.textContent = errors[k];
            f.insertAdjacentElement('afterend', d);
            first = first || f;
        });
        if (first) { first.focus(); }
    };

    /** Xử lý lỗi chung; trả về true nếu đã xử lý. */
    RH.fail = function (res, form) {
        var d = res.data || {};
        if (res.status === 401) { location.href = RH.url('login'); return true; }
        if (res.status === 422 && d.errors && form) { RH.showErrors(form, d.errors); RH.toast(d.message || 'Dữ liệu chưa hợp lệ.', 'warning'); return true; }
        RH.toast(d.message || 'Có lỗi xảy ra.', res.status === 409 ? 'warning' : 'danger');
        return true;
    };

    RH.formData = function (form) {
        var o = {};
        new FormData(form).forEach(function (v, k) { if (k !== '_csrf') { o[k] = v; } });
        form.querySelectorAll('input[type=checkbox]').forEach(function (c) { if (c.name) { o[c.name] = c.checked; } });
        return o;
    };

    // <form data-api="api/..."> gửi JSON bằng Fetch; thành công thì chuyển trang / tải lại
    document.addEventListener('submit', async function (ev) {
        var form = ev.target;
        if (!form.dataset || !form.dataset.api) { return; }
        ev.preventDefault();
        var btn = form.querySelector('[type=submit]');
        RH.busy(btn, true);
        var res = await RH.api(form.dataset.method || 'POST', form.dataset.api, RH.formData(form));
        RH.busy(btn, false);
        if (!res.ok) { RH.fail(res, form); return; }
        RH.toast(form.dataset.success || 'Đã lưu thành công.', 'success');
        if (res.data.redirect) { location.href = res.data.redirect; }
        else if (form.dataset.reload !== undefined) { location.reload(); }
    });

    // <button data-post="api/..." data-confirm="..." data-body='{"a":1}'>
    document.addEventListener('click', async function (ev) {
        var b = ev.target.closest('[data-post]');
        if (!b) { return; }
        ev.preventDefault();
        if (b.dataset.confirm && !(await RH.confirm(b.dataset.confirm, { danger: b.dataset.danger !== undefined, okText: b.dataset.ok }))) { return; }
        RH.busy(b, true);
        var res = await RH.api('POST', b.dataset.post, b.dataset.body ? JSON.parse(b.dataset.body) : {});
        RH.busy(b, false);
        if (!res.ok) { RH.fail(res); return; }
        RH.toast(b.dataset.success || 'Đã thực hiện.', 'success');
        if (res.data.redirect) { location.href = res.data.redirect; } else { location.reload(); }
    });

    // Sidebar di động
    var menu = document.getElementById('menuBtn'), side = document.getElementById('sidebar'), scrim = document.getElementById('scrim');
    if (menu && side) {
        var toggle = function (open) { side.classList.toggle('open', open); scrim.classList.toggle('show', open); };
        menu.addEventListener('click', function () { toggle(!side.classList.contains('open')); });
        scrim.addEventListener('click', function () { toggle(false); });
    }
})();
