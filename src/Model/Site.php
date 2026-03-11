<?php

namespace App\Model;

use App\Database\Connection;
use PDO;

/**
 * Modèle pour les sc_sitesSearch Console enregistrés en base.
 */
class Site
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::get();
    }

    /** Retourne tous les sc_sitesactifs. */
    public function allActive(): array
    {
        return $this->db->query(
            'SELECT * FROM sc_sitesWHERE active = 1 ORDER BY site_url'
        )->fetchAll();
    }

    /** Retourne un site par ID. */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sc_sitesWHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Retourne un site par URL. */
    public function findByUrl(string $url): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sc_sitesWHERE site_url = :url');
        $stmt->execute(['url' => $url]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Insère ou met à jour un site (upsert par site_url).
     * Retourne l'ID du site.
     */
    public function upsert(string $siteUrl, ?string $label = null): int
    {
        $existing = $this->findByUrl($siteUrl);

        if ($existing) {
            if ($label !== null) {
                $stmt = $this->db->prepare('UPDATE sc_sitesSET label = :label WHERE id = :id');
                $stmt->execute(['label' => $label, 'id' => $existing['id']]);
            }
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO sc_sites (site_url, label) VALUES (:url, :label)'
        );
        $stmt->execute(['url' => $siteUrl, 'label' => $label ?? $siteUrl]);

        return (int) $this->db->lastInsertId();
    }
}
