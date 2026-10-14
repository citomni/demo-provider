# tests/

Standalone PHP test suites, as defined by the CitOmni testing guide ([`contribute/testing-guide.md`](https://github.com/citomni/docs/blob/main/contribute/testing-guide.md) in citomni/docs). No PHPUnit and no test framework: each suite is a PHP script that runs in its own process.

The suites run in the package root, after `composer install` there, without an app. `.gitattributes` keeps `tests/` out of the installed package.

## Layout

- `tests/run.php` runs every suite: first all `tests/*/run.php`, then all `tests/*/database.php`, each group in alphabetical order. It is the reference runner from the testing guide; a new suite needs no registration.
- `tests/<suite>/run.php`: isolated checks, without a database.
- `tests/<suite>/database.php`: checks against a real MySQL or MariaDB server. A suite folder may hold both entry points.
- `tests/<suite>/README.md`: the commands, the expected totals and what the suite covers.
- Helpers of one suite, such as a worker process, sit in its folder. The runner starts only `run.php` and `database.php`.
- `tests/bootstrap.php`: the autoloader, the kernel doubles and the case runner that every suite requires. Not a suite.
- `tests/Support/`: classes the suites share, PSR-4 under `CitOmni\DemoProvider\Tests\Support\`. Not a suite.

## Run

```
php tests/run.php
composer test
php tests/notices/run.php
```

The last line of every suite, and of the runner, holds the totals: `N passed, M failed`, with `, K skipped` when an optional check did not run. The exit code is 1 when a check fails.

## Writing a suite

- The script starts with the CLI guard and an error handler that throws `\ErrorException`. A database suite also calls `\mysqli_report(\MYSQLI_REPORT_ERROR | \MYSQLI_REPORT_STRICT)`. Then it requires `\dirname(__DIR__) . '/bootstrap.php'`.
- One `test('<name>', function (): void { ... })` per case, with `check()` and `throws()` inside, and `done()` as the last statement. A case name says what must hold, so the `PASS` line reads as a fact.
- Suites never load Composer's autoloader. They replace citomni/kernel's `App`, `Cfg` and base classes with the doubles in `tests/Support/Kernel/`, and Composer could load a real kernel class first. The bootstrap loads `CitOmni\DemoProvider\` from `src/`, `CitOmni\DemoProvider\Tests\` from `tests/` and `CitOmni\Infrastructure\` from `vendor/`.
- Code under test runs unchanged. When it needs a kernel feature that a double lacks, extend the double.
- There is no app and no configuration to load. Build the app double with exactly the cfg a case reads, the package's own keys included: a case does not get the Registry baseline.
- Declare no named class in a suite script: it breaks PSR-4 for `CitOmni\DemoProvider\Tests\`, and Composer warns about it on every install. Use an anonymous class, or a class of its own in `tests/Support/`.

## What to test where

- Utils: isolated, called directly.
- Policies, Support classes, cfg checks, and argument guards that must fail before persistence: isolated, with an app double that has no `db` service, so any database access fails the case. When a layer below rejects the same input with the same exception, also check where it was thrown, or the case passes without the guard it is about.
- Time: pass the current time to a Policy and choose it in the case. Code that reads the clock itself gets times relative to now, and a timezone with daylight saving, so the conversions are exercised.
- Repositories and Operations: database suites with the real Db service, because the SQL and the schema are the contract.
- A race: deterministically, with a competing write at a known point. Real multi-process races are optional checks, run only with `CITOMNI_TEST_PARALLEL=1` and reported as `SKIP` otherwise. [citomni/demo-app](https://github.com/citomni/demo-app) has both kinds.
- Controllers, Commands, templates and the Registry run only inside an app. Try them in one, such as citomni/demo-app with this package installed from a path repository.

## Database suites

- Connection: `root` on `127.0.0.1:3306` over TCP. The password comes only from `CITOMNI_TEST_PASSWORD`; unset means no password. Use a local development server; the suites create and drop databases.
- Each run uses one database of its own. `\CitOmni\DemoProvider\Tests\Support\TestDatabase::create()` creates `citomni_demo_provider_<suite>_test_<random>`; the suite selects it, imports `sql/citomni_demo-provider.sql`, the current schema, and calls `TestDatabase::drop()` in `finally`, which drops only a database that `create()` named.
- `TestDatabase::app()` returns an app double with the real Db service on that database. The password reaches it through an in-memory secrets double.
- Never put the password on a command line, in a file or in output. Worker processes inherit it through the environment.
- A database suite needs `vendor/`, so run `composer install` first. When the server is down, the suite fails; nothing is skipped.

## Rules

- A regression test must fail against the code without the fix it protects. Show that before you rely on it.
- Keep the expected totals in each suite README up to date, also those with `CITOMNI_TEST_PARALLEL=1`.
- The code conventions apply to tests: the license header, tabs, K&R braces, English comments.

## Examples

- [`notices/run.php`](notices/run.php): a Support class, a Policy with a chosen time, cfg checks, argument guards, and an Operation up to the point where it would write, all isolated.
- [`notices/database.php`](notices/database.php): a Repository, a Service and an Operation on the real schema, with PHP's timezone set as the kernel sets it.
