<?php

namespace App\Model;

use App\Auth\UserContext;
use App\Database\Connection;
use PDO;

/**
 * Suivi global d'un job de synchronisation.
 * Filtré par user_id pour l'isolation multi-utilisateur.
 *
 * Les méthodes de mutation (start, advanceTask, success, error, setPid, setLogFile)
 * ne filtrent PAS par user_id car elles sont appelées depuis bin/sync.php
 * (CLI, sans contexte utilisateur). Le job est déjà lié à un user_id à la création.
 */
class SyncJob
{
    private PDO $db;
    private int $userId;

    public function __construct(?int $userId = null)
    {
        $this->db = Connection::get();
        $this->userId = $userId ?? UserContext::id();
    }

    /** Crée un job pending pour l'utilisateur courant. Retourne l'ID du job. */
    public function create(?int $siteId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sc_sync_jobs (user_id, site_id, status, started_at)
             VALUES (:uid, :site_id, "pending", NOW())'
        );
        $stmt->execute(['uid' => $this->userId, 'site_id' => $siteId]);

        return (int) $this->db->lastInsertId();
    }

    /** Passe le job en running et enregistre le nombre total de tâches. */
    public function start(int $jobId, int $totalTasks): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sc_sync_jobs
             SET status = "running", total_tasks = :total
             WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId, 'total' => $totalTasks]);
    }

    /** Incrémente completed_tasks de 1. */
    public function advanceTask(int $jobId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sc_sync_jobs
             SET completed_tasks = completed_tasks + 1
             WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId]);
    }

    /** Marque le job comme réussi. */
    public function success(int $jobId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sc_sync_jobs
             SET status = "success", finished_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId]);
    }

    /** Marque le job comme échoué. */
    public function error(int $jobId, string $msg): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sc_sync_jobs
             SET status = "error", error_message = :msg, finished_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId, 'msg' => mb_substr($msg, 0, 5000)]);
    }

    /** Retourne un job par son ID (vérifie l'ownership en contexte web). */
    public function find(int $jobId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sc_sync_jobs WHERE id = :id');
        $stmt->execute(['id' => $jobId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Retourne le job en cours de l'utilisateur courant. */
    public function findRunning(): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM sc_sync_jobs
             WHERE status IN ("pending","running") AND user_id = :uid
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['uid' => $this->userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Enregistre le PID du process PHP. */
    public function setPid(int $jobId, int $pid): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sc_sync_jobs SET pid = :pid WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId, 'pid' => $pid]);
    }

    /** Enregistre le chemin du fichier log du process. */
    public function setLogFile(int $jobId, string $path): void
    {
        $stmt = $this->db->prepare(
            'UPDATE sc_sync_jobs SET log_file = :path WHERE id = :id'
        );
        $stmt->execute(['id' => $jobId, 'path' => $path]);
    }
}
