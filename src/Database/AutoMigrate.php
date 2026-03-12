<?php

namespace App\Database;

use PDO;

/**
 * Auto-migration : crée les tables du plugin si elles n'existent pas.
 * Gère aussi les migrations incrémentales (ajout de colonnes).
 * Appelé à chaque chargement via boot.php.
 */
class AutoMigrate
{
    /** Vérifie et crée les tables manquantes, puis lance les migrations. */
    public static function run(): void
    {
        $db = Connection::get();

        // Vérifier si la table principale existe déjà
        $check = $db->query("SHOW TABLES LIKE 'sc_sites'");
        if ($check->rowCount() === 0) {
            // Première installation : créer toutes les tables
            $db->exec(self::getSchema());
        }

        // Migrations incrémentales
        self::runMigrations($db);
    }

    /** Migrations incrémentales : ajout de colonnes manquantes. */
    private static function runMigrations(PDO $db): void
    {
        // Migration : ajout user_id à sc_oauth_tokens
        if (!self::columnExists($db, 'sc_oauth_tokens', 'user_id')) {
            $db->exec("ALTER TABLE sc_oauth_tokens ADD COLUMN user_id INT UNSIGNED NOT NULL DEFAULT 0");
            $db->exec("CREATE INDEX idx_sc_oauth_user ON sc_oauth_tokens (user_id)");
        }

        // Migration : ajout user_id à sc_sites
        if (!self::columnExists($db, 'sc_sites', 'user_id')) {
            $db->exec("ALTER TABLE sc_sites ADD COLUMN user_id INT UNSIGNED NOT NULL DEFAULT 0");
            $db->exec("CREATE INDEX idx_sc_sites_user ON sc_sites (user_id)");
            // Mettre à jour la contrainte unique : un même site_url peut exister pour différents users
            $db->exec("ALTER TABLE sc_sites DROP INDEX uq_sc_site_url");
            $db->exec("ALTER TABLE sc_sites ADD UNIQUE KEY uq_sc_site_user (site_url, user_id)");
        }

        // Migration : ajout user_id à sc_sync_jobs
        if (!self::columnExists($db, 'sc_sync_jobs', 'user_id')) {
            $db->exec("ALTER TABLE sc_sync_jobs ADD COLUMN user_id INT UNSIGNED NOT NULL DEFAULT 0");
            $db->exec("CREATE INDEX idx_sc_syncjobs_user ON sc_sync_jobs (user_id)");
        }
    }

    /** Vérifie si une colonne existe dans une table. */
    private static function columnExists(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :col");
        $stmt->execute(['col' => $column]);
        return $stmt->rowCount() > 0;
    }

