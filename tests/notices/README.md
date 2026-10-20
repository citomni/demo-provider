# Notices suite

Checks for the site notices feature: `\CitOmni\DemoProvider\Support\LocalTime`, `\CitOmni\DemoProvider\Policy\SchedulingPolicy`, `\CitOmni\DemoProvider\Service\Notices`, `\CitOmni\DemoProvider\Repository\NoticeRepository` and `\CitOmni\DemoProvider\Operation\ScheduleNotice`. The real classes run on the kernel doubles in `tests/Support/Kernel/`, without Composer. The suite has two entry points, which `tests/run.php` runs as separate executions.

| Entry point | Needs |
|---|---|
| `run.php` | Nothing: isolated checks with an app double that has no services. |
| `database.php` | A MySQL/MariaDB server, and `vendor/` from `composer install` in the package root for the real Db service of citomni/infrastructure. |

The Controllers, the Commands, the templates and the Registry are not covered: they run only inside an app.

## Run

```
php tests/notices/run.php
php tests/notices/database.php
```

Expected results, in that order:

```
15 passed, 0 failed
10 passed, 0 failed
```

`PASS` lines go to stdout, `FAIL` lines to stderr. The last line holds the totals, and the exit code is 1 when a check fails.

## Database

`database.php` connects as `root` to `127.0.0.1:3306` over TCP, with the password from `CITOMNI_TEST_PASSWORD`, as described in [`tests/README.md`](../README.md). Each run creates its own `citomni_demo_provider_notices_test_<random>` database, imports `sql/citomni_demo-provider.sql` and drops only that database in `finally`. Every check starts from an empty `demoprov_notices` table. If a run is interrupted, remove any leftover `citomni_demo_provider_notices_test_*` database after checking that no run is still going.

## How it works

Both entry points use Europe/Copenhagen, which has daylight saving. `database.php` sets it as PHP's timezone before the Db service connects, as the kernel does from cfg `locale.timezone`, so `NOW()` in SQL and the times of the cases agree. The cases build their times relative to the current time.

`SchedulingPolicy` gets the current time as an argument, so its cases choose the time. `ScheduleNotice` reads the clock through `LocalTime`, so its cases use times relative to now.

Fixtures are inserted on the admin connection, past the code under test. The second `expire()` check first moves the end of the ended notice back: a repeated write within the same second would leave the row unchanged and report 0 rows, so without that step the check would pass without the guard.

## Checks

| Check | Covers |
|---|---|
| LocalTime parses the typed forms as local times in the cfg timezone | The four input forms, surrounding whitespace, the timezone |
| LocalTime rejects impossible dates, other forms and a local time that does not exist | Overflow such as 30 February, other formats, trailing text, a time that daylight saving skips |
| LocalTime::now() uses the cfg timezone and falls back to UTC | `locale.timezone` and its fallback |
| SchedulingPolicy allows a notice with or without an end | No end, a future window, a start in the past |
| SchedulingPolicy refuses an empty or too long text | `text_empty`, `text_too_long`; the limit counts characters |
| SchedulingPolicy refuses a start beyond max_lead_days with starts_too_late | The limit is inclusive |
| SchedulingPolicy refuses an end that is not after the start or not after now | `ends_not_after_start`, `ends_in_past`; both bounds are exclusive |
| SchedulingPolicy lists every reason, in check order | Several reasons at once |
| SchedulingPolicy reads the lead limit from cfg demo_provider.notices.max_lead_days | The cfg key; `NoticeConfigException` with the cfg path for a negative, string or null value |
| The notices service rejects an invalid max_visible before it reaches the database | `NoticeConfigException` with the cfg path for 0, a string and null |
| NoticeRepository rejects ids and limits below 1 before it reaches the database | Argument guards |
| NoticeRepository rejects an empty or too long text before it reaches the database | Text guards |
| ScheduleNotice::preflight() reports the notice as it would be stored, in the app timezone | `allowed`, trimmed text, a UTC input converted to the cfg timezone |
| ScheduleNotice::preflight() reports the reasons of SchedulingPolicy | `allowed` false with the reasons |
| ScheduleNotice refuses with the reasons of SchedulingPolicy and writes nothing | `refused` without a `db` service |
| insert() stores a trimmed notice with local times and returns its id | Insert, trimming, a UTC input stored as local time |
| insert() stores a text of exactly 500 characters intact | Multibyte text at the column size |
| listActive() returns only current notices, newest start first, within the limit | Filter, order with `id` as tie-breaker, limit, row shape and types |
| listNotEnded() returns current and upcoming notices by start, with the current flag | Filter, order, the `current` flag, limit |
| expire() ends a current notice once | Guarded update; a second call and a missing id report false |
| expire() of an upcoming notice moves its start to its end, so it never shows | An empty window at the current time; a second call reports false |
| The schema refuses a level that is not exactly info or warning, and an end before the start | The two check constraints in `sql/citomni_demo-provider.sql`, by name: an unknown level, trailing and leading spaces, another letter case, a tab and `''`; the exact value is stored |
| The notices service returns at most max_visible current notices | The cfg limit, newest start first |
| ScheduleNotice schedules a notice that SchedulingPolicy allows | `scheduled`, the stored row |
| ScheduleNotice refuses with the reasons of SchedulingPolicy and stores nothing | `refused`, no row |

`php tests/run.php` runs every suite of the package.
