#!/usr/bin/env php
<?php

/**
 * Script CLI de synchronisation des données Search Console.
 *
 * Usage :
 *   php bin/sync.php                 # Sync tous les sites, tous les types
 *   php bin/sync.php --site-id=3     # Sync uniquement le site ID 3
 *   php bin/sync.php --no-import     # Ne pas ré-importer la liste des sites
 *   php bin/sync.php --job-id=42     # Rattacher a un sync_job existant
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');

// Parse des arguments CLI
$options = getopt('', ['site-id::', 'no-import', 'job-id::']);
$siteId      = isset($options['site-id']) ? (int) $options['site-id'] : null;
$jobId       = isset($options['job-id'])  ? (int) $options['job-id']  : null;
$importSites = !isset($options['no-import']);

echo "========================================\n";
echo " Search Console — Synchronisation\n";
echo " " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    $controller = new \App\Controller\SyncController();

    // Rattacher a un job existant si --job-id fourni
    if ($jobId) {
        $controller->setJobId($jobId);
        // Enregistrer le PID pour la detection de crash
        $syncJob = new \App\Model\SyncJob();
        $syncJob->setPid($jobId, getmypid());
    }

    $results = $controller->run($importSites, $siteId);

    echo "\n========================================\n";
    echo " Résumé\n";
    echo "========================================\n";

    $hasError = false;
    foreach ($results as $r) {
        $statusMap = ['success' => 'OK', 'up_to_date' => 'A JOUR', 'empty' => 'VIDE', 'error' => 'ERREUR'];
        $status = $statusMap[$r['status']] ?? 'ERREUR';
        echo sprintf(
            " [%s] %s (%s) — %s lignes en %ss\n",
            $status,
            $r['site'] ?? '?',
            $r['search_type'] ?? '?',
            $r['rows_fetched'] ?? 0,
            $r['duration'] ?? 0
        );

        if ($r['status'] === 'error') {
            $hasError = true;
            echo "   -> " . ($r['error'] ?? 'Erreur inconnue') . "\n";
        }
    }

    echo "\nTerminé.\n";
    exit($hasError ? 1 : 0);

} catch (\Throwable $e) {
    // Marquer le job en erreur si applicable
    if ($jobId) {
        try {
            $syncJob = $syncJob ?? new \App\Model\SyncJob();
            $syncJob->error($jobId, $e->getMessage());
        } catch (\Throwable $ignored) {
        }
    }

    echo "\nERREUR FATALE : " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(2);
}
