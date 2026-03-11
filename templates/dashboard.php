<?php
$pageTitle   = 'Search Console History';
$currentPage = 'dashboard';
$prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
$authenticated = $this->authenticated ?? false;
ob_start();
?>

<!-- ── Section connexion GSC ── -->
<div class="chart-container" style="margin-bottom:1.5rem">
    <h3>Google Search Console</h3>
    <div style="padding:0 .25rem">
        <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap">
            <?php if ($authenticated): ?>
                <span class="gsc-status-badge gsc-status-connecte">
                    <i>&#9679;</i> Connecté
                </span>
                <a href="<?= $prefix ?>/auth/logout" class="btn btn-outline" style="padding:.3rem .8rem; font-size:.85rem">
                    <i class="bi bi-box-arrow-left"></i> Déconnecter
                </a>
            <?php elseif (!empty($oauthConfigure)): ?>
                <span class="gsc-status-badge gsc-status-deconnecte">
                    <i>&#9679;</i> Non connecté
                </span>
                <a href="<?= htmlspecialchars($authUrl) ?>" class="btn" style="padding:.3rem .8rem; font-size:.85rem">
                    <i class="bi bi-google"></i> Connecter Google Search Console
                </a>
            <?php else: ?>
                <span class="gsc-status-badge gsc-status-deconnecte">
                    <i>&#9679;</i> Non connecté
                </span>
                <div class="gsc-alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    La connexion Google Search Console n'est pas configurée. Contactez l'administrateur.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($authenticated && !empty($sites)): ?>
        <p style="margin-top:.75rem; font-size:.85rem; color:var(--text-secondary)">
            <?= count($sites) ?> propriété(s) GSC synchronisée(s).
            Scope : <code style="background:var(--brand-teal-light);color:var(--brand-dark);padding:.1em .3em;border-radius:3px;font-size:.8em">webmasters.readonly</code> (lecture seule).
        </p>
        <?php elseif ($authenticated && empty($sites)): ?>
        <p style="margin-top:.75rem; font-size:.85rem; color:var(--text-secondary)">
            Aucun site importé. <a href="<?= $prefix ?>/sync-status">Lancez une synchronisation</a> pour importer vos propriétés.
        </p>
        <?php elseif (!$authenticated): ?>
        <p style="margin-top:.75rem; font-size:.85rem; color:var(--text-secondary)">
            Connectez-vous pour synchroniser et visualiser vos données Search Console
            (clics, impressions, positions, requêtes).
        </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($authenticated && !empty($sites)): ?>

<!-- Barre de filtres -->
<form class="filters-bar" method="GET" action="<?= $prefix ?>/">
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
        <label for="filter_query">Requête</label>
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
        <h3>Répartition par appareil</h3>
        <canvas id="deviceChart" height="200"></canvas>
    </div>
    <div class="chart-container">
        <h3>Top pays</h3>
        <canvas id="countryChart" height="200"></canvas>
    </div>
</div>

<!-- Tableaux Top Requêtes + Top Pages -->
<div class="grid-2">
    <div class="data-table-wrap">
        <h3>Top requêtes</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Requête</th>
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

