<?php

namespace App\Model;

use App\Database\Connection;
use PDO;

/**
 * Journal de synchronisation.
 * Trace chaque exécution de sync (début, fin, statut, erreurs).
 */
class SyncLog
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    /** Démarre un nouvel enregistrement de sync. */
    public function start(int $siteId, string $searchType, string $dateFrom, string $dateTo, ?int $jobId = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sync_logs (job_id, site_id, search_type, date_from, date_to, status, started_at)
             VALUES (:job_id, :site_id, :search_type, :date_from, :date_to, "running", NOW())'
        );

        $stmt->execute([
            'job_id'      => $jobId,
            'site_id'     => $siteId,
            'search_type' => $searchType,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Ecrit le nombre total de chunks pour un log. */
    public function setChunks(int $logId, int $total): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_logs SET total_chunks = :total, done_chunks = 0 WHERE id = :id'
        );
        $stmt->execute(['id' => $logId, 'total' => $total]);
    }

    /** Incremente done_chunks de 1. */
    public function advanceChunk(int $logId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_logs SET done_chunks = done_chunks + 1 WHERE id = :id'
        );
        $stmt->execute(['id' => $logId]);
    }

    /** Tache en cours (running) pour un job, avec site_url. */
    public function findRunningByJob(int $jobId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT sl.*, s.site_url
             FROM sync_logs sl
             JOIN sites s ON s.id = sl.site_id
             WHERE sl.job_id = :job_id AND sl.status = "running"
             ORDER BY sl.id DESC LIMIT 1'
        );
        $stmt->execute(['job_id' => $jobId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Taches terminees (success/error) pour un job, avec site_url. */
    public function completedByJob(int $jobId): array
    {
        $stmt = $this->db->prepare(
            'SELECT sl.*, s.site_url
             FROM sync_logs sl
             JOIN sites s ON s.id = sl.site_id
             WHERE sl.job_id = :job_id AND sl.status IN ("success","error","empty")
             ORDER BY sl.id ASC'
        );
        $stmt->execute(['job_id' => $jobId]);

        return $stmt->fetchAll();
    }

    /** Marque une sync comme réussie. */
    public function success(
        int $logId,
        int $rowsFetched,
        int $rowsInserted,
        float $duration,
        int $rowsNew = 0,
        int $rowsUpdated = 0,
        ?string $effectiveDateTo = null
    ): void {
        $sql = 'UPDATE sync_logs
                SET status = "success", rows_fetched = :fetched, rows_inserted = :inserted,
                    rows_new = :rows_new, rows_updated = :rows_updated,
                    duration_sec = :duration, finished_at = NOW()';

        $params = [
            'id'           => $logId,
            'fetched'      => $rowsFetched,
            'inserted'     => $rowsInserted,
            'rows_new'     => $rowsNew,
            'rows_updated' => $rowsUpdated,
            'duration'     => $duration,
        ];

        if ($effectiveDateTo !== null) {
            $sql .= ', date_to = :effective_date_to';
            $params['effective_date_to'] = $effectiveDateTo;
        }

        $sql .= ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /** Marque une sync comme vide (0 lignes retournees par l'API). */
    public function markEmpty(int $logId, float $duration): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_logs
             SET status = "empty", rows_fetched = 0, rows_inserted = 0,
                 rows_new = 0, rows_updated = 0,
                 duration_sec = :duration, finished_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'id'       => $logId,
            'duration' => $duration,
        ]);
    }

    /** Marque une sync comme échouée. */
    public function error(int $logId, string $message, float $duration): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_logs
             SET status = "error", error_message = :msg, duration_sec = :duration, finished_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'id'       => $logId,
            'msg'      => mb_substr($message, 0, 5000),
            'duration' => $duration,
        ]);
    }

    /** Derniers logs de synchronisation. */
    public function recent(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT sl.*, s.site_url
             FROM sync_logs sl
             JOIN sites s ON s.id = sl.site_id
             ORDER BY sl.id DESC
             LIMIT :lim'
        );
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Dernière sync réussie pour un site et type. */
    public function lastSuccess(int $siteId, string $searchType): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM sync_logs
             WHERE site_id = :site_id AND search_type = :st AND status = "success"
             ORDER BY date_to DESC LIMIT 1'
        );
        $stmt->execute(['site_id' => $siteId, 'st' => $searchType]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
