-- ============================================================
-- Migration : Table de pré-agrégation quotidienne
-- Accélère les requêtes dashboard (trend, devices, countries, totals)
-- en évitant de ré-agréger les millions de lignes brutes.
-- ============================================================

CREATE TABLE IF NOT EXISTS performance_daily (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED    NOT NULL,
    data_date       DATE            NOT NULL,
    device          VARCHAR(20)     NOT NULL DEFAULT '',
    country         CHAR(3)         NOT NULL DEFAULT '',
    search_type     VARCHAR(20)     NOT NULL DEFAULT 'web',
    query_count     INT UNSIGNED    NOT NULL DEFAULT 0   COMMENT 'Nombre de requêtes distinctes',
    page_count      INT UNSIGNED    NOT NULL DEFAULT 0   COMMENT 'Nombre de pages distinctes',
    clicks          INT UNSIGNED    NOT NULL DEFAULT 0,
    impressions     INT UNSIGNED    NOT NULL DEFAULT 0,
    position_sum    DECIMAL(12,2)   NOT NULL DEFAULT 0   COMMENT 'Somme des positions (pour moyenne pondérée)',
    row_count       INT UNSIGNED    NOT NULL DEFAULT 0   COMMENT 'Nombre de lignes sources agrégées',

    CONSTRAINT fk_daily_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uq_daily (site_id, data_date, device, country, search_type)
) ENGINE=InnoDB;

CREATE INDEX idx_daily_site_date ON performance_daily (site_id, data_date, search_type);

-- Index composite pour accélérer topQueries et topPages sur la table brute
CREATE INDEX idx_perf_site_type_date ON performance_data (site_id, search_type, data_date);
