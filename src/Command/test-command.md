# CLI test command

A Command written to test, diagnose, probe concurrency, experiment with persistence or reproduce a regression. It is development tooling, not production architecture. The contract, signature and output rules of `README.md` apply; its layering rules do not.

A test command runs inside an app that has the package installed, with that app's configuration and database. Automated suites that `php tests/run.php` runs in the package root are not Commands; see [`tests/README.md`](../../tests/README.md).

## What is allowed here

- Setup, orchestration, SQL and direct Db use, transactions, locks, subprocesses, concurrency, filesystem changes, assertions, diagnostics and cleanup may all live in the Command when the test needs that control or visibility.
- Do not add Operations, Repositories, Services or Support classes only to satisfy production layering. Use an existing one when it is the subject of the test or when reuse has concrete value.
- Exercise the real behavior under test. Reimplement production logic in the Command only when that isolated logic is what is being tested.
- Do not change production behavior to make a test easier, unless the task asks for that change.

## Rules

- The name ends with `Command` and says what it tests: `AllocationConcurrencyTestCommand`, `DatabaseLockTestCommand`.
- Prefer isolated test identifiers and deterministic setup. Never run destructive operations against unspecified production-like data; when the safe scope is unclear, stop and ask.
- Concurrency tests must separate a real failure from timing noise: explicit synchronization, bounded waits, timeouts, completion checks and exit-status checks, using only APIs that exist.
- Collect enough diagnostics to name the failing step, worker, process, transaction or assertion, without noise.
- Clean up temporary state unless cleanup would invalidate the test, or the task wants the evidence kept. Use `finally` when cleanup must always run.
- Catching exceptions, including `\Throwable`, is allowed when observing failures, coordinating subprocesses or guaranteeing cleanup is part of the test. Never swallow an unexpected failure; keep expected and unexpected failures distinguishable.
- Exit codes: `self::SUCCESS` only when every pass condition holds, `self::FAILURE` when the scenario ran and an expectation failed, `self::USAGE` for usage errors the parser cannot catch.
- Prefer arguments and options over interactive input so the test can be repeated. `--json` when machine-readable results are useful.
- Never register it in the Registry: `COMMANDS_CLI` applies in every environment of every app that installs the package, and only the app's command files have an environment overlay. Register it only when the task asks for it, in the dev command file of the app that runs it, normally `config/citomni_cli_commands.dev.php`, so it never exists in stage or prod.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Command;

use CitOmni\Kernel\Command\BaseCommand;


/**
 * AllocationConcurrencyTestCommand: Test command that runs concurrent allocations and checks that none is lost.
 *
 * Development tooling for one concurrency regression, run in an app that has the package
 * installed. It is never registered in the package's Registry.
 *
 * Rules:
 * - Touch only the test rows that the run creates, and remove them unless the evidence is
 *   needed.
 */
class AllocationConcurrencyTestCommand extends BaseCommand {

	/**
	 * Declare the test interface.
	 *
	 * @return array{arguments?: array<string,array<string,mixed>>, options?: array<string,array<string,mixed>>}
	 * @see \CitOmni\Kernel\Command\BaseCommand::signature()  The signature format.
	 */
	protected function signature(): array {
		return [
			'arguments' => [
				'workers' => [
					'description' => 'Number of concurrent workers',
					'required'    => true,
					'type'        => 'int',
				],
			],
			'options' => [
				'json' => [
					'short'       => 'j',
					'type'        => 'bool',
					'description' => 'Emit the result as JSON',
				],
			],
		];
	}


	/**
	 * Run the scenario and report pass or fail.
	 *
	 * Reproduces the lost-allocation regression with real concurrency, on test rows that the run
	 * creates and removes itself.
	 *
	 * Behavior:
	 * - Exit code 0 when every invariant holds, 1 when one fails, 2 when workers is below 1.
	 * - With --json: One JSON line on stdout with ok, and result with workers and diagnostics.
	 *   Otherwise each diagnostic goes to stderr.
	 *
	 * @return int  Process exit code.
	 */
	protected function execute(): int {
		$workers = $this->argInt('workers');

		if ($workers < 1) {
			$this->error('Workers must be at least 1.');

			return self::USAGE;
		}

		$diagnostics = [];

		try {
			// Setup, workers and assertions go here; each failed assertion appends to $diagnostics.
		} finally {
			// Remove the isolated test rows unless the task wants the evidence kept.
		}

		$passed = $diagnostics === [];

		if ($this->getBool('json')) {
			$this->stdout(\json_encode([
				'ok'     => $passed,
				'result' => [
					'workers'     => $workers,
					'diagnostics' => $diagnostics,
				],
			], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

			return $passed ? self::SUCCESS : self::FAILURE;
		}

		foreach ($diagnostics as $diagnostic) {
			$this->stderr((string)$diagnostic);
		}

		if (!$passed) {
			$this->error('Test failed.');

			return self::FAILURE;
		}

		$this->success('Test passed.');

		return self::SUCCESS;
	}


}
```
