<?php
$pageTitle   = 'Search Console History';
$currentPage = 'dashboard';
$prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
$authenticated = $this->authenticated ?? false;
ob_start();
?>

<!-- ── Card principale : connexion + filtres ── -->
<div class="row g-4 mb-4">
<div class="col-lg-8">
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2><i class="bi bi-graph-up"></i> <span data-i18n="gsc.titre">Google Search Console</span></h2>
    </div>
    <div class="card-body">

        <!-- Section connexion GSC -->
        <div id="gsc-connexion-section" class="mb-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span id="gsc-badge-statut" class="gsc-status-badge gsc-status-deconnecte">
                    <i class="bi bi-circle-fill"></i> <span id="gsc-badge-text" data-i18n="gsc.verification">Vérification...</span>
                </span>
                <button type="button" class="btn btn-sm btn-primary d-none" id="btn-connecter-gsc" onclick="connecterGsc()">
                    <i class="bi bi-google"></i> <span data-i18n="gsc.connecter">Connecter Google Search Console</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btn-deconnecter-gsc" onclick="deconnecterGsc()">
                    <i class="bi bi-box-arrow-left"></i> <span data-i18n="gsc.deconnecter">Déconnecter</span>
                </button>
            </div>
            <div id="gsc-erreur-oauth" class="alert alert-danger d-none" role="alert"></div>
            <div id="gsc-non-configure" class="alert alert-warning d-none">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <span data-i18n="gsc.nonConfigure">La connexion Google Search Console n'est pas disponible. Contactez l'administrateur.</span>
            </div>
        </div>

        <!-- Formulaire filtres (visible si connecté + sites) -->
        <form id="dashboard-filters" method="GET" action="<?= $prefix ?>/" class="<?= ($authenticated && !empty($sites)) ? '' : 'd-none' ?>">
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label for="site_id" class="form-label" data-i18n="filtres.propriete">Propriété GSC</label>
                    <select name="site_id" id="site_id" class="form-select">
                        <?php foreach ($sites ?? [] as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($s['id'] ?? 0) == ($siteId ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['label'] ?: $s['site_url']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="from" class="form-label" data-i18n="filtres.dateDebut">Date de début</label>
                    <input type="date" name="from" id="from" class="form-control" value="<?= htmlspecialchars($from ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="to" class="form-label" data-i18n="filtres.dateFin">Date de fin</label>
                    <input type="date" name="to" id="to" class="form-control" value="<?= htmlspecialchars($to ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="device" class="form-label" data-i18n="filtres.appareil">Appareil</label>
                    <select name="device" id="device" class="form-select">
                        <option value="" data-i18n="filtres.tous">Tous</option>
                        <option value="DESKTOP" <?= ($filters['device'] ?? '') === 'DESKTOP' ? 'selected' : '' ?>>Desktop</option>
                        <option value="MOBILE" <?= ($filters['device'] ?? '') === 'MOBILE' ? 'selected' : '' ?>>Mobile</option>
                        <option value="TABLET" <?= ($filters['device'] ?? '') === 'TABLET' ? 'selected' : '' ?>>Tablet</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label for="country" class="form-label" data-i18n="filtres.pays">Pays</label>
                    <input type="text" name="country" id="country" class="form-control" placeholder="FRA" value="<?= htmlspecialchars($filters['country'] ?? '') ?>">
                </div>
            </div>

            <!-- Filtres avancés -->
            <div class="mb-3">
                <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#filtres-avances" role="button">
                    <i class="bi bi-sliders"></i> <span data-i18n="filtres.avances">Filtres avancés</span>
                </a>
                <div class="collapse mt-2" id="filtres-avances">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="filter_query" class="form-label" data-i18n="filtres.requete">Requête</label>
                            <input type="text" name="filter_query" id="filter_query" class="form-control" data-i18n-placeholder="filtres.requetePlaceholder" placeholder="Filtrer par mot-clé..." value="<?= htmlspecialchars($filters['query'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter_page" class="form-label" data-i18n="filtres.page">Page</label>
                            <input type="text" name="filter_page" id="filter_page" class="form-control" data-i18n-placeholder="filtres.pagePlaceholder" placeholder="/chemin..." value="<?= htmlspecialchars($filters['page'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel"></i> <span data-i18n="filtres.filtrer">Filtrer</span>
            </button>
        </form>

        <?php if ($authenticated && empty($sites ?? [])): ?>
        <p class="text-muted mt-2 mb-0">
            <span data-i18n="filtres.aucunSite">Aucun site importé.</span> <a href="<?= $prefix ?>/sync-status" data-i18n="filtres.lancerSync">Lancez une synchronisation</a> <span data-i18n="filtres.pourImporter">pour importer vos propriétés.</span>
        </p>
        <?php endif; ?>

    </div>
</div>
</div>
<div class="col-lg-4" id="helpPanel">
    <div class="config-help-panel">
        <div class="help-title mb-2">
            <i class="bi bi-info-circle me-1"></i> <span data-i18n="aide.commentCaMarche">Comment ça marche</span>
        </div>
        <ul>
            <li><strong data-i18n="aide.connexionOauth">Connexion OAuth</strong> : <span data-i18n="aide.connexionOauthDesc">connectez votre compte Google pour accéder à Search Console.</span></li>
            <li><strong data-i18n="aide.synchronisation">Synchronisation</strong> : <span data-i18n="aide.synchronisationDesc">les données sont synchronisées automatiquement.</span></li>
            <li><strong data-i18n="aide.tendances">Tendances</strong> : <span data-i18n="aide.tendancesDesc">consultez les tendances quotidiennes (clics, impressions, CTR, position).</span></li>
            <li><strong data-i18n="aide.segments">Segments</strong> : <span data-i18n="aide.segmentsDesc">filtrez par requête, page, appareil et pays.</span></li>
            <li><strong data-i18n="aide.comparaison">Comparaison</strong> : <span data-i18n="aide.comparaisonDesc">comparez deux périodes pour détecter les évolutions.</span></li>
        </ul>
        <hr>
        <div class="help-title mb-2">
            <i class="bi bi-infinity me-1"></i> <span data-i18n="aide.quota">Quota</span>
        </div>
        <ul class="mb-0">
            <li data-i18n="aide.quotaDesc">Aucun quota — synchronisation illimitée.</li>
        </ul>
    </div>
</div>
</div>

<?php if ($authenticated && !empty($sites)): ?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="label" data-i18n="kpi.clicks">Clicks</div>
        <div class="value" id="kpi-clicks">...</div>
        <div class="diff" id="kpi-clicks-diff"></div>
    </div>
    <div class="kpi-card">
        <div class="label" data-i18n="kpi.impressions">Impressions</div>
        <div class="value" id="kpi-impressions">...</div>
        <div class="diff" id="kpi-impressions-diff"></div>
    </div>
    <div class="kpi-card">
        <div class="label" data-i18n="kpi.ctrMoyen">CTR moyen</div>
        <div class="value" id="kpi-ctr">...</div>
        <div class="diff" id="kpi-ctr-diff"></div>
    </div>
    <div class="kpi-card">
        <div class="label" data-i18n="kpi.positionMoyenne">Position moyenne</div>
        <div class="value" id="kpi-position">...</div>
        <div class="diff" id="kpi-position-diff"></div>
    </div>
</div>

<!-- Graphique tendance -->
<div class="chart-container">
    <h3 data-i18n="graphique.tendance">Tendance quotidienne</h3>
    <canvas id="trendChart" height="100"></canvas>
</div>

<!-- Graphiques appareils + pays -->
<div class="grid-2">
    <div class="chart-container">
        <h3 data-i18n="graphique.appareils">Répartition par appareil</h3>
        <canvas id="deviceChart" height="200"></canvas>
    </div>
    <div class="chart-container">
        <h3 data-i18n="graphique.topPays">Top pays</h3>
        <canvas id="countryChart" height="200"></canvas>
    </div>
</div>

<!-- Tableaux Top Requêtes + Top Pages -->
<div class="grid-2">
    <div class="data-table-wrap">
        <h3 data-i18n="tableau.topRequetes">Top requêtes</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th data-i18n="tableau.requete">Requête</th>
                    <th data-i18n="tableau.clicks">Clicks</th>
                    <th data-i18n="tableau.impressions">Impressions</th>
                    <th data-i18n="tableau.ctr">CTR</th>
                    <th data-i18n="tableau.position">Position</th>
                </tr>
            </thead>
            <tbody id="queries-body">
                <tr><td colspan="5" style="text-align:center;color:#999" data-i18n="tableau.chargement">Chargement...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="data-table-wrap">
        <h3 data-i18n="tableau.topPages">Top pages</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th data-i18n="tableau.page">Page</th>
                    <th data-i18n="tableau.clicks">Clicks</th>
                    <th data-i18n="tableau.impressions">Impressions</th>
                    <th data-i18n="tableau.ctr">CTR</th>
                    <th data-i18n="tableau.position">Position</th>
                </tr>
            </thead>
            <tbody id="pages-body">
                <tr><td colspan="5" style="text-align:center;color:#999" data-i18n="tableau.chargement">Chargement...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php endif; // fin $authenticated && !empty($sites) ?>

<!-- JS : OAuth popup + chargement données -->
<script>
(function() {
    var baseUrl = window.MODULE_BASE_URL || '<?= $prefix ?>';

    // ── OAuth popup : écouter postMessage ──
    window.addEventListener('message', function(e) {
        if (e.data && typeof e.data.succes !== 'undefined') {
            if (e.data.succes) {
                location.reload();
            } else {
                var erreurEl = document.getElementById('gsc-erreur-oauth');
                if (erreurEl) {
                    erreurEl.textContent = e.data.erreur || t('gsc.erreurConnexion');
                    erreurEl.classList.remove('d-none');
                }
                // Rétablir le bouton
                var btn = document.getElementById('btn-connecter-gsc');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-google"></i> ' + t('gsc.connecter'); }
            }
        }
    });

    // ── Vérifier le statut GSC au chargement ──
    verifierStatutGsc();

    function verifierStatutGsc() {
        fetch(baseUrl + '/api/gsc-status')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.configure) {
                    var msgNonConfigure = document.getElementById('gsc-non-configure');
                    if (msgNonConfigure) msgNonConfigure.classList.remove('d-none');
                    mettreAJourUiGsc(false);
                    return;
                }

                var btnConn = document.getElementById('btn-connecter-gsc');
                if (btnConn && !data.connecte) btnConn.classList.remove('d-none');

                mettreAJourUiGsc(data.connecte);
            })
            .catch(function() {
                // Silencieux
            });
    }

    function mettreAJourUiGsc(connecte) {
        var badge     = document.getElementById('gsc-badge-statut');
        var badgeText = document.getElementById('gsc-badge-text');
        var btnConn   = document.getElementById('btn-connecter-gsc');
        var btnDeco   = document.getElementById('btn-deconnecter-gsc');
        var formEl    = document.getElementById('dashboard-filters');

        if (badge) {
            badge.className = 'gsc-status-badge ' + (connecte ? 'gsc-status-connecte' : 'gsc-status-deconnecte');
        }
        if (badgeText) {
            badgeText.textContent = connecte ? t('gsc.connecte') : t('gsc.nonConnecte');
        }
        if (btnConn) btnConn.classList.toggle('d-none', connecte);
        if (btnDeco) btnDeco.classList.toggle('d-none', !connecte);
    }

    // ── Connecter : ouvrir popup OAuth ──
    window.connecterGsc = function() {
        var btn = document.getElementById('btn-connecter-gsc');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + t('gsc.connexion'); }

        // Masquer l'erreur précédente
        var erreurEl = document.getElementById('gsc-erreur-oauth');
        if (erreurEl) erreurEl.classList.add('d-none');

        fetch(baseUrl + '/api/auth-url')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.url) {
                    var largeur = 600, hauteur = 700;
                    var gauche = (screen.width - largeur) / 2;
                    var haut   = (screen.height - hauteur) / 2;
                    window.open(
                        data.url,
                        'gsc_oauth_popup',
                        'width=' + largeur + ',height=' + hauteur + ',left=' + gauche + ',top=' + haut + ',scrollbars=yes'
                    );
                } else {
                    if (erreurEl) {
                        erreurEl.textContent = data.error || t('gsc.erreurConnexion');
                        erreurEl.classList.remove('d-none');
                    }
                }
            })
            .catch(function() {
                if (erreurEl) {
                    erreurEl.textContent = t('gsc.erreurReseau');
                    erreurEl.classList.remove('d-none');
                }
            })
            .finally(function() {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-google"></i> ' + t('gsc.connecter'); }
            });
    };

    // ── Déconnecter : appel AJAX ──
    window.deconnecterGsc = function() {
        var btn = document.getElementById('btn-deconnecter-gsc');
        if (btn) btn.disabled = true;

        fetch(baseUrl + '/api/logout', { method: 'POST' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.succes) {
                    location.reload();
                }
            })
            .catch(function() {})
            .finally(function() {
                if (btn) btn.disabled = false;
            });
    };

