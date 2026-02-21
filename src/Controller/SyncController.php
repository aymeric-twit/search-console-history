<?php

namespace App\Controller;

use App\Auth\GoogleOAuth;
use App\Model\PerformanceData;
use App\Model\Site;
use App\Model\SyncJob;
use App\Model\SyncLog;
use App\Service\SearchConsoleAPI;

/**
 * Orchestre la synchronisation des données Search Console.
 *
 * Stratégie :
 * 1. Lister les sites actifs en base (ou les importer depuis l'API)
 * 2. Pour chaque site et chaque searchType, déterminer la plage de dates à sync
 * 3. Découper en tranches de 30 jours pour éviter les réponses trop volumineuses
 * 4. Récupérer les données, les insérer en base, loguer le résultat
 */
class SyncController
{
    private SearchConsoleAPI $api;
    private PerformanceData $perfModel;
    private Site $siteModel;
    private SyncLog $syncLog;
    private SyncJob $syncJob;

    private int $daysBack;
    private string $dataState;
    private array $searchTypes;
    private ?int $jobId = null;

    public function __construct()
    {
        $auth = new GoogleOAuth();
        $this->api       = new SearchConsoleAPI($auth);
        $this->perfModel = new PerformanceData();
        $this->siteModel = new Site();
        $this->syncLog   = new SyncLog();
        $this->syncJob   = new SyncJob();

        $this->daysBack    = (int) ($_ENV['SYNC_DAYS_BACK']    ?? 480);
        $this->dataState   = $_ENV['SYNC_DATA_STATE']          ?? 'all';
        $this->searchTypes = array_map('trim', explode(',', $_ENV['SYNC_SEARCH_TYPES'] ?? 'web'));
    }

    /** Definit le job_id pour le suivi de progression. */
    public function setJobId(int $jobId): void
    {
        $this->jobId = $jobId;
    }

    /**
     * Lance une synchronisation complète.
     * Appelé par le cron ou manuellement.
     *
     * @param bool $importSites  Si true, importe les sites depuis l'API GSC
     * @param int|null $siteId   Si fourni, ne synchronise que ce site
     */
    public function run(bool $importSites = true, ?int $siteId = null): array
    {
        $results = [];

        // Import des sites depuis l'API si demandé
        if ($importSites) {
            $this->importSites();
        }

        // Déterminer les sites à synchroniser
        if ($siteId !== null) {
            $site = $this->siteModel->find($siteId);
            $sites = $site ? [$site] : [];
        } else {
            $sites = $this->siteModel->allActive();
        }

        if (empty($sites)) {
            $this->log('Aucun site à synchroniser.');
            if ($this->jobId) {
                $this->syncJob->start($this->jobId, 0);
                $this->syncJob->success($this->jobId);
            }
            return $results;
        }

        // Calculer le nombre total de taches (sites * searchTypes)
        $totalTasks = count($sites) * count($this->searchTypes);
        if ($this->jobId) {
            $this->syncJob->start($this->jobId, $totalTasks);
        }

        try {
            foreach ($sites as $site) {
                foreach ($this->searchTypes as $searchType) {
                    $result = $this->syncSite($site, $searchType);
                    $results[] = $result;

                    // Avancer le compteur de taches du job
                    if ($this->jobId) {
                        $this->syncJob->advanceTask($this->jobId);
                    }
                }
            }

            if ($this->jobId) {
                $this->syncJob->success($this->jobId);
            }
        } catch (\Throwable $e) {
            if ($this->jobId) {
                $this->syncJob->error($this->jobId, $e->getMessage());
            }
            throw $e;
        }

        return $results;
    }

    /** Importe la liste des sites depuis l'API et les enregistre en base. */
    public function importSites(): int
    {
        $apiSites = $this->api->listSites();
        $count = 0;

        foreach ($apiSites as $s) {
            $this->siteModel->upsert($s['siteUrl']);
            $count++;
        }

        $this->log("Import : {$count} site(s) synchronisé(s) depuis l'API.");

        return $count;
    }

