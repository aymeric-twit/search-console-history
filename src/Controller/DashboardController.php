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

    /** Page principale : connexion GSC + dashboard si connecté. */
    public function index(): void
    {
        // Variables dashboard (vides si non connecté)
        $sites = [];
        $siteId = 0;
        $from = '';
        $to = '';
        $filters = [];

        if ($this->authenticated) {
            $sites = $this->siteModel->allActive();
            $siteId = isset($_GET['site_id']) ? (int) $_GET['site_id'] : ($sites[0]['id'] ?? 0);

            $to   = $_GET['to']   ?? date('Y-m-d', strtotime('-3 days'));
            $from = $_GET['from'] ?? date('Y-m-d', strtotime("{$to} -29 days"));

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
