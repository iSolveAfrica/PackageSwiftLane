-- Developer console: GitHub (read-only cache), deployment records and database deploy jobs.
-- Used by helpers/dev_github.php (sl_ghEnsureTables creates the same tables on first use, so this
-- file is optional on hosts where the app user may CREATE TABLE). Idempotent.
--   cdb_dev_gh_commits      commits of the tracked branch (change log source)
--   cdb_dev_gh_runs         GitHub Actions workflow runs (+ jobs/steps JSON when fetched)
--   cdb_dev_gh_releases     releases / tags
--   cdb_dev_gh_pulls        recent pull requests
--   cdb_dev_gh_deployments  GitHub deployments (environment, latest status)
--   cdb_dev_deploy_records  deployments recorded by hand on the Deployments page
--   cdb_dev_db_jobs         database backups / uploads / imports with their step log
-- Settings (repository, API base, encrypted token) live in cdb_dev_state under key 'github'.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_gh_commits (
    sha          CHAR(40) NOT NULL,
    branch       VARCHAR(120) NULL,
    title        VARCHAR(255) NOT NULL,
    body         TEXT NULL,
    ctype        VARCHAR(20) NOT NULL DEFAULT 'other',
    cscope       VARCHAR(60) NULL,
    breaking     TINYINT NOT NULL DEFAULT 0,
    is_merge     TINYINT NOT NULL DEFAULT 0,
    author       VARCHAR(120) NULL,
    author_login VARCHAR(120) NULL,
    committed_at DATETIME NULL,
    url          VARCHAR(255) NULL,
    synced_at    DATETIME NOT NULL,
    PRIMARY KEY (sha),
    KEY idx_when (committed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_gh_runs (
    id            BIGINT NOT NULL,
    workflow_id   BIGINT NULL,
    workflow_name VARCHAR(160) NULL,
    title         VARCHAR(255) NULL,
    event         VARCHAR(40) NULL,
    branch        VARCHAR(160) NULL,
    head_sha      CHAR(40) NULL,
    status        VARCHAR(20) NULL,
    conclusion    VARCHAR(20) NULL,
    run_number    INT NULL,
    run_attempt   INT NULL,
    actor         VARCHAR(120) NULL,
    created_at    DATETIME NULL,
    started_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    duration_s    INT NULL,
    url           VARCHAR(255) NULL,
    jobs_json     MEDIUMTEXT NULL,
    jobs_at       DATETIME NULL,
    synced_at     DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_created (created_at),
    KEY idx_branch (branch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_gh_releases (
    id           BIGINT NOT NULL,
    tag          VARCHAR(120) NOT NULL,
    name         VARCHAR(255) NULL,
    target       VARCHAR(160) NULL,
    draft        TINYINT NOT NULL DEFAULT 0,
    prerelease   TINYINT NOT NULL DEFAULT 0,
    author       VARCHAR(120) NULL,
    published_at DATETIME NULL,
    url          VARCHAR(255) NULL,
    body         TEXT NULL,
    synced_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_pub (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_gh_pulls (
    number     INT NOT NULL,
    title      VARCHAR(255) NOT NULL,
    state      VARCHAR(12) NOT NULL,
    draft      TINYINT NOT NULL DEFAULT 0,
    author     VARCHAR(120) NULL,
    head_ref   VARCHAR(160) NULL,
    base_ref   VARCHAR(160) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    merged_at  DATETIME NULL,
    closed_at  DATETIME NULL,
    url        VARCHAR(255) NULL,
    synced_at  DATETIME NOT NULL,
    PRIMARY KEY (number),
    KEY idx_upd (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_gh_deployments (
    id          BIGINT NOT NULL,
    environment VARCHAR(80) NULL,
    ref         VARCHAR(160) NULL,
    sha         CHAR(40) NULL,
    task        VARCHAR(60) NULL,
    creator     VARCHAR(120) NULL,
    created_at  DATETIME NULL,
    state       VARCHAR(20) NULL,
    state_at    DATETIME NULL,
    url         VARCHAR(255) NULL,
    synced_at   DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sha (sha),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_deploy_records (
    id          INT NOT NULL AUTO_INCREMENT,
    environment VARCHAR(40) NOT NULL DEFAULT 'production',
    version     VARCHAR(60) NULL,
    commit_sha  VARCHAR(40) NULL,
    branch      VARCHAR(120) NULL,
    status      VARCHAR(12) NOT NULL DEFAULT 'success',
    duration_s  INT NULL,
    notes       VARCHAR(500) NULL,
    recorded_by INT NULL,
    deployed_at DATETIME NOT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_when (deployed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_db_jobs (
    id          INT NOT NULL AUTO_INCREMENT,
    kind        VARCHAR(12) NOT NULL,
    status      VARCHAR(12) NOT NULL,
    mode        VARCHAR(12) NULL,
    file_name   VARCHAR(190) NULL,
    stored_name VARCHAR(120) NULL,
    file_bytes  BIGINT NULL,
    sha256      CHAR(64) NULL,
    tables_n    INT NULL,
    backup_file VARCHAR(190) NULL,
    log         MEDIUMTEXT NULL,
    user_id     INT NULL,
    started_at  DATETIME NOT NULL,
    finished_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO cdb_schema_migrations (name, applied_at) VALUES ('2026_11_26_dev_github', NOW());
SELECT 'ok: 2026_11_26_dev_github' AS result;
