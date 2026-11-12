/* Kanban: đổi trạng thái qua backend. Thất bại => hoàn nguyên thẻ và báo lý do. Có menu thay thế cho kéo thả. */
(function () {
    var board = document.getElementById('board');
    if (!board) { return; }
    var labels = JSON.parse(board.dataset.labels || '{}');
    var dragging = null, srcCol = null, srcNext = null;

    function recount() {
        board.querySelectorAll('.kb-col').forEach(function (c) { c.querySelector('.count').textContent = c.querySelectorAll('.kb-card').length; });
    }
    function revert(card) {
        if (srcCol) { srcCol.insertBefore(card, srcNext); }
        recount();
    }
    async function move(card, to, from, fromNext) {
        card.classList.add('busy');
        var res = await RH.api('POST', 'api/issues/' + card.dataset.id + '/transition', { to: to, expected_version: card.dataset.version });
        card.classList.remove('busy');
        if (res.ok) {
            card.dataset.version = res.data.issue.version;
            RH.toast(card.querySelector('.code-link').textContent + ' → ' + labels[to], 'success');
            var menu = card.querySelector('.dropdown-menu');
            if (menu) { menu.querySelectorAll('[data-move]').forEach(function (b) { b.parentNode.style.display = b.dataset.move === to ? 'none' : ''; }); }
        } else {
            // hoàn nguyên và nói rõ lý do
            var colFrom = board.querySelector('.kb-col[data-status="' + from + '"]');
            colFrom.insertBefore(card, fromNext && fromNext.parentNode === colFrom ? fromNext : null);
            recount();
            RH.toast((res.data && res.data.message) || 'Không thể đổi trạng thái.', res.status === 409 ? 'warning' : 'danger');
            if (res.status === 409 && res.data && res.data.error === 'version_conflict') {
                RH.toast('Dữ liệu đã thay đổi; tải lại trang để thấy bản mới nhất.', 'warning');
            }
        }
        recount();
    }
    function doMove(card, to) {
        var from = card.parentNode.dataset.status;
        if (from === to) { return; }
        var next = card.nextElementSibling;
        var target = board.querySelector('.kb-col[data-status="' + to + '"]');
        target.appendChild(card);
        recount();
        move(card, to, from, next);
    }

    if (board.dataset.canMove === '1') {
        board.addEventListener('dragstart', function (e) {
            var c = e.target.closest('.kb-card');
            if (!c) { return; }
            dragging = c; srcCol = c.parentNode; srcNext = c.nextElementSibling;
            c.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', c.dataset.id);
        });
        board.addEventListener('dragend', function () { if (dragging) { dragging.classList.remove('dragging'); } board.querySelectorAll('.over').forEach(function (x) { x.classList.remove('over'); }); dragging = null; });
        board.querySelectorAll('.kb-col').forEach(function (col) {
            col.addEventListener('dragover', function (e) { if (dragging) { e.preventDefault(); col.classList.add('over'); } });
            col.addEventListener('dragleave', function () { col.classList.remove('over'); });
            col.addEventListener('drop', function (e) {
                e.preventDefault(); col.classList.remove('over');
                if (!dragging) { return; }
                var card = dragging, from = srcCol.dataset.status, next = srcNext;
                if (from === col.dataset.status) { return; }
                col.appendChild(card);
                recount();
                move(card, col.dataset.status, from, next);
            });
        });
    }
    board.addEventListener('click', function (e) {
        var b = e.target.closest('[data-move]');
        if (b) { doMove(b.closest('.kb-card'), b.dataset.move); }
    });
})();
