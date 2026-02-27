<?php
$pageTitle   = 'Dashboard — Search Console';
$currentPage = 'dashboard';
ob_start();
?>

<!-- Barre de filtres -->
<form class="filters-bar" method="GET" action="/">
    <div>
        <label for="site_id">Site</label>
        <select name="site_id" id="site_id">
            <?php foreach ($sites as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $siteId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['label'] ?: $s['site_url']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="from">Du</label>
        <input type="date" name="from" id="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div>
        <label for="to">Au</label>
        <input type="date" name="to" id="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div>
        <label for="device">Appareil</label>
        <select name="device" id="device">
            <option value="">Tous</option>
            <option value="DESKTOP" <?= ($filters['device'] ?? '') === 'DESKTOP' ? 'selected' : '' ?>>Desktop</option>
            <option value="MOBILE" <?= ($filters['device'] ?? '') === 'MOBILE' ? 'selected' : '' ?>>Mobile</option>
            <option value="TABLET" <?= ($filters['device'] ?? '') === 'TABLET' ? 'selected' : '' ?>>Tablet</option>
        </select>
    </div>
    <div>
        <label for="country">Pays</label>
        <input type="text" name="country" id="country" placeholder="ex: FRA" value="<?= htmlspecialchars($filters['country'] ?? '') ?>" style="width:80px">
    </div>
    <div>
        <label for="filter_query">Requete</label>
        <input type="text" name="filter_query" id="filter_query" placeholder="Filtrer..." value="<?= htmlspecialchars($filters['query'] ?? '') ?>">
    </div>
    <div>
        <label for="filter_page">Page</label>
        <input type="text" name="filter_page" id="filter_page" placeholder="/chemin..." value="<?= htmlspecialchars($filters['page'] ?? '') ?>">
    </div>
    <button type="submit">Filtrer</button>
</form>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="label">Clicks</div>
        <div class="value" id="kpi-clicks">...</div>
        <div class="diff" id="kpi-clicks-diff"></div>
    </div>
    <div class="kpi-card">
        <div class="label">Impressions</div>
        <div class="value" id="kpi-impressions">...</div>
        <div class="diff" id="kpi-impressions-diff"></div>
    </div>
    <div class="kpi-card">
        <div class="label">CTR moyen</div>
        <div class="value" id="kpi-ctr">...</div>
        <div class="diff" id="kpi-ctr-diff"></div>
    </div>
    <div class="kpi-card">
        <div class="label">Position moyenne</div>
        <div class="value" id="kpi-position">...</div>
        <div class="diff" id="kpi-position-diff"></div>
    </div>
</div>

<!-- Graphique tendance -->
<div class="chart-container">
    <h3>Tendance quotidienne</h3>
    <canvas id="trendChart" height="100"></canvas>
</div>

<!-- Graphiques appareils + pays -->
<div class="grid-2">
    <div class="chart-container">
        <h3>Repartition par appareil</h3>
        <canvas id="deviceChart" height="200"></canvas>
    </div>
    <div class="chart-container">
        <h3>Top pays</h3>
        <canvas id="countryChart" height="200"></canvas>
    </div>
</div>

<!-- Tableaux Top Requetes + Top Pages -->
<div class="grid-2">
    <div class="data-table-wrap">
        <h3>Top requetes</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Requete</th>
                    <th>Clicks</th>
                    <th>Impressions</th>
                    <th>CTR</th>
                    <th>Position</th>
                </tr>
            </thead>
            <tbody id="queries-body">
                <tr><td colspan="5" style="text-align:center;color:#999">Chargement...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="data-table-wrap">
        <h3>Top pages</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Clicks</th>
                    <th>Impressions</th>
                    <th>CTR</th>
                    <th>Position</th>
                </tr>
            </thead>
            <tbody id="pages-body">
                <tr><td colspan="5" style="text-align:center;color:#999">Chargement...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Parametres pour le JS -->
<script>
window.dashboardParams = {
    siteId: <?= (int)$siteId ?>,
    from: '<?= htmlspecialchars($from) ?>',
    to: '<?= htmlspecialchars($to) ?>',
    searchType: '<?= htmlspecialchars($filters['search_type'] ?? 'web') ?>',
    device: '<?= htmlspecialchars($filters['device'] ?? '') ?>',
    country: '<?= htmlspecialchars($filters['country'] ?? '') ?>',
    filterQuery: '<?= htmlspecialchars($filters['query'] ?? '') ?>',
    filterPage: '<?= htmlspecialchars($filters['page'] ?? '') ?>'
};
window.dashboardData = { dailyTrend: [], devices: [], countries: [] };
</script>
<script src="/assets/js/dashboard.js"></script>
<script>
(function() {
    var p = window.dashboardParams;
    if (!p.siteId) return;

    var qs = 'site_id=' + p.siteId + '&from=' + p.from + '&to=' + p.to
        + '&search_type=' + encodeURIComponent(p.searchType)
        + (p.device ? '&device=' + encodeURIComponent(p.device) : '')
        + (p.country ? '&country=' + encodeURIComponent(p.country) : '')
        + (p.filterQuery ? '&filter_query=' + encodeURIComponent(p.filterQuery) : '')
        + (p.filterPage ? '&filter_page=' + encodeURIComponent(p.filterPage) : '');

    function fmt(n) { return Number(n || 0).toLocaleString('fr-FR'); }
    function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Charger KPIs + comparaison
    fetch('/api/compare?' + qs.replace('from=', 'from1=').replace('to=', 'to1=')
        + '&from2=' + encodeURIComponent(prevFrom(p.from, p.to))
        + '&to2=' + encodeURIComponent(prevTo(p.from)))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var c = data.current || {};
            var d = data.diff || {};
            document.getElementById('kpi-clicks').textContent = fmt(c.clicks);
            document.getElementById('kpi-impressions').textContent = fmt(c.impressions);
            document.getElementById('kpi-ctr').textContent = ((c.ctr || 0) * 100).toFixed(2).replace('.', ',') + '%';
            document.getElementById('kpi-position').textContent = (c.position || 0).toFixed(1).replace('.', ',');
            setDiff('kpi-clicks-diff', d.clicks, false);
            setDiff('kpi-impressions-diff', d.impressions, false);
            setDiff('kpi-ctr-diff', d.ctr * 100, false, ' pts', 2);
            setDiff('kpi-position-diff', d.position, true, '', 1);
        });

    // Charger tendance quotidienne
    fetch('/api/daily-trend?' + qs)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            window.dashboardData.dailyTrend = data;
            if (typeof window.renderTrendChart === 'function') window.renderTrendChart(data);
        });

    // Charger appareils
    fetch('/api/devices?' + qs)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            window.dashboardData.devices = data;
            if (typeof window.renderDeviceChart === 'function') window.renderDeviceChart(data);
        });

    // Charger pays
    fetch('/api/countries?' + qs)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            window.dashboardData.countries = data;
            if (typeof window.renderCountryChart === 'function') window.renderCountryChart(data);
        });

    // Charger top requetes
    fetch('/api/top-queries?' + qs + '&limit=20')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('queries-body');
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999">Aucune donnee</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(function(q) {
                return '<tr>'
                    + '<td class="truncate">' + escapeHtml(q.query) + '</td>'
                    + '<td class="num">' + fmt(q.clicks) + '</td>'
                    + '<td class="num">' + fmt(q.impressions) + '</td>'
                    + '<td class="num">' + (q.ctr * 100).toFixed(2) + '%</td>'
                    + '<td class="num">' + Number(q.position).toFixed(1) + '</td>'
                    + '</tr>';
            }).join('');
        });

    // Charger top pages
    fetch('/api/top-pages?' + qs + '&limit=20')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('pages-body');
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999">Aucune donnee</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(function(pg) {
                return '<tr>'
                    + '<td class="truncate">' + escapeHtml(pg.page) + '</td>'
                    + '<td class="num">' + fmt(pg.clicks) + '</td>'
                    + '<td class="num">' + fmt(pg.impressions) + '</td>'
                    + '<td class="num">' + (pg.ctr * 100).toFixed(2) + '%</td>'
                    + '<td class="num">' + Number(pg.position).toFixed(1) + '</td>'
                    + '</tr>';
            }).join('');
        });

    function setDiff(id, val, invertColor, suffix, decimals) {
        var el = document.getElementById(id);
        if (!el || val === undefined || val === null) return;
        decimals = decimals !== undefined ? decimals : 0;
        suffix = suffix || '';
        var n = Number(val);
        var sign = n >= 0 ? '+' : '';
        el.textContent = sign + n.toFixed(decimals).replace('.', ',') + suffix + ' vs periode prec.';
        var positive = invertColor ? n <= 0 : n >= 0;
        el.className = 'diff ' + (positive ? 'positive' : 'negative');
    }

    function prevFrom(from, to) {
        var days = Math.round((new Date(to) - new Date(from)) / 86400000) + 1;
        var d = new Date(from);
        d.setDate(d.getDate() - days);
        return d.toISOString().slice(0, 10);
    }

    function prevTo(from) {
        var d = new Date(from);
        d.setDate(d.getDate() - 1);
        return d.toISOString().slice(0, 10);
    }
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
