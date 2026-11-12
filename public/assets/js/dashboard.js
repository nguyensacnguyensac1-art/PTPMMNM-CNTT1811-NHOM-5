(function () {
    var el = document.getElementById('dash-data');
    if (!el || typeof Chart === 'undefined') { return; }
    var d = JSON.parse(el.textContent);
    var colors = { open: '#94a3b8', reopened: '#ef4444', in_progress: '#3b82f6', resolved: '#f59e0b', verified: '#22c55e', closed: '#6b7280' };
    var keys = d.chart_status.keys || [];
    new Chart(document.getElementById('chStatus'), {
        type: 'doughnut',
        data: { labels: d.chart_status.labels, datasets: [{ data: d.chart_status.values, backgroundColor: keys.map(function (k) { return colors[k] || '#94a3b8'; }) }] },
        options: { plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
    });
    var sevColors = { blocker: '#7f1d1d', critical: '#ef4444', major: '#f97316', minor: '#eab308', trivial: '#94a3b8' };
    new Chart(document.getElementById('chSeverity'), {
        type: 'bar',
        data: { labels: d.chart_severity.labels, datasets: [{ label: 'Lỗi đang mở', data: d.chart_severity.values, backgroundColor: d.chart_severity.keys.map(function (k) { return sevColors[k] || '#94a3b8'; }) }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
    new Chart(document.getElementById('chProjects'), {
        type: 'bar',
        data: { labels: d.chart_projects.labels, datasets: [
            { label: 'Lỗi đang mở', data: d.chart_projects.open, backgroundColor: '#ef4444' },
            { label: 'Đã hoàn thành', data: d.chart_projects.done, backgroundColor: '#22c55e' }] },
        options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
})();