<!-- Paramètres pour le JS -->
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
<script src="<?= $prefix ?>/assets/js/dashboard.js"></script>
<script>
(function() {
    var p = window.dashboardParams;
    if (!p.siteId) return;

    var baseUrl = window.MODULE_BASE_URL || '';
    var qs = 'site_id=' + p.siteId + '&from=' + p.from + '&to=' + p.to
        + '&search_type=' + encodeURIComponent(p.searchType)
        + (p.device ? '&device=' + encodeURIComponent(p.device) : '')
        + (p.country ? '&country=' + encodeURIComponent(p.country) : '')
        + (p.filterQuery ? '&filter_query=' + encodeURIComponent(p.filterQuery) : '')
        + (p.filterPage ? '&filter_page=' + encodeURIComponent(p.filterPage) : '');

    var qsCompare = qs.replace('from=', 'from1=').replace('to=', 'to1=')
        + '&from2=' + encodeURIComponent(prevFrom(p.from, p.to))
        + '&to2=' + encodeURIComponent(prevTo(p.from));

    function fmt(n) { return Number(n || 0).toLocaleString('fr-FR'); }
    function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Charger toutes les données en parallèle
    Promise.all([
        fetch(baseUrl + '/api/compare?' + qsCompare).then(function(r) { return r.json(); }),
        fetch(baseUrl + '/api/daily-trend?' + qs).then(function(r) { return r.json(); }),
        fetch(baseUrl + '/api/devices?' + qs).then(function(r) { return r.json(); }),
        fetch(baseUrl + '/api/countries?' + qs).then(function(r) { return r.json(); }),
        fetch(baseUrl + '/api/top-queries?' + qs + '&limit=20').then(function(r) { return r.json(); }),
        fetch(baseUrl + '/api/top-pages?' + qs + '&limit=20').then(function(r) { return r.json(); })
    ]).then(function(results) {
        var compare   = results[0];
        var trend     = results[1];
        var devices   = results[2];
        var countries = results[3];
        var queries   = results[4];
        var pages     = results[5];

        // KPIs
        var c = compare.current || {};
        var d = compare.diff || {};
        document.getElementById('kpi-clicks').textContent = fmt(c.clicks);
        document.getElementById('kpi-impressions').textContent = fmt(c.impressions);
        document.getElementById('kpi-ctr').textContent = ((c.ctr || 0) * 100).toFixed(2).replace('.', ',') + '%';
        document.getElementById('kpi-position').textContent = (c.position || 0).toFixed(1).replace('.', ',');
        setDiff('kpi-clicks-diff', d.clicks, false);
        setDiff('kpi-impressions-diff', d.impressions, false);
        setDiff('kpi-ctr-diff', d.ctr * 100, false, ' pts', 2);
        setDiff('kpi-position-diff', d.position, true, '', 1);

        // Graphiques
        window.dashboardData.dailyTrend = trend;
        if (typeof window.renderTrendChart === 'function') window.renderTrendChart(trend);

        window.dashboardData.devices = devices;
        if (typeof window.renderDeviceChart === 'function') window.renderDeviceChart(devices);

        window.dashboardData.countries = countries;
        if (typeof window.renderCountryChart === 'function') window.renderCountryChart(countries);

        // Top requêtes
        var tbodyQ = document.getElementById('queries-body');
        if (!queries.length) {
            tbodyQ.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">Aucune donnée</td></tr>';
        } else {
            tbodyQ.innerHTML = queries.map(function(q) {
                return '<tr>'
                    + '<td class="truncate">' + escapeHtml(q.query) + '</td>'
                    + '<td class="num">' + fmt(q.clicks) + '</td>'
                    + '<td class="num">' + fmt(q.impressions) + '</td>'
                    + '<td class="num">' + (q.ctr * 100).toFixed(2) + '%</td>'
                    + '<td class="num">' + Number(q.position).toFixed(1) + '</td>'
                    + '</tr>';
            }).join('');
        }

        // Top pages
        var tbodyP = document.getElementById('pages-body');
        if (!pages.length) {
            tbodyP.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">Aucune donnée</td></tr>';
        } else {
            tbodyP.innerHTML = pages.map(function(pg) {
                return '<tr>'
                    + '<td class="truncate">' + escapeHtml(pg.page) + '</td>'
                    + '<td class="num">' + fmt(pg.clicks) + '</td>'
                    + '<td class="num">' + fmt(pg.impressions) + '</td>'
                    + '<td class="num">' + (pg.ctr * 100).toFixed(2) + '%</td>'
                    + '<td class="num">' + Number(pg.position).toFixed(1) + '</td>'
                    + '</tr>';
            }).join('');
        }
    }).catch(function(err) {
        console.error('Erreur chargement dashboard:', err);
    });

    function setDiff(id, val, invertColor, suffix, decimals) {
        var el = document.getElementById(id);
        if (!el || val === undefined || val === null) return;
        decimals = decimals !== undefined ? decimals : 0;
        suffix = suffix || '';
        var n = Number(val);
        var sign = n >= 0 ? '+' : '';
        el.textContent = sign + n.toFixed(decimals).replace('.', ',') + suffix + ' vs période préc.';
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

<?php endif; // fin $authenticated && !empty($sites) ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
