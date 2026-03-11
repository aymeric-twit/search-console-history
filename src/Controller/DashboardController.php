<?php

namespace App\Controller;

use App\Auth\GoogleOAuth;
use App\Model\PerformanceData;
use App\Model\Site;
use App\Model\SyncLog;

/**
 * Contrôleur pour l'interface web du dashboard.
 */
class DashboardController
{
    private GoogleOAuth $oauth;
    private ?PerformanceData $perfModel = null;
    private ?Site $siteModel = null;
    private ?SyncLog $syncLog = null;
    private bool $authenticated = false;

    public function __construct()
    {
        $this->oauth = new GoogleOAuth();
        $this->authenticated = $this->oauth->hasToken();

        if ($this->authenticated) {
            $this->perfModel = new PerformanceData();
            $this->siteModel = new Site();
            $this->syncLog   = new SyncLog();
        }
    }

    /** Page principale : section connexion + dashboard si connecté. */
    public function index(): void
    {
        // Vérifier si OAuth est configuré
        $oauthConfigure = !empty($_ENV['GOOGLE_CLIENT_ID']) && !empty($_ENV['GOOGLE_CLIENT_SECRET']);
        $authUrl = $oauthConfigure && !$this->authenticated ? $this->oauth->getAuthUrl() : '';

        // Variables dashboard (vides si non connecté)
        $sites = [];
        $siteId = 0;
        $from = '';
        $to = '';
        $filters = [];

        if ($this->authenticated) {
            $sites = $this->siteModel->allActive();

            // Site sélectionné (par défaut le premier)
            $siteId = isset($_GET['site_id']) ? (int) $_GET['site_id'] : ($sites[0]['id'] ?? 0);

            // Période par défaut : 30 derniers jours
            $to   = $_GET['to']   ?? date('Y-m-d', strtotime('-3 days'));
            $from = $_GET['from'] ?? date('Y-m-d', strtotime("{$to} -29 days"));

            // Filtres
            $filters = array_filter([
                'device'      => $_GET['device']      ?? '',
                'country'     => $_GET['country']      ?? '',
                'search_type' => $_GET['search_type']  ?? 'web',
                'query'       => $_GET['filter_query'] ?? '',
                'page'        => $_GET['filter_page']  ?? '',
            ]);
        }

        require __DIR__ . '/../../templates/dashboard.php';
    }

    /** Page de statut des synchronisations. */
    public function syncStatus(): void
    {
        if (!$this->authenticated) {
            $prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
            header('Location: ' . $prefix . '/');
            exit;
        }

        $sites = $this->siteModel->allActive();
        $logs = $this->syncLog->recent(100);
        require __DIR__ . '/../../templates/sync-status.php';
    }
}
