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

    /** Page principale : connexion ou dashboard selon l'état d'auth. */
    public function index(): void
    {
        if (!$this->authenticated) {
            $authUrl = $this->oauth->getAuthUrl();
            require __DIR__ . '/../../templates/auth.php';
            return;
        }

        $sites = $this->siteModel->allActive();

        // Site sélectionné (par défaut le premier)
        $siteId = isset($_GET['site_id']) ? (int) $_GET['site_id'] : ($sites[0]['id'] ?? 0);
        $currentSite = $this->siteModel->find($siteId);

        // Période par défaut : 30 derniers jours
        $to   = $_GET['to']   ?? date('Y-m-d', strtotime('-3 days'));
        $from = $_GET['from'] ?? date('Y-m-d', strtotime("{$to} -29 days"));

        // Filtres — search_type par defaut 'web' pour limiter le volume de donnees
        $filters = array_filter([
            'device'      => $_GET['device']      ?? '',
            'country'     => $_GET['country']      ?? '',
            'search_type' => $_GET['search_type']  ?? 'web',
            'query'       => $_GET['filter_query'] ?? '',
            'page'        => $_GET['filter_page']  ?? '',
        ]);

        // Le dashboard charge les donnees en AJAX via /api/* pour eviter
        // de bloquer le rendu initial (les requetes sont lourdes sur 27M+ lignes)
        $dateRange = ['min_date' => null, 'max_date' => null];
        if ($siteId > 0) {
            $dateRange = $this->perfModel->dateRange($siteId);
        }

        // Variables transmises au template (les donnees arrivent en JS)
        $dailyTrend  = [];
        $topQueries  = [];
        $topPages    = [];
        $devices     = [];
        $countries   = [];
        $totals      = ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0];
        $comparison  = null;

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
