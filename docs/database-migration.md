# Main to temp database migration

## What was compared

The repository SQL export at `install.done/swiftlane_database.sql` and the local development schema at `/workspace/dev-tools/packageswiftlane/schema.sql` each define 59 tables and 647 columns. Their table and column names match. The development schema was generated from that export, so this is a baseline check, not a comparison of the live main and temp databases.

The export has INSERT data for 30 tables. A trial import into an empty database with the matching schema failed in strict SQL mode because a `cdb_category.name_item` value exceeded that column's declared width. The importer rolled back every inserted row. Resolve source data that exceeds target column limits before a live migration; do not disable strict mode or accept truncation.

## Dashboard workflow

The `temp` branch enables `CDP_DB_IMPORT_ENABLED` by default in `loader.php`. A deployment can override it with `define('CDP_DB_IMPORT_ENABLED', false);` in its private `config/config.php`. In Settings > Database import, a super administrator can upload a `.sql` file (up to 128 MB), preview table/column/type mismatches, then type `IMPORT` to apply the data. The destination tables named by the import must be empty. The importer ignores schema DDL, so the temp application schema must be prepared first. It writes data in a transaction and rolls back if any INSERT fails. This supports repeat imports into a fresh or cleared temp database without silently combining old and new records.

Before moving production data, compare a current main export against the *live* temp schema using the dashboard preview. Back up temp, schedule a write freeze on main, export main with explicit INSERT columns, and import into an empty temp database. Verify row counts, user login, shipments, payments, and tracking before switching traffic. PHP needs `upload_max_filesize` of at least `128M` and `post_max_size` above `128M` (the included `.user.ini` requests `136M`). The host must honor these settings; check its PHP control panel if it does not.