<?php if ($authenticated && !empty($sites) && ($siteId ?? 0) > 0): ?>
    // ── Chargement des données dashboard ──
    var p = {
        siteId: <?= (int)$siteId ?>,
        from: '<?= htmlspecialchars($from) ?>',
        to: '<?= htmlspecialchars($to) ?>',
        searchType: '<?= htmlspecialchars($filters['search_type'] ?? 'web') ?>',
        device: '<?= htmlspecialchars($filters['device'] ?? '') ?>',
        country: '<?= htmlspecialchars($filters['country'] ?? '') ?>',
        filterQuery: '<?= htmlspecialchars($filters['query'] ?? '') ?>',
        filterPage: '<?= htmlspecialchars($filters['page'] ?? '') ?>'
    };

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
        window.dashboardData = { dailyTrend: trend, devices: devices, countries: countries };
        if (typeof window.renderTrendChart === 'function') window.renderTrendChart(trend);
        if (typeof window.renderDeviceChart === 'function') window.renderDeviceChart(devices);
        if (typeof window.renderCountryChart === 'function') window.renderCountryChart(countries);

        // Top requêtes
        var tbodyQ = document.getElementById('queries-body');
        if (!queries.length) {
            tbodyQ.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">' + t('tableau.aucuneDonnee') + '</td></tr>';
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
            tbodyP.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">' + t('tableau.aucuneDonnee') + '</td></tr>';
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
        el.textContent = sign + n.toFixed(decimals).replace('.', ',') + suffix + ' ' + t('kpi.vsPeriodePrec');
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
<?php endif; ?>

    // --- Help panel collapse ---
    function collapserHelpPanel() {
        var panel = document.getElementById('helpPanel');
        if (panel) panel.classList.add('help-hidden');
    }
})();
</script>
<?php if ($authenticated && !empty($sites)): ?>
<script src="<?= $prefix ?>/assets/js/dashboard.js"></script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
