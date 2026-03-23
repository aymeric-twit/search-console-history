<?php
$pageTitle   = 'Synchronisations — Search Console';
$currentPage = 'sync';
$authenticated = true; // sync-status n'est accessible que si connecté
ob_start();
?>

<h2 style="margin-bottom:1rem" data-i18n="sync.titre">Historique des synchronisations</h2>

<div style="margin-bottom:1rem; display:flex; align-items:center; gap:1rem">
    <form style="display:flex; align-items:center; gap:0.5rem" id="syncForm">
        <select name="site_id" id="sync_site_id">
            <option value="" data-i18n="sync.tousLesSites">Tous les sites</option>
            <?php foreach ($sites as $s): ?>
                <option value="<?= $s['id'] ?>">
                    <?= htmlspecialchars($s['label'] ?: $s['site_url']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn" id="syncBtn" data-i18n="sync.lancer">Lancer la synchronisation</button>
    </form>
    <?php if (empty($sites)): ?>
        <span style="color:#999; font-size:0.9em" data-i18n="sync.aucunSiteImporte">Aucun site importé — la première sync importera vos sites depuis Google.</span>
    <?php endif; ?>
</div>

<!-- Progress bar (masqué par défaut) -->
<div class="sync-progress-card" id="syncProgress" style="display:none">
    <h3 data-i18n="sync.enCours">Synchronisation en cours</h3>
    <div class="sync-status-label" id="syncStatusLabel" data-i18n="sync.demarrage">Démarrage...</div>
    <div class="progress-bar-track">
        <div class="progress-bar-fill" id="syncBarFill" style="width:0%"></div>
    </div>
    <div class="sync-percent" id="syncPercent">0 %</div>
    <ul class="sync-task-list" id="syncTaskList"></ul>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th data-i18n="syncTab.id">ID</th>
                <th data-i18n="syncTab.site">Site</th>
                <th data-i18n="syncTab.type">Type</th>
                <th data-i18n="syncTab.periode">Période</th>
                <th data-i18n="syncTab.lignes">Lignes</th>
                <th data-i18n="syncTab.inserees">Insérées</th>
                <th data-i18n="syncTab.new">New</th>
                <th data-i18n="syncTab.maj">Maj</th>
                <th data-i18n="syncTab.duree">Durée</th>
                <th data-i18n="syncTab.statut">Statut</th>
                <th data-i18n="syncTab.erreur">Erreur</th>
                <th data-i18n="syncTab.date">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= $log['id'] ?></td>
                <td class="truncate"><?= htmlspecialchars($log['site_url'] ?? '') ?></td>
                <td><?= htmlspecialchars($log['search_type']) ?></td>
                <td><?= $log['date_from'] ?> &rarr; <?= $log['date_to'] ?></td>
                <td class="num"><?= number_format((int)$log['rows_fetched'], 0, ',', ' ') ?></td>
                <td class="num"><?= number_format((int)$log['rows_inserted'], 0, ',', ' ') ?></td>
                <td class="num"><?= isset($log['rows_new']) ? number_format((int)$log['rows_new'], 0, ',', ' ') : '-' ?></td>
                <td class="num"><?= isset($log['rows_updated']) ? number_format((int)$log['rows_updated'], 0, ',', ' ') : '-' ?></td>
                <td class="num"><?= $log['duration_sec'] ? number_format($log['duration_sec'], 1) . 's' : '-' ?></td>
                <td>
                    <?php if ($log['status'] === 'success'): ?>
                        <span class="badge badge-success" data-i18n="syncTab.ok">OK</span>
                    <?php elseif ($log['status'] === 'empty'): ?>
                        <span class="badge badge-empty" data-i18n="syncTab.vide">Vide</span>
                    <?php elseif ($log['status'] === 'error'): ?>
                        <span class="badge badge-error" data-i18n="syncTab.erreurBadge">Erreur</span>
                    <?php else: ?>
                        <span class="badge badge-running" data-i18n="syncTab.enCours">En cours</span>
                    <?php endif; ?>
                </td>
                <td class="truncate" style="max-width:250px"><?= htmlspecialchars($log['error_message'] ?? '') ?></td>
                <td style="white-space:nowrap"><?= $log['started_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="12" style="text-align:center;color:#999" data-i18n="syncTab.aucuneSync">Aucune synchronisation enregistrée</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
(function() {
    var form       = document.getElementById('syncForm');
    var btn        = document.getElementById('syncBtn');
    var card       = document.getElementById('syncProgress');
    var barFill    = document.getElementById('syncBarFill');
    var percentEl  = document.getElementById('syncPercent');
    var statusLabel = document.getElementById('syncStatusLabel');
    var taskList   = document.getElementById('syncTaskList');
    var pollTimer  = null;
    var currentJobId = null;
    var baseUrl = window.MODULE_BASE_URL || '';

    // Lancer la sync
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        btn.disabled = true;
        btn.textContent = t('sync.demarrage');

        var siteId = document.getElementById('sync_site_id').value;
        var url = baseUrl + '/api/sync' + (siteId ? '?site_id=' + siteId : '');

        fetch(url, { method: 'POST' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'already_running') {
                    currentJobId = data.job_id;
                    showProgress();
                    startPolling();
                } else if (data.status === 'started') {
                    currentJobId = data.job_id;
                    showProgress();
                    startPolling();
                } else {
                    location.reload();
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = t('sync.lancer');
            });
    });

    function showProgress() {
        card.style.display = '';
        barFill.style.width = '0%';
        barFill.className = 'progress-bar-fill';
        percentEl.textContent = '0 %';
        statusLabel.textContent = t('sync.demarrage');
        taskList.innerHTML = '';
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        poll(); // premier appel immédiat
        pollTimer = setInterval(poll, 2000);
    }

    function poll() {
        if (!currentJobId) return;

        fetch(baseUrl + '/api/sync-progress?job_id=' + currentJobId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                updateUI(data);

                if (data.status === 'success' || data.status === 'error') {
                    clearInterval(pollTimer);
                    pollTimer = null;
                    // Auto-reload après 3s
                    setTimeout(function() { location.reload(); }, 3000);
                }
            })
            .catch(function() {
                // Ignorer les erreurs réseau, on réessaye au prochain tick
            });
    }

    function updateUI(data) {
        var total = data.total_tasks || 0;
        var completed = data.completed_tasks || 0;
        var current = data.current_task;

        // Calcul du pourcentage avec granularité chunks
        var percent = 0;
        if (total > 0) {
            var chunkFraction = 0;
            if (current && current.total_chunks > 0) {
                chunkFraction = current.done_chunks / current.total_chunks;
            }
            percent = ((completed + chunkFraction) / total) * 100;
        }

        if (data.status === 'success') percent = 100;

        percent = Math.min(100, Math.round(percent * 10) / 10);

        barFill.style.width = percent + '%';
        percentEl.textContent = percent + ' %';

        // Classe du fill
        barFill.className = 'progress-bar-fill';
        if (data.status === 'success') barFill.classList.add('done');
        if (data.status === 'error') barFill.classList.add('error');

        // Label de statut
        if (data.status === 'pending') {
            statusLabel.textContent = t('sync.enAttente');
        } else if (data.status === 'running') {
            if (current) {
                statusLabel.textContent = current.site_url + ' (' + current.search_type + ') — chunk ' + current.done_chunks + '/' + current.total_chunks;
            } else {
                statusLabel.textContent = t('sync.syncEnCours') + ' (' + completed + '/' + total + ' ' + t('sync.taches') + ')';
            }
        } else if (data.status === 'success') {
            statusLabel.textContent = t('sync.terminee');
            btn.disabled = false;
            btn.textContent = t('sync.lancer');
        } else if (data.status === 'error') {
            statusLabel.textContent = t('sync.erreur') + ' ' + (data.error_message || t('sync.inconnue'));
            btn.disabled = false;
            btn.textContent = t('sync.lancer');
        }

        // Liste des tâches
        var html = '';

        // Tâches terminées
        if (data.completed_list) {
            data.completed_list.forEach(function(tsk) {
                var cls = tsk.status === 'success' ? 'completed' : (tsk.status === 'empty' ? 'completed' : 'error');
                var icon = tsk.status === 'success' ? '&#10003;' : (tsk.status === 'empty' ? '&#9898;' : '&#10007;');
                var detail = tsk.status === 'empty'
                    ? t('sync.vide') + ', ' + tsk.duration_sec.toFixed(1) + 's'
                    : tsk.rows_fetched + ' ' + t('sync.lignes') + ' (' + (tsk.rows_new || 0) + ' new, ' + (tsk.rows_updated || 0) + ' maj), ' + tsk.duration_sec.toFixed(1) + 's';
                html += '<li class="sync-task-item ' + cls + '">'
                    + '<span class="task-icon">' + icon + '</span>'
                    + '<span>' + escapeHtml(tsk.site_url) + ' (' + tsk.search_type + ')'
                    + ' — ' + detail
                    + '</span></li>';
            });
        }

        // Tâche en cours
        if (current && data.status === 'running') {
            html += '<li class="sync-task-item running">'
                + '<span class="task-icon spinner-icon">&#9696;</span>'
                + '<span>' + escapeHtml(current.site_url) + ' (' + current.search_type + ')'
                + ' — chunk ' + current.done_chunks + '/' + current.total_chunks
                + '</span></li>';
        }

        taskList.innerHTML = html;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Détection au chargement : vérifier s'il y a un sync en cours
    fetch(baseUrl + '/api/sync-progress')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'pending' || data.status === 'running') {
                currentJobId = data.job_id;
                btn.disabled = true;
                btn.textContent = t('sync.syncEnCoursBtn');
                showProgress();
                updateUI(data);
                startPolling();
            }
        })
        .catch(function() {});
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
