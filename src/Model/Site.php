<?php

namespace App\Model;

use App\Auth\UserContext;
use App\Database\Connection;
use PDO;

/**
 * Modèle pour les sites Search Console enregistrés en base.
 * Filtré par user_id pour l'isolation multi-utilisateur.
 */
class Site
{
    private PDO $db;
    private int $userId;

    public function __construct(?int $userId = null)
    {
        $this->db = Connection::get();
        $this->userId = $userId ?? UserContext::id();
    }

    /** Retourne tous les sites actifs de l'utilisateur courant. */
    public function allActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM sc_sites WHERE active = 1 AND user_id = :uid ORDER BY site_url'
        );
        $stmt->execute(['uid' => $this->userId]);
        return $stmt->fetchAll();
    }

    /** Retourne un site par ID (vérifie l'ownership). */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sc_sites WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $this->userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Retourne un site par URL pour l'utilisateur courant. */
    public function findByUrl(string $url): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sc_sites WHERE site_url = :url AND user_id = :uid');
        $stmt->execute(['url' => $url, 'uid' => $this->userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Insère ou met à jour un site (upsert par site_url + user_id).
     * Retourne l'ID du site.
     */
    public function upsert(string $siteUrl, ?string $label = null): int
    {
        $existing = $this->findByUrl($siteUrl);

        if ($existing) {
            if ($label !== null) {
                $stmt = $this->db->prepare('UPDATE sc_sites SET label = :label WHERE id = :id AND user_id = :uid');
                $stmt->execute(['label' => $label, 'id' => $existing['id'], 'uid' => $this->userId]);
            }
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO sc_sites (user_id, site_url, label) VALUES (:uid, :url, :label)'
        );
        $stmt->execute(['uid' => $this->userId, 'url' => $siteUrl, 'label' => $label ?? $siteUrl]);

        return (int) $this->db->lastInsertId();
    }
}
