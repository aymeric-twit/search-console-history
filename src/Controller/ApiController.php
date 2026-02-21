<?php

namespace App\Controller;

use App\Auth\GoogleOAuth;
use App\Model\PerformanceData;
use App\Model\Site;
use App\Model\SyncJob;
use App\Model\SyncLog;

/**
 * Endpoints API JSON pour exposer les données à un front JS ou outil externe.
 *
 * Toutes les réponses sont en JSON.
 */
class ApiController
{
    private PerformanceData $perfModel;
    private Site $siteModel;
    private SyncLog $syncLog;
    private SyncJob $syncJob;

    public function __construct()
    {
        $oauth = new GoogleOAuth();
        if (!$oauth->hasToken()) {
            $this->json(['error' => 'Non authentifié'], 401);
            exit;
        }

        $this->perfModel = new PerformanceData();
        $this->siteModel = new Site();
        $this->syncLog   = new SyncLog();
        $this->syncJob   = new SyncJob();
    }

    /** GET /api/sites — Liste des sites. */
    public function sites(): void
    {
        $this->json($this->siteModel->allActive());
    }

    /** GET /api/daily-trend?site_id=X&from=Y&to=Z */
    public function dailyTrend(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $data = $this->perfModel->getDailyTrend($siteId, $from, $to, $filters);
        $this->json($data);
    }

    /** GET /api/top-queries?site_id=X&from=Y&to=Z&limit=50 */
    public function topQueries(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $limit = (int) ($_GET['limit'] ?? 50);
        $data = $this->perfModel->topQueries($siteId, $from, $to, $limit, $filters);
        $this->json($data);
    }

    /** GET /api/top-pages?site_id=X&from=Y&to=Z&limit=50 */
    public function topPages(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $limit = (int) ($_GET['limit'] ?? 50);
        $data = $this->perfModel->topPages($siteId, $from, $to, $limit, $filters);
        $this->json($data);
    }

    /** GET /api/devices?site_id=X&from=Y&to=Z */
    public function devices(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $data = $this->perfModel->byDevice($siteId, $from, $to, $filters);
        $this->json($data);
    }

    /** GET /api/countries?site_id=X&from=Y&to=Z */
    public function countries(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $limit = (int) ($_GET['limit'] ?? 20);
        $data = $this->perfModel->byCountry($siteId, $from, $to, $limit, $filters);
        $this->json($data);
    }

    /** GET /api/totals?site_id=X&from=Y&to=Z */
    public function totals(): void
    {
        [$siteId, $from, $to, $filters] = $this->parseParams();
        $data = $this->perfModel->periodTotals($siteId, $from, $to, $filters);
        $this->json($data);
    }

    /** GET /api/compare?site_id=X&from1=Y&to1=Z&from2=A&to2=B */
    public function compare(): void
    {
        $siteId = (int) ($_GET['site_id'] ?? 0);
        $from1 = $_GET['from1'] ?? date('Y-m-d', strtotime('-33 days'));
        $to1   = $_GET['to1']   ?? date('Y-m-d', strtotime('-3 days'));
        $from2 = $_GET['from2'] ?? date('Y-m-d', strtotime('-63 days'));
        $to2   = $_GET['to2']   ?? date('Y-m-d', strtotime('-34 days'));

        $filters = $this->parseFilters();
        $data = $this->perfModel->comparePeriods($siteId, $from1, $to1, $from2, $to2, $filters);
        $this->json($data);
    }

    /** GET /api/sync-logs */
    public function syncLogs(): void
    {
        $limit = (int) ($_GET['limit'] ?? 50);
        $this->json($this->syncLog->recent($limit));
    }

