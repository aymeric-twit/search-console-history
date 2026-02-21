-- ============================================================
-- Migration : Barre de progression pour la synchronisation
-- ============================================================

USE search_console;

-- ------------------------------------------------------------
-- Table sync_jobs — suivi global d'un job de synchronisation
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sync_jobs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED    DEFAULT NULL COMMENT 'NULL = tous les sites',
    total_tasks     INT UNSIGNED    NOT NULL DEFAULT 0,
    completed_tasks INT UNSIGNED    NOT NULL DEFAULT 0,
    status          ENUM('pending','running','success','error') NOT NULL DEFAULT 'pending',
    error_message   TEXT            DEFAULT NULL,
    pid             INT UNSIGNED    DEFAULT NULL COMMENT 'PID du process PHP',
    started_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at     DATETIME        DEFAULT NULL,

    CONSTRAINT fk_syncjob_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Colonnes supplementaires sur sync_logs pour le suivi par chunk
-- ------------------------------------------------------------
ALTER TABLE sync_logs
    ADD COLUMN job_id       INT UNSIGNED DEFAULT NULL AFTER id,
    ADD COLUMN total_chunks  INT UNSIGNED NOT NULL DEFAULT 0 AFTER rows_inserted,
    ADD COLUMN done_chunks   INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_chunks;

ALTER TABLE sync_logs
    ADD CONSTRAINT fk_synclog_job FOREIGN KEY (job_id) REFERENCES sync_jobs(id) ON DELETE SET NULL;
