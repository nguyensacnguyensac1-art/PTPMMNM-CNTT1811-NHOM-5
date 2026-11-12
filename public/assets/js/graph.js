/* Đồ thị phụ thuộc: bố cục theo tầng (trái -> phải), mũi tên đi từ việc PHẢI XONG TRƯỚC tới việc phụ thuộc. */
(function () {
    var dataEl = document.getElementById('graph-data');
    var host = document.getElementById('graph');
    if (!dataEl || !host) { return; }
    var g = JSON.parse(dataEl.textContent);
    var NS = 'http://www.w3.org/2000/svg', W = 168, H = 50, GX = 90, GY = 18;

    function draw(hlId) {
        host.innerHTML = '';
        if (!g.nodes.length) { host.innerHTML = '<div class="empty"><i class="bi bi-diagram-3"></i>Chưa có quan hệ phụ thuộc nào.</div>'; return; }
        var byId = {}, deps = {}, dependents = {};
        g.nodes.forEach(function (n) { byId[n.id] = n; deps[n.id] = []; dependents[n.id] = []; });
        g.edges.forEach(function (e) { deps[e.issue_id].push(e.depends_on_id); dependents[e.depends_on_id].push(e.issue_id); });
        var layer = {};
        (function calc() {
            var state = {};
            function visit(id) {
                if (layer[id] !== undefined) { return layer[id]; }
                if (state[id]) { return 0; }
                state[id] = 1;
                var m = 0;
                deps[id].forEach(function (d) { m = Math.max(m, visit(d) + 1); });
                layer[id] = m; return m;
            }
            g.nodes.forEach(function (n) { visit(n.id); });
        })();
        var cols = [];
        g.nodes.forEach(function (n) { (cols[layer[n.id]] = cols[layer[n.id]] || []).push(n); });
        var pos = {}, maxRows = 0;
        cols.forEach(function (c, ci) { c.forEach(function (n, ri) { pos[n.id] = { x: 20 + ci * (W + GX), y: 20 + ri * (H + GY) }; }); maxRows = Math.max(maxRows, c.length); });
        var width = 40 + cols.length * (W + GX) - GX, height = 40 + maxRows * (H + GY) - GY;
        var hl = {};
        if (hlId) { // làm nổi bật toàn bộ tiền đề và hậu duệ
            (function mark(id, dir) { hl[id] = true; (dir === 'up' ? deps[id] : dependents[id]).forEach(function (x) { if (!hl[x] || true) { if (!hl['_' + dir + x]) { hl['_' + dir + x] = 1; mark(x, dir); } } }); })(hlId, 'up');
            (function mark(id, dir) { hl[id] = true; (dir === 'up' ? deps[id] : dependents[id]).forEach(function (x) { if (!hl['_' + dir + x]) { hl['_' + dir + x] = 1; mark(x, dir); } }); })(hlId, 'down');
        }
        var svg = document.createElementNS(NS, 'svg');
        svg.setAttribute('width', width); svg.setAttribute('height', height); svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'Đồ thị phụ thuộc: mũi tên đi từ công việc phải hoàn thành trước tới công việc phụ thuộc');
        svg.innerHTML = '<defs><marker id="arr" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" fill="#64748b"/></marker>' +
            '<marker id="arrh" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" fill="#4f46e5"/></marker></defs>';
        g.edges.forEach(function (e) {
            var from = pos[e.depends_on_id], to = pos[e.issue_id];
            if (!from || !to) { return; }
            var x1 = from.x + W, y1 = from.y + H / 2, x2 = to.x, y2 = to.y + H / 2, mx = (x1 + x2) / 2;
            var p = document.createElementNS(NS, 'path');
            var on = hlId && hl[e.issue_id] && hl[e.depends_on_id];
            p.setAttribute('d', 'M' + x1 + ' ' + y1 + ' C' + mx + ' ' + y1 + ',' + mx + ' ' + y2 + ',' + x2 + ' ' + y2);
            p.setAttribute('class', 'g-edge' + (on ? ' hl' : ''));
            p.setAttribute('marker-end', on ? 'url(#arrh)' : 'url(#arr)');
            svg.appendChild(p);
        });
        g.nodes.forEach(function (n) {
            var p = pos[n.id], el = document.createElementNS(NS, 'g');
            el.setAttribute('class', 'g-node' + (Number(n.is_complete) ? ' done' : '') + (n.severity === 'blocker' && n.type === 'bug' ? ' blocker' : '') + (hlId && hl[n.id] ? ' hl' : ''));
            el.setAttribute('transform', 'translate(' + p.x + ',' + p.y + ')');
            el.setAttribute('tabindex', '0'); el.setAttribute('role', 'button');
            el.setAttribute('aria-label', n.code + ' ' + n.title + (Number(n.is_complete) ? ' (đã xong)' : ' (chưa xong)'));
            var title = n.title.length > 24 ? n.title.slice(0, 23) + '…' : n.title;
            el.innerHTML = '<rect width="' + W + '" height="' + H + '" rx="8"/><text class="code" x="10" y="19"></text><text x="10" y="37"></text>';
            el.querySelectorAll('text')[0].textContent = n.code + (Number(n.is_complete) ? ' ✓' : '');
            el.querySelectorAll('text')[1].textContent = title;
            var t = document.createElementNS(NS, 'title'); t.textContent = n.code + ' – ' + n.title; el.appendChild(t);
            el.addEventListener('click', function () { draw(hlId === n.id ? null : n.id); });
            el.addEventListener('dblclick', function () { location.href = RH.url('issues/' + n.id); });
            el.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { location.href = RH.url('issues/' + n.id); } if (ev.key === ' ') { ev.preventDefault(); draw(hlId === n.id ? null : n.id); } });
            svg.appendChild(el);
        });
        host.appendChild(svg);
    }
    draw(null);

    // Thêm quan hệ: hiện chuỗi gây vòng khi bị từ chối
    var form = document.getElementById('dep-form');
    if (form) {
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            var box = document.getElementById('cycle-box'); box.classList.add('d-none'); box.innerHTML = '';
            var a = form.querySelector('[name=issue_id]').value, b = form.querySelector('[name=depends_on_id]').value;
            if (!a || !b) { RH.toast('Hãy chọn đủ hai công việc.', 'warning'); return; }
            var btn = form.querySelector('[type=submit]'); RH.busy(btn, true);
            var res = await RH.api('POST', 'api/issues/' + a + '/dependencies', { depends_on_id: b });
            RH.busy(btn, false);
            if (res.ok) { RH.toast('Đã thêm quan hệ phụ thuộc.', 'success'); location.reload(); return; }
            if (res.data && res.data.error === 'cycle_detected') {
                box.classList.remove('d-none');
                box.innerHTML = '<div class="fw-semibold mb-1"><i class="bi bi-arrow-repeat"></i> Quan hệ bị từ chối vì tạo vòng phụ thuộc:</div><div class="cycle-chain"></div>';
                box.querySelector('.cycle-chain').textContent = res.data.cycle_text;
                RH.toast('Bị từ chối: ' + res.data.cycle_text, 'danger');
            } else { RH.fail(res, form); }
        });
    }
})();