    /** POST /api/sync — Déclenche une synchronisation en arriere-plan. */
    public function triggerSync(): void
    {
        // Verifier qu'aucun sync ne tourne deja
        $running = $this->syncJob->findRunning();
        if ($running) {
            $this->json([
                'status' => 'already_running',
                'job_id' => (int) $running['id'],
            ]);
            return;
        }

        $siteId = isset($_GET['site_id']) && $_GET['site_id'] !== ''
            ? (int) $_GET['site_id']
            : null;

        // Creer le job en base
        $jobId = $this->syncJob->create($siteId);

        // Creer le repertoire storage/ s'il n'existe pas
        $storageDir = realpath(__DIR__ . '/../../') . '/storage';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $logFile = $storageDir . '/sync-job-' . $jobId . '.log';

        // Sauvegarder le chemin du log en BDD
        $this->syncJob->setLogFile($jobId, $logFile);

        // Construire la commande CLI
        $phpBin  = PHP_BINARY;
        $script  = realpath(__DIR__ . '/../../bin/sync.php');
        $cmd     = sprintf('%s %s --job-id=%d', escapeshellarg($phpBin), escapeshellarg($script), $jobId);

        if ($siteId !== null) {
            $cmd .= sprintf(' --site-id=%d', $siteId);
        }

        // Lancer en arriere-plan avec sortie redirigee vers un fichier log
        exec($cmd . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &');

        $this->json([
            'status' => 'started',
            'job_id' => $jobId,
        ]);
    }

    /** GET /api/sync-progress?job_id=N — Etat complet d'un job de sync. */
    public function syncProgress(): void
    {
        $jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;

        // Si pas de job_id, chercher le job en cours
        if ($jobId === 0) {
            $job = $this->syncJob->findRunning();
            if (!$job) {
                $this->json(['status' => 'idle']);
                return;
            }
            $jobId = (int) $job['id'];
        } else {
            $job = $this->syncJob->find($jobId);
            if (!$job) {
                $this->json(['error' => 'Job introuvable'], 404);
                return;
            }
        }

        // Detection de crash : si le process n'existe plus mais le job est running
        if (in_array($job['status'], ['pending', 'running']) && !empty($job['pid'])) {
            $pid = (int) $job['pid'];
            if ($pid > 0 && !$this->isProcessAlive($pid)) {
                $errorMsg = 'Le process a crashe (PID ' . $pid . ' introuvable)';
                // Tenter de lire les dernieres lignes du fichier log pour contexte
                if (!empty($job['log_file'])) {
                    $tail = $this->tailFile($job['log_file'], 10);
                    if ($tail !== '') {
                        $errorMsg .= "\n--- Dernières lignes du log ---\n" . $tail;
                    }
                }
                $this->syncJob->error($jobId, $errorMsg);
                $job = $this->syncJob->find($jobId);
            }
        }

        // Detection de timeout : si le job tourne depuis trop longtemps
        $timeoutMinutes = (int) ($_ENV['SYNC_TIMEOUT_MINUTES'] ?? 30);
        if (in_array($job['status'], ['pending', 'running']) && !empty($job['started_at'])) {
            $startedAt = strtotime($job['started_at']);
            $elapsed = (time() - $startedAt) / 60;
            if ($elapsed > $timeoutMinutes) {
                // Tenter de tuer le process
                if (!empty($job['pid']) && function_exists('posix_kill')) {
                    $pid = (int) $job['pid'];
                    if ($pid > 0 && $this->isProcessAlive($pid)) {
                        posix_kill($pid, 15); // SIGTERM
                    }
                }
                $errorMsg = sprintf('Timeout : le process depasse %d minutes (%.1f min ecoulees)', $timeoutMinutes, $elapsed);
                $this->syncJob->error($jobId, $errorMsg);
                // Marquer aussi le sync_log en cours en erreur
                $currentLog = $this->syncLog->findRunningByJob($jobId);
                if ($currentLog) {
                    $this->syncLog->error((int) $currentLog['id'], $errorMsg, $elapsed * 60);
                }
                $job = $this->syncJob->find($jobId);
            }
        }

        // Tache en cours
        $currentTask = $this->syncLog->findRunningByJob($jobId);

        // Taches terminees
        $completedTasks = $this->syncLog->completedByJob($jobId);

        $this->json([
            'job_id'          => (int) $job['id'],
            'status'          => $job['status'],
            'total_tasks'     => (int) $job['total_tasks'],
            'completed_tasks' => (int) $job['completed_tasks'],
            'error_message'   => $job['error_message'],
            'started_at'      => $job['started_at'],
            'finished_at'     => $job['finished_at'],
            'current_task'    => $currentTask ? [
                'log_id'       => (int) $currentTask['id'],
                'site_url'     => $currentTask['site_url'],
                'search_type'  => $currentTask['search_type'],
                'total_chunks' => (int) $currentTask['total_chunks'],
                'done_chunks'  => (int) $currentTask['done_chunks'],
            ] : null,
            'completed_list'  => array_map(fn($t) => [
                'site_url'      => $t['site_url'],
                'search_type'   => $t['search_type'],
                'status'        => $t['status'],
                'rows_fetched'  => (int) $t['rows_fetched'],
                'rows_new'      => (int) ($t['rows_new'] ?? 0),
                'rows_updated'  => (int) ($t['rows_updated'] ?? 0),
                'duration_sec'  => (float) $t['duration_sec'],
            ], $completedTasks),
        ]);
    }

    /** GET /api/sync-diagnostic?site_id=X&search_type=web — Diagnostic de couverture des donnees. */
    public function syncDiagnostic(): void
    {
        $siteId = (int) ($_GET['site_id'] ?? 0);
        if ($siteId === 0) {
            $this->json(['error' => 'site_id requis'], 400);
            return;
        }

        $searchType = $_GET['search_type'] ?? 'web';

        $diagnostic = $this->perfModel->syncDiagnostic($siteId, $searchType);

        // Ajouter les infos du dernier sync log
        $lastSync = $this->syncLog->lastSuccess($siteId, $searchType);

        $this->json([
            'data'      => $diagnostic,
            'last_sync' => $lastSync ? [
                'id'        => (int) $lastSync['id'],
                'date_from' => $lastSync['date_from'],
                'date_to'   => $lastSync['date_to'],
                'status'    => $lastSync['status'],
                'rows_fetched' => (int) $lastSync['rows_fetched'],
                'finished_at'  => $lastSync['finished_at'],
            ] : null,
        ]);
    }

    /** Verifie si un process est encore en vie. */
    private function isProcessAlive(int $pid): bool
    {
        if (function_exists('posix_getpgid')) {
            return posix_getpgid($pid) !== false;
        }
        // Fallback : verifier via /proc
        return file_exists("/proc/{$pid}");
    }

    /** Lit les N dernieres lignes d'un fichier. */
    private function tailFile(string $path, int $lines = 10): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }
        $content = file($path, FILE_IGNORE_NEW_LINES);
        if ($content === false) {
            return '';
        }
        return implode("\n", array_slice($content, -$lines));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function parseParams(): array
    {
        $siteId = (int) ($_GET['site_id'] ?? 0);
        $from   = $_GET['from'] ?? date('Y-m-d', strtotime('-33 days'));
        $to     = $_GET['to']   ?? date('Y-m-d', strtotime('-3 days'));

        return [$siteId, $from, $to, $this->parseFilters()];
    }

    private function parseFilters(): array
    {
        return array_filter([
            'device'      => $_GET['device']      ?? '',
            'country'     => $_GET['country']      ?? '',
            'search_type' => $_GET['search_type']  ?? '',
            'query'       => $_GET['filter_query'] ?? '',
            'page'        => $_GET['filter_page']  ?? '',
        ]);
    }

    private function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
