<?php
$pageTitle   = 'Synchronisations — Search Console';
$currentPage = 'sync';
ob_start();
?>

<h2 style="margin-bottom:1rem">Historique des synchronisations</h2>

<div style="margin-bottom:1rem; display:flex; align-items:center; gap:1rem">
    <form style="display:flex; align-items:center; gap:0.5rem" id="syncForm">
        <select name="site_id" id="sync_site_id">
            <option value="">Tous les sites</option>
            <?php foreach ($sites as $s): ?>
                <option value="<?= $s['id'] ?>">
                    <?= htmlspecialchars($s['label'] ?: $s['site_url']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn" id="syncBtn">Lancer la synchronisation</button>
    </form>
    <?php if (empty($sites)): ?>
        <span style="color:#999; font-size:0.9em">Aucun site importe — la premiere sync importera vos sites depuis Google.</span>
    <?php endif; ?>
</div>

<!-- Progress bar (masque par defaut) -->
<div class="sync-progress-card" id="syncProgress" style="display:none">
    <h3>Synchronisation en cours</h3>
    <div class="sync-status-label" id="syncStatusLabel">Demarrage...</div>
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
                <th>ID</th>
                <th>Site</th>
                <th>Type</th>
                <th>Periode</th>
                <th>Lignes</th>
                <th>Inserees</th>
                <th>New</th>
                <th>Maj</th>
                <th>Duree</th>
                <th>Statut</th>
                <th>Erreur</th>
                <th>Date</th>
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
                        <span class="badge badge-success">OK</span>
                    <?php elseif ($log['status'] === 'empty'): ?>
                        <span class="badge badge-empty">Vide</span>
                    <?php elseif ($log['status'] === 'error'): ?>
                        <span class="badge badge-error">Erreur</span>
                    <?php else: ?>
                        <span class="badge badge-running">En cours</span>
                    <?php endif; ?>
                </td>
                <td class="truncate" style="max-width:250px"><?= htmlspecialchars($log['error_message'] ?? '') ?></td>
                <td style="white-space:nowrap"><?= $log['started_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="12" style="text-align:center;color:#999">Aucune synchronisation enregistree</td></tr>
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

    // Lancer la sync
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        btn.disabled = true;
        btn.textContent = 'Demarrage...';

        var siteId = document.getElementById('sync_site_id').value;
        var url = '/api/sync' + (siteId ? '?site_id=' + siteId : '');

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
                btn.textContent = 'Lancer la synchronisation';
            });
    });

    function showProgress() {
        card.style.display = '';
        barFill.style.width = '0%';
        barFill.className = 'progress-bar-fill';
        percentEl.textContent = '0 %';
        statusLabel.textContent = 'Demarrage...';
        taskList.innerHTML = '';
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        poll(); // premier appel immediat
        pollTimer = setInterval(poll, 2000);
    }

    function poll() {
        if (!currentJobId) return;

        fetch('/api/sync-progress?job_id=' + currentJobId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                updateUI(data);

                if (data.status === 'success' || data.status === 'error') {
                    clearInterval(pollTimer);
                    pollTimer = null;
                    // Auto-reload apres 3s
                    setTimeout(function() { location.reload(); }, 3000);
                }
            })
            .catch(function() {
                // Ignorer les erreurs reseau, on reessaye au prochain tick
            });
    }

    function updateUI(data) {
        var total = data.total_tasks || 0;
        var completed = data.completed_tasks || 0;
        var current = data.current_task;

        // Calcul du pourcentage avec granularite chunks
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
            statusLabel.textContent = 'En attente de demarrage...';
        } else if (data.status === 'running') {
            if (current) {
                statusLabel.textContent = current.site_url + ' (' + current.search_type + ') — chunk ' + current.done_chunks + '/' + current.total_chunks;
            } else {
                statusLabel.textContent = 'Synchronisation en cours... (' + completed + '/' + total + ' taches)';
            }
        } else if (data.status === 'success') {
            statusLabel.textContent = 'Synchronisation terminee ! Rechargement dans 3s...';
            btn.disabled = false;
            btn.textContent = 'Lancer la synchronisation';
        } else if (data.status === 'error') {
            statusLabel.textContent = 'Erreur : ' + (data.error_message || 'inconnue');
            btn.disabled = false;
            btn.textContent = 'Lancer la synchronisation';
        }

        // Liste des taches
        var html = '';

        // Taches terminees
        if (data.completed_list) {
            data.completed_list.forEach(function(t) {
                var cls = t.status === 'success' ? 'completed' : (t.status === 'empty' ? 'completed' : 'error');
                var icon = t.status === 'success' ? '&#10003;' : (t.status === 'empty' ? '&#9898;' : '&#10007;');
                var detail = t.status === 'empty'
                    ? 'vide, ' + t.duration_sec.toFixed(1) + 's'
                    : t.rows_fetched + ' lignes (' + (t.rows_new || 0) + ' new, ' + (t.rows_updated || 0) + ' maj), ' + t.duration_sec.toFixed(1) + 's';
                html += '<li class="sync-task-item ' + cls + '">'
                    + '<span class="task-icon">' + icon + '</span>'
                    + '<span>' + escapeHtml(t.site_url) + ' (' + t.search_type + ')'
                    + ' — ' + detail
                    + '</span></li>';
            });
        }

        // Tache en cours
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

    // Detection au chargement : verifier s'il y a un sync en cours
    fetch('/api/sync-progress')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'pending' || data.status === 'running') {
                currentJobId = data.job_id;
                btn.disabled = true;
                btn.textContent = 'Sync en cours...';
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