    private static function getSchema(): string
    {
        return "
            -- Sites enregistrés dans Search Console
            CREATE TABLE IF NOT EXISTS sc_sites (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id     INT UNSIGNED  NOT NULL DEFAULT 0,
                site_url    VARCHAR(500)  NOT NULL,
                label       VARCHAR(255)  DEFAULT NULL,
                active      TINYINT(1)    NOT NULL DEFAULT 1,
                created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sc_site_user (site_url, user_id),
                INDEX idx_sc_sites_user (user_id)
            ) ENGINE=InnoDB;

            -- Tokens OAuth2
            CREATE TABLE IF NOT EXISTS sc_oauth_tokens (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id         INT UNSIGNED  NOT NULL DEFAULT 0,
                access_token    TEXT          NOT NULL,
                refresh_token   TEXT          DEFAULT NULL,
                token_type      VARCHAR(50)   DEFAULT 'Bearer',
                expires_at      DATETIME      NOT NULL,
                scope           TEXT          DEFAULT NULL,
                created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_sc_oauth_user (user_id)
            ) ENGINE=InnoDB;

            -- Données de performance Search Console
            CREATE TABLE IF NOT EXISTS sc_performance_data (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                site_id         INT UNSIGNED    NOT NULL,
                data_date       DATE            NOT NULL,
                page            VARCHAR(2048)   NOT NULL DEFAULT '',
                query           VARCHAR(500)    NOT NULL DEFAULT '',
                country         CHAR(3)         NOT NULL DEFAULT '',
                device          VARCHAR(20)     NOT NULL DEFAULT '',
                search_type     VARCHAR(20)     NOT NULL DEFAULT 'web',
                clicks          INT UNSIGNED    NOT NULL DEFAULT 0,
                impressions     INT UNSIGNED    NOT NULL DEFAULT 0,
                ctr             DECIMAL(8,6)    NOT NULL DEFAULT 0,
                position        DECIMAL(8,2)    NOT NULL DEFAULT 0,
                created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_sc_perf_site FOREIGN KEY (site_id) REFERENCES sc_sites(id) ON DELETE CASCADE,
                UNIQUE KEY uq_sc_perf_row (site_id, data_date, search_type, country, device, query(255), page(255))
            ) ENGINE=InnoDB;

            -- Index performance_data
            CREATE INDEX idx_sc_perf_date      ON sc_performance_data (data_date);
            CREATE INDEX idx_sc_perf_site_date ON sc_performance_data (site_id, data_date);
            CREATE INDEX idx_sc_perf_query     ON sc_performance_data (query(100));
            CREATE INDEX idx_sc_perf_page      ON sc_performance_data (page(255));
            CREATE INDEX idx_sc_perf_site_type ON sc_performance_data (site_id, search_type, data_date);

            -- Agrégats quotidiens pré-calculés
            CREATE TABLE IF NOT EXISTS sc_performance_daily (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                site_id         INT UNSIGNED    NOT NULL,
                data_date       DATE            NOT NULL,
                device          VARCHAR(20)     NOT NULL DEFAULT '',
                country         CHAR(3)         NOT NULL DEFAULT '',
                search_type     VARCHAR(20)     NOT NULL DEFAULT 'web',
                query_count     INT UNSIGNED    NOT NULL DEFAULT 0,
                page_count      INT UNSIGNED    NOT NULL DEFAULT 0,
                clicks          INT UNSIGNED    NOT NULL DEFAULT 0,
                impressions     INT UNSIGNED    NOT NULL DEFAULT 0,
                position_sum    DECIMAL(12,2)   NOT NULL DEFAULT 0,
                row_count       INT UNSIGNED    NOT NULL DEFAULT 0,
                CONSTRAINT fk_sc_daily_site FOREIGN KEY (site_id) REFERENCES sc_sites(id) ON DELETE CASCADE,
                UNIQUE KEY uq_sc_daily (site_id, data_date, device, country, search_type)
            ) ENGINE=InnoDB;

            CREATE INDEX idx_sc_daily_site_date ON sc_performance_daily (site_id, data_date, search_type);

            -- Journal de synchronisation
            CREATE TABLE IF NOT EXISTS sc_sync_logs (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                job_id          INT UNSIGNED    DEFAULT NULL,
                site_id         INT UNSIGNED    NOT NULL,
                search_type     VARCHAR(20)     NOT NULL DEFAULT 'web',
                date_from       DATE            NOT NULL,
                date_to         DATE            NOT NULL,
                rows_fetched    INT UNSIGNED    NOT NULL DEFAULT 0,
                rows_inserted   INT UNSIGNED    NOT NULL DEFAULT 0,
                total_chunks    INT UNSIGNED    NOT NULL DEFAULT 0,
                done_chunks     INT UNSIGNED    NOT NULL DEFAULT 0,
                rows_new        INT UNSIGNED    NOT NULL DEFAULT 0,
                rows_updated    INT UNSIGNED    NOT NULL DEFAULT 0,
                status          ENUM('running','success','error','empty') NOT NULL DEFAULT 'running',
                error_message   TEXT            DEFAULT NULL,
                duration_sec    DECIMAL(8,2)    DEFAULT NULL,
                started_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                finished_at     DATETIME        DEFAULT NULL,
                CONSTRAINT fk_sc_sync_site FOREIGN KEY (site_id) REFERENCES sc_sites(id) ON DELETE CASCADE
            ) ENGINE=InnoDB;

            CREATE INDEX idx_sc_sync_site_date ON sc_sync_logs (site_id, date_from, date_to);

            -- Jobs de synchronisation
            CREATE TABLE IF NOT EXISTS sc_sync_jobs (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id         INT UNSIGNED    NOT NULL DEFAULT 0,
                site_id         INT UNSIGNED    DEFAULT NULL,
                total_tasks     INT UNSIGNED    NOT NULL DEFAULT 0,
                completed_tasks INT UNSIGNED    NOT NULL DEFAULT 0,
                status          ENUM('pending','running','success','error') NOT NULL DEFAULT 'pending',
                error_message   TEXT            DEFAULT NULL,
                pid             INT UNSIGNED    DEFAULT NULL,
                log_file        VARCHAR(500)    DEFAULT NULL,
                started_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                finished_at     DATETIME        DEFAULT NULL,
                CONSTRAINT fk_sc_syncjob_site FOREIGN KEY (site_id) REFERENCES sc_sites(id) ON DELETE SET NULL,
                INDEX idx_sc_syncjobs_user (user_id)
            ) ENGINE=InnoDB;

            -- FK sync_logs → sync_jobs
            ALTER TABLE sc_sync_logs
                ADD CONSTRAINT fk_sc_synclog_job FOREIGN KEY (job_id) REFERENCES sc_sync_jobs(id) ON DELETE SET NULL;
        ";
    }
}
