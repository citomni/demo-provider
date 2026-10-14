# Command

A Command is the CLI adapter for one concrete terminal action. It owns its signature, the parsed arguments and options, terminal output, interactive input when needed, and the exit code.

A provider package keeps its Commands in `src/Command/`, directly under `src/`. Apps keep theirs in `src/Cli/Command/` and register them in their config files; [citomni/demo-app](https://github.com/citomni/demo-app) is the guide for that.

## Variants

- Tests, diagnostics, concurrency probes and regression reproductions: [test-command.md](test-command.md). Different layering rules apply there. Automated suites that `php tests/run.php` runs are not Commands; see [`tests/`](../../tests/README.md).

## Contract

- `class ScheduleNoticeCommand extends \CitOmni\Kernel\Command\BaseCommand`. The name ends with `Command`.
- No constructor. The CLI runner creates the Command with the App, the command name, its description and the `options` of the command map entry, available as `$this->options`. BaseCommand calls `init()` when it exists.
- Implement `protected function execute(): int`. `signature()` when the Command takes arguments or options.

## Delegation

- No HTTP: no request, response, templates or HTTP adapter services.
- No SQL and no Db access. Trivial persistence: `new NoticeRepository($this->app)`.
- Non-trivial workflows, logic shared with HTTP, several Repositories, side effects or state transitions: `(new ScheduleNotice($this->app))->execute(...)`.
- Registered Services through `$this->app->serviceId` when their contract is known.

## Signature

- Top-level keys: `arguments`, `options`.
- Argument keys: `description`, `required`, `default`, `type`. Types: `string`, `int`.
- Option keys: `short`, `type`, `description`, `required`, `default`, `allowed`. Types: `string`, `bool`, `int`.
- `h` is reserved for `--help`; no option may use it as its short alias.
- A bool option defaults to false and accepts `--no-<option>`. It takes no `allowed` and no null default.
- `allowed` only on string or int options, with values of that type. A backed enum supplies them: `\array_column(NoticeLevel::cases(), 'value')`.
- `required` never comes with a non-null default.
- BaseCommand validates the signature against these rules only when `CITOMNI_ENVIRONMENT` is `dev`. In stage and prod a broken signature passes silently, so run every new Command once in dev.
- Read values with `argString()` and `argInt()` for required or defaulted arguments, `getString()`, `getInt()` and `getBool()` for options that cannot be null, and `opt()` for an optional string or int option without a default, which is null when left out.
- The parser handles `--help`, missing required values, unknown options, invalid types and values outside `allowed`. Do not repeat those checks.

## Output and exit codes

- `stdout()` for normal output and machine-readable payloads. `stderr()`, `warning()` and `error()` for diagnostics and failures. `success()` and `info()` for human-readable status lines.
- Offer `--json` when the Command is useful in automation. With it, every outcome that ends in `SUCCESS` or `FAILURE` writes exactly one JSON document with `stdout()`, and nothing else goes to stdout; warnings go to stderr. Encode with `\JSON_THROW_ON_ERROR`, so a payload that cannot be encoded fails instead of printing nothing.
- The document is an envelope, the same as the success answer of a JSON route: `{"ok": true, "result": ...}` on success, `{"ok": false, "result": ...}` when the domain layer returned a refusal, and `{"ok": false, "error": "..."}` when the Command fails without a result, such as a caught exception. `ok` is true exactly when the exit code is `SUCCESS`.
- A usage error writes no JSON, whether the parser or the Command finds it: the message goes to stderr and the exit code is `USAGE`, so scripts check the exit code first.
- Offer `--dry-run` when the Command changes data and its Operation has a `preflight()`: it prints the report and changes nothing. With `--json`, the report is the `result` of the envelope, and `ok` says whether `execute()` would succeed.
- Return `self::SUCCESS` on success and `self::FAILURE` for a domain or runtime failure the Command handles, including a value that a Repository, Operation or Service refuses with `\InvalidArgumentException`. `self::USAGE` only for a usage error that the parser cannot catch and the Command itself detects before it delegates, such as an id below 1 or a time that does not convert.

## Failures

- Catch only known, recoverable domain exceptions that the Command can present more clearly. No `\Throwable` catches; let the rest reach the CLI error handler.
- Secrets: prefer interactive input. When a secret arrives as an option, warn that it may end up in shell history or process listings.

## Registration

- Registry `COMMANDS_CLI`. The app overrides an entry in its own command files, or removes it with `null`, like `'notice:schedule' => null`.
- Command names are lowercase with colon-separated namespaces. A package keeps its commands under its own prefix, like `notice:schedule`. `app:info`, `cache:warm` and `cache:clear` come from citomni/cli.

```php
// src/Boot/Registry.php
public const array COMMANDS_CLI = [
	'notice:schedule' => [
		'command'     => \CitOmni\DemoProvider\Command\ScheduleNoticeCommand::class,
		'description' => 'Schedule a site notice.',
	],
];
```

## Examples

- [`ScheduleNoticeCommand.php`](ScheduleNoticeCommand.php): converts `--starts` and `--ends` with `LocalTime` and reports a value that does not convert as a usage error. `--level` has its `allowed` values from `NoticeLevel`. It delegates to the same `ScheduleNotice` Operation as the management page: `--dry-run` prints `preflight()`, otherwise `execute()`, with human or `--json` output and an exit code per result.
- [`ExpireNoticeCommand.php`](ExpireNoticeCommand.php): one guarded update, so it calls `NoticeRepository::expire()` directly.
- [`ListNoticesCommand.php`](ListNoticesCommand.php): a read with an `int` option and a default, as aligned columns or `--json`.
