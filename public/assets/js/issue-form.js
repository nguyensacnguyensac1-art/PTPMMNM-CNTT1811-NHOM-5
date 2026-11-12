(function () {
    var form = document.getElementById('issue-form');
    if (!form) { return; }
    function sync() {
        var bug = form.querySelector('[name=type]:checked').value === 'bug';
        form.querySelectorAll('.bug-only').forEach(function (el) { el.classList.toggle('d-none', !bug); el.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !bug; }); });
    }
    form.querySelectorAll('[name=type]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
