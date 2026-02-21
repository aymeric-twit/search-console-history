-- ============================================================
-- Migration : Fiabilite des donnees de synchronisation
-- ============================================================

USE search_console;

-- ------------------------------------------------------------
-- 1. Ajouter le statut 'empty' a sync_logs
-- ------------------------------------------------------------
ALTER TABLE sync_logs
    MODIFY COLUMN status ENUM('running','success','error','empty') NOT NULL DEFAULT 'running';

-- ------------------------------------------------------------
-- 2. Colonnes rows_new / rows_updated pour un comptage fiable
-- ------------------------------------------------------------
ALTER TABLE sync_logs
    ADD COLUMN rows_new     INT UNSIGNED NOT NULL DEFAULT 0 AFTER rows_inserted,
    ADD COLUMN rows_updated INT UNSIGNED NOT NULL DEFAULT 0 AFTER rows_new;

-- ------------------------------------------------------------
-- 3. Colonne log_file sur sync_jobs pour capturer la sortie
-- ------------------------------------------------------------
ALTER TABLE sync_jobs
    ADD COLUMN log_file VARCHAR(500) DEFAULT NULL AFTER pid;

-- ------------------------------------------------------------
-- 4. Nettoyage historique : les anciennes syncs avec 0 lignes
--    etaient marquees 'success' a tort → les passer en 'empty'
-- ------------------------------------------------------------
UPDATE sync_logs SET status = 'empty' WHERE status = 'success' AND rows_fetched = 0;
