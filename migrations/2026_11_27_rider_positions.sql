CREATE TABLE IF NOT EXISTS cdb_rider_positions (
 driver_id INT NOT NULL PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 latitude DECIMAL(10,7) NOT NULL,
 longitude DECIMAL(10,7) NOT NULL,
 recorded_at DATETIME NOT NULL,
 INDEX (request_id)
) ENGINE=InnoDB;
