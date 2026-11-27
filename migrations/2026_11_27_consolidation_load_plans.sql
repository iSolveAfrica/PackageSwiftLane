CREATE TABLE IF NOT EXISTS cdb_consolidation_load_plans (
 mode VARCHAR(3) NOT NULL,
 consolidation_id INT NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 0,
 positions_json LONGTEXT NOT NULL,
 updated_by INT NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY (mode,consolidation_id)
) ENGINE=InnoDB;
