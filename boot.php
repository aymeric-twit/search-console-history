<?php

// Search-Console module boot: load Composer autoloader and set env
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Set module URL prefix for internal redirects
if (!defined('MODULE_URL_PREFIX')) {
    define('MODULE_URL_PREFIX', '/m/search-console');
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');

// Propager les clés Google depuis le .env plateforme vers getenv()
// Si les clés sont vides côté plateforme, tenter le .env local du plugin (fallback standalone)
$clesGoogle = ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI'];
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
