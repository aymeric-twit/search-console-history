#!/usr/bin/env php
<?php

/**
 * Script CLI de rattrapage : recalcule les agrégats quotidiens
 * pour toutes les données existantes dans performance_data.
 *
 * À lancer une fois après la migration performance_daily :
 *   php bin/aggregate.php
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/..');
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');

echo "========================================\n";
echo " Recalcul des agrégats quotidiens\n";
echo " " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    $daily = new \App\Model\PerformanceDaily();

    $start = microtime(true);
    $rows = $daily->recalculerTout();
    $duration = round(microtime(true) - $start, 2);

    echo "Terminé : {$rows} agrégats insérés/mis à jour en {$duration}s.\n";
    exit(0);

} catch (\Throwable $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