    /**
     * Synchronise un site pour un type de recherche donné.
     * Découpe la plage en tranches de 30 jours.
     */
    private function syncSite(array $site, string $searchType): array
    {
        $siteId  = (int) $site['id'];
        $siteUrl = $site['site_url'];

        // Déterminer la plage de dates
        // Les données GSC ne sont disponibles qu'avec ~3 jours de retard
        $endDate   = date('Y-m-d', strtotime('-3 days'));
        $startDate = date('Y-m-d', strtotime("-{$this->daysBack} days"));

        // Reprendre là où on s'est arrêté si une sync précédente existe
        $lastSync = $this->syncLog->lastSuccess($siteId, $searchType);
        if ($lastSync) {
            // On reprend au lendemain de la dernière date synchronisée
            $resumeDate = date('Y-m-d', strtotime($lastSync['date_to'] . ' +1 day'));
            if ($resumeDate > $startDate) {
                $startDate = $resumeDate;
            }
        }

        if ($startDate > $endDate) {
            $this->log("[{$siteUrl}][{$searchType}] Déjà à jour.");
            return [
                'site'        => $siteUrl,
                'search_type' => $searchType,
                'status'      => 'up_to_date',
            ];
        }

        $this->log("[{$siteUrl}][{$searchType}] Sync du {$startDate} au {$endDate}...");

        // Découper en tranches de 30 jours
        $chunks = $this->dateChunks($startDate, $endDate, 30);

        $totalFetched  = 0;
        $totalNew      = 0;
        $totalUpdated  = 0;
        $lastChunkWithData = null; // high-water mark : dernier chunk ayant retourne des donnees
        $logId = $this->syncLog->start($siteId, $searchType, $startDate, $endDate, $this->jobId);
        $startTime = microtime(true);

        // Ecrire le nombre de chunks pour le suivi de progression
        $this->syncLog->setChunks($logId, count($chunks));

        try {
            foreach ($chunks as [$chunkStart, $chunkEnd]) {
                $this->log("  Tranche : {$chunkStart} -> {$chunkEnd}");

                $rows = $this->api->fetchPerformanceData(
                    $siteUrl,
                    $chunkStart,
                    $chunkEnd,
                    $searchType,
                    $this->dataState
                );

                $fetched = count($rows);
                $totalFetched += $fetched;

                if ($fetched > 0) {
                    $result = $this->perfModel->upsertBatch($siteId, $searchType, $rows);
                    $totalNew     += $result['new'];
                    $totalUpdated += $result['updated'];
                    $lastChunkWithData = $chunkEnd;
                }

                // Avancer le compteur de chunks
                $this->syncLog->advanceChunk($logId);

                $this->log("  -> {$fetched} lignes récupérées.");
            }

            $duration = round(microtime(true) - $startTime, 2);
            $totalInserted = $totalNew + $totalUpdated;

            // Si aucune ligne recue sur toute la plage → statut "empty"
            // Cela evite que lastSuccess() trouve cet enregistrement et bloque les futures syncs
            if ($totalFetched === 0) {
                $this->syncLog->markEmpty($logId, $duration);

                $this->log("[{$siteUrl}][{$searchType}] Aucune donnée retournée (empty) en {$duration}s.");

                return [
                    'site'          => $siteUrl,
                    'search_type'   => $searchType,
                    'status'        => 'empty',
                    'rows_fetched'  => 0,
                    'rows_inserted' => 0,
                    'duration'      => $duration,
                ];
            }

            // Si partiellement vide : restreindre date_to au dernier chunk avec des donnees
            $effectiveDateTo = null;
            if ($lastChunkWithData !== null && $lastChunkWithData < $endDate) {
                $effectiveDateTo = $lastChunkWithData;
            }

            $this->syncLog->success(
                $logId, $totalFetched, $totalInserted, $duration,
                $totalNew, $totalUpdated, $effectiveDateTo
            );

            $this->log("[{$siteUrl}][{$searchType}] Terminé : {$totalFetched} récupérées ({$totalNew} new, {$totalUpdated} updated) en {$duration}s.");

            return [
                'site'          => $siteUrl,
                'search_type'   => $searchType,
                'status'        => 'success',
                'rows_fetched'  => $totalFetched,
                'rows_inserted' => $totalInserted,
                'rows_new'      => $totalNew,
                'rows_updated'  => $totalUpdated,
                'duration'      => $duration,
            ];
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $this->syncLog->error($logId, $e->getMessage(), $duration);

            $this->log("[{$siteUrl}][{$searchType}] ERREUR : {$e->getMessage()}");

            return [
                'site'        => $siteUrl,
                'search_type' => $searchType,
                'status'      => 'error',
                'error'       => $e->getMessage(),
                'duration'    => $duration,
            ];
        }
    }

    /**
     * Découpe une plage de dates en tranches de $days jours.
     *
     * @return array<array{0: string, 1: string}>
     */
    private function dateChunks(string $start, string $end, int $days): array
    {
        $chunks = [];
        $current = $start;

        while ($current <= $end) {
            $chunkEnd = date('Y-m-d', strtotime("{$current} +{$days} days -1 day"));
            if ($chunkEnd > $end) {
                $chunkEnd = $end;
            }
            $chunks[] = [$current, $chunkEnd];
            $current = date('Y-m-d', strtotime("{$chunkEnd} +1 day"));
        }

        return $chunks;
    }

    private function log(string $message): void
    {
        $ts = date('Y-m-d H:i:s');
        $line = "[Sync][{$ts}] {$message}";
        echo $line . PHP_EOL;
        error_log($line);
    }
}
