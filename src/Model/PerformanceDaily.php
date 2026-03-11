<?php

namespace App\Model;

use App\Database\Connection;
use PDO;

/**
 * Modèle pour les données pré-agrégées quotidiennes.
 *
 * Interroge la table `performance_daily` (agrégats par site/date/device/country/search_type)
 * pour des réponses instantanées sur le dashboard.
 */
class PerformanceDaily
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    /**
     * Recalcule les agrégats quotidiens pour un site et une plage de dates.
     * Appelé après chaque synchronisation.
     */
    public function recalculer(int $siteId, string $dateFrom, string $dateTo): int
    {
        $sql = "INSERT INTO performance_daily
                    (site_id, data_date, device, country, search_type,
                     query_count, page_count, clicks, impressions, position_sum, row_count)
                SELECT site_id, data_date, device, country, search_type,
                       COUNT(DISTINCT NULLIF(query, '')),
                       COUNT(DISTINCT NULLIF(page, '')),
                       SUM(clicks),
                       SUM(impressions),
                       SUM(position),
                       COUNT(*)
                FROM performance_data
                WHERE site_id = :site_id AND data_date BETWEEN :date_from AND :date_to
                GROUP BY site_id, data_date, device, country, search_type
                ON DUPLICATE KEY UPDATE
                    query_count  = VALUES(query_count),
                    page_count   = VALUES(page_count),
                    clicks       = VALUES(clicks),
                    impressions  = VALUES(impressions),
                    position_sum = VALUES(position_sum),
                    row_count    = VALUES(row_count)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'site_id'   => $siteId,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Recalcule les agrégats pour TOUS les sites (rattrapage initial).
     */
    public function recalculerTout(): int
    {
        $sql = "INSERT INTO performance_daily
                    (site_id, data_date, device, country, search_type,
                     query_count, page_count, clicks, impressions, position_sum, row_count)
                SELECT site_id, data_date, device, country, search_type,
                       COUNT(DISTINCT NULLIF(query, '')),
                       COUNT(DISTINCT NULLIF(page, '')),
                       SUM(clicks),
                       SUM(impressions),
                       SUM(position),
                       COUNT(*)
                FROM performance_data
                GROUP BY site_id, data_date, device, country, search_type
                ON DUPLICATE KEY UPDATE
                    query_count  = VALUES(query_count),
                    page_count   = VALUES(page_count),
                    clicks       = VALUES(clicks),
                    impressions  = VALUES(impressions),
                    position_sum = VALUES(position_sum),
                    row_count    = VALUES(row_count)";

        $stmt = $this->db->query($sql);

        return $stmt->rowCount();
    }

    // ------------------------------------------------------------------
    // Requêtes dashboard optimisées
    // ------------------------------------------------------------------

    /** Tendance quotidienne : clicks, impressions, CTR, position moyenne. */
    public function getDailyTrend(int $siteId, string $from, string $to, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT data_date,
                       SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions,
                       CASE WHEN SUM(impressions) > 0
                            THEN SUM(clicks) / SUM(impressions)
                            ELSE 0 END  AS ctr,
                       CASE WHEN SUM(row_count) > 0
                            THEN SUM(position_sum) / SUM(row_count)
                            ELSE 0 END  AS position
                FROM performance_daily
                WHERE {$where}
                GROUP BY data_date
                ORDER BY data_date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Répartition par appareil. */
    public function byDevice(int $siteId, string $from, string $to, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT device,
                       SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions
                FROM performance_daily
                WHERE {$where} AND device != ''
                GROUP BY device
                ORDER BY clicks DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Répartition par pays. */
    public function byCountry(int $siteId, string $from, string $to, int $limit = 20, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT country,
                       SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions
                FROM performance_daily
                WHERE {$where} AND country != ''
                GROUP BY country
                ORDER BY clicks DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Totaux agrégés pour une période. */
    public function periodTotals(int $siteId, string $from, string $to, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($siteId, $from, $to, $filters);

        $sql = "SELECT SUM(clicks)      AS clicks,
                       SUM(impressions) AS impressions,
                       CASE WHEN SUM(impressions) > 0
                            THEN SUM(clicks) / SUM(impressions)
                            ELSE 0 END  AS ctr,
                       CASE WHEN SUM(row_count) > 0
                            THEN SUM(position_sum) / SUM(row_count)
                            ELSE 0 END  AS position
                FROM performance_daily
                WHERE {$where}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0];
    }

    /** Comparaison entre deux périodes. */
    public function comparePeriods(
        int $siteId,
        string $from1, string $to1,
        string $from2, string $to2,
        array $filters = []
    ): array {
        $current  = $this->periodTotals($siteId, $from1, $to1, $filters);
        $previous = $this->periodTotals($siteId, $from2, $to2, $filters);

        return [
            'current'  => $current,
            'previous' => $previous,
            'diff'     => [
                'clicks'      => ($current['clicks'] ?? 0) - ($previous['clicks'] ?? 0),
                'impressions' => ($current['impressions'] ?? 0) - ($previous['impressions'] ?? 0),
                'ctr'         => ($current['ctr'] ?? 0) - ($previous['ctr'] ?? 0),
                'position'    => ($current['position'] ?? 0) - ($previous['position'] ?? 0),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Construit la clause WHERE pour la table performance_daily.
     * Filtres supportés : device, country, search_type.
     * Note : les filtres query/page ne s'appliquent pas ici (agrégats).
     */
    private function buildFilters(int $siteId, string $from, string $to, array $filters): array
    {
        $where  = 'site_id = :site_id AND data_date BETWEEN :from AND :to';
        $params = ['site_id' => $siteId, 'from' => $from, 'to' => $to];

        if (!empty($filters['device'])) {
            $where .= ' AND device = :device';
            $params['device'] = $filters['device'];
        }

        if (!empty($filters['country'])) {
            $where .= ' AND country = :country';
            $params['country'] = $filters['country'];
        }

        if (!empty($filters['search_type'])) {
            $where .= ' AND search_type = :search_type';
            $params['search_type'] = $filters['search_type'];
        }

        return [$where, $params];
    }
}
