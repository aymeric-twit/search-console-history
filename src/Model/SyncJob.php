<?php

namespace App\Model;

use App\Database\Connection;
use PDO;

/**
 * Suivi global d'un job de synchronisation.
 */
class SyncJob
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    /** Cree un job pending. Retourne l'ID du job. */
    public function create(?int $siteId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sync_jobs (site_id, status, started_at)
             VALUES (:site_id, "pending", NOW())'
        );
        $stmt->execute(['site_id' => $siteId]);

        return (int) $this->db->lastInsertId();
    }

    /** Passe le job en running et enregistre le nombre total de taches. */
    public function start(int $jobId, int $totalTasks): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_jobs
             SET status = "running", total_tasks = :total
             WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId, 'total' => $totalTasks]);
    }

    /** Incremente completed_tasks de 1. */
    public function advanceTask(int $jobId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_jobs
             SET completed_tasks = completed_tasks + 1
             WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId]);
    }

    /** Marque le job comme reussi. */
    public function success(int $jobId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_jobs
             SET status = "success", finished_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId]);
    }

    /** Marque le job comme echoue. */
    public function error(int $jobId, string $msg): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_jobs
             SET status = "error", error_message = :msg, finished_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId, 'msg' => mb_substr($msg, 0, 5000)]);
    }

    /** Retourne un job par son ID. */
    public function find(int $jobId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sync_jobs WHERE id = :id');
        $stmt->execute(['id' => $jobId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Retourne le job en cours (running ou pending), s'il existe. */
    public function findRunning(): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM sync_jobs
             WHERE status IN ("pending","running")
             ORDER BY id DESC LIMIT 1'
        );
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Enregistre le PID du process PHP. */
    public function setPid(int $jobId, int $pid): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_jobs SET pid = :pid WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId, 'pid' => $pid]);
    }

    /** Enregistre le chemin du fichier log du process. */
    public function setLogFile(int $jobId, string $path): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sync_jobs SET log_file = :path WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId, 'path' => $path]);
    }
}
