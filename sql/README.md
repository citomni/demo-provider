# sql/

Database schema files of the package for MySQL and MariaDB. The package's Repositories own every query that runs against these tables; this folder holds only the definitions. The app imports them into its own database, next to its own tables and those of other packages.

## Layout

- `sql/<package>.sql`: the complete schema, executable on an empty database and named after the package: the vendor, an underscore and the package name, like `citomni_demo-provider.sql`. A fresh install imports only this file.
- `sql/migrations/YYYYMMDD_NN_<name>.sql`: one migration per change to a released schema, applied in name order by apps that installed an earlier release. When a migration is added, the complete schema file is updated to match. A package without a release that changed its schema has no migrations folder.

## Rules

- InnoDB and `utf8mb4`, with `utf8mb4_unicode_ci` for text.
- Machine values compare byte by byte, apart from trailing spaces: Hex hashes, tokens and UUIDs are `CHARACTER SET ascii COLLATE ascii_bin`, like `jobrun_jobs.worker_token_hash`, and `ascii_bin` is a PAD SPACE collation, so `'abc '` equals `'abc'` in a comparison. JSON is `longtext` with `utf8mb4_bin` and a `json_valid()` check, like `jobrun_jobs.payload_json`.
- A status or other code is preferably text: `varchar` in `ascii_bin`, with a check constraint that compares the bytes with the cases of a string-backed enum, `CHECK (CAST(level AS BINARY) IN ('info', 'warning'))`, like `demoprov_notices.level` and `NoticeLevel`. The stored value then means the same in SQL, dumps and logs as in code. Without the `CAST`, a value with a trailing space passes the check; a MariaDB dump writes the expression as `cast(level as char charset binary)`, which MySQL reads too. A numeric code is allowed, as `tinyint unsigned` with a check constraint and an int-backed enum, for example for a flag that never gets a third state or to match an existing schema. Either way, a code is compared with named cases, never with a range such as `status > 0`.
- snake_case names. Every table carries the package prefix, so it never collides with the app or another package; this package uses `demoprov_`.
- `bigint unsigned` surrogate ids when a surrogate key fits; natural keys are allowed.
- Name every index, unique key, foreign key and check constraint explicitly, with its kind and table first: `ix_demoprov_notices_window`, `chk_demoprov_notices_level`, `uq_jobrun_jobs_job_uuid`, `fk_auth_audit_identity`.
- No foreign keys to tables of the app or of another package unless that package is a requirement and documents the table as stable.
- A table whose rows are entities or events carries `created_at datetime NOT NULL DEFAULT current_timestamp()`, also when no code reads it yet, because the moment a row was created cannot be recovered later.
- Design only the requested schema. Constraints, foreign keys, unique keys, indexes, other timestamps and lifecycle fields only when the requirements justify them. No invented tables, columns, audit fields, soft deletion or denormalization.
- Existing schemas are compatibility context, not templates to copy. Match their conventions where compatibility matters.
- No seed data unless the task asks for it.
- When a material domain rule or relationship is unclear, stop and ask instead of guessing.

## Example

[`citomni_demo-provider.sql`](citomni_demo-provider.sql): the `demoprov_notices` table of the site notices feature. One check constraint limits `level` to exactly the cases of `NoticeLevel`, another keeps `ends_at` from lying before `starts_at`. The index on `ends_at` and `starts_at` serves the queries of `NoticeRepository`, which all filter on notices that have not ended. `created_at` records when a notice was scheduled.

[`migrations/20261020_01_notices_level_check.sql`](migrations/20261020_01_notices_level_check.sql) brings a database from release v1.0.0.1 to the byte comparison of the level check.
