<?php

// Search-Console module boot: load Composer autoloader and set env
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Set module URL prefix for internal redirects
if (!defined('MODULE_URL_PREFIX')) {
    define('MODULE_URL_PREFIX', '/m/search-console-history');
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');

// Propager les clés Google depuis le .env plateforme vers getenv()
// Si les clés sont vides côté plateforme, tenter le .env local du plugin (fallback standalone)
$clesGoogle = ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'];
$besoinFallback = false;
foreach ($clesGoogle as $cle) {
    $val = $_ENV[$cle] ?? '';
    if ($val === '') {
        $besoinFallback = true;
        break;
    }
}

if ($besoinFallback && file_exists(__DIR__ . '/.env')) {
    $dotenvLocal = Dotenv\Dotenv::createMutable(__DIR__);
    $dotenvLocal->safeLoad();
}

foreach ($clesGoogle as $cle) {
    $val = $_ENV[$cle] ?? '';
    if ($val !== '') {
        putenv("{$cle}={$val}");
    }
}

// Propager aussi les variables DB pour que le plugin utilise la même base que la plateforme
$clesDb = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
foreach ($clesDb as $cle) {
    $val = $_ENV[$cle] ?? '';
    if ($val !== '') {
        putenv("{$cle}={$val}");
    }
}

// Auto-migration : créer les tables du plugin si elles n'existent pas
\App\Database\AutoMigrate::run();
