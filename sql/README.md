# sql/

Database schema files of the package for MySQL and MariaDB. The package's Repositories own every query that runs against these tables; this folder holds only the definitions. The app imports them into its own database, next to its own tables and those of other packages.

## Layout

- `sql/<package>.sql`: the complete schema, executable on an empty database and named after the package: the vendor, an underscore and the package name, like `citomni_demo-provider.sql`. A fresh install imports only this file.
- `sql/migrations/YYYYMMDD_NN_<name>.sql`: one migration per change to a released schema, applied in name order by apps that installed an earlier release. When a migration is added, the complete schema file is updated to match. A package without a release that changed its schema has no migrations folder.

## Rules

- InnoDB and `utf8mb4`, with `utf8mb4_unicode_ci` for text.
- Machine values compare byte by byte. Hex hashes, tokens, UUIDs and status codes are `CHARACTER SET ascii COLLATE ascii_bin`, like `demoprov_notices.level` and `jobrun_jobs.worker_token_hash`. JSON is `longtext` with `utf8mb4_bin` and a `json_valid()` check, like `jobrun_jobs.payload_json`.
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

[`citomni_demo-provider.sql`](citomni_demo-provider.sql): the `demoprov_notices` table of the site notices feature. One check constraint limits `level` to the cases of `NoticeLevel`, another keeps `ends_at` from lying before `starts_at`. The index on `ends_at` and `starts_at` serves the queries of `NoticeRepository`, which all filter on notices that have not ended. `created_at` records when a notice was scheduled.
