# Main to temp database migration

## What was compared

The repository SQL export at `install.done/swiftlane_database.sql` and the local development schema at `/workspace/dev-tools/packageswiftlane/schema.sql` each define 59 tables and 647 columns. Their table and column names match. The development schema was generated from that export, so this is a baseline check, not a comparison of the live main and temp databases.

The export has INSERT data for 30 tables. A trial import into an empty database with the matching schema failed in strict SQL mode because a `cdb_category.name_item` value exceeded that column's declared width. The importer rolled back every inserted row. Resolve source data that exceeds target column limits before a live migration; do not disable strict mode or accept truncation.

## Dashboard workflow

Set `define('CDP_DB_IMPORT_ENABLED', true);` in temp's gitignored `config/config.php`. Leave it unset on main. In Settings > Database import, a super administrator can then upload a `.sql` file (up to 64 MB), preview table/column/type mismatches, then type `IMPORT` to apply the data. The destination tables named by the import must be empty. The importer ignores schema DDL, so the temp application schema must be prepared first. It writes data in a transaction and rolls back if any INSERT fails. This supports repeat imports into a fresh or cleared temp database without silently combining old and new records.

Before moving production data, compare a current main export against the *live* temp schema using the dashboard preview. Back up temp, schedule a write freeze on main, export main with explicit INSERT columns, and import into an empty temp database. Verify row counts, user login, shipments, payments, and tracking before switching traffic. The app's PHP `upload_max_filesize` and `post_max_size` must both allow the chosen export size.
