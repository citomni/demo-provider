# JobRunner

A job handler is the worker entry point for one job type of citomni/jobrunner. It is an adapter: it reads the payload, reports progress, honors cancellation and delegates the work. A package ships handlers for the job types it owns.

## Starting jobs

How an adapter starts a job decides how it runs. Pick one mode and read its guide:

- Token/push: `StartJob` enqueues and launches a fresh `job:run` child at once. [token-push-job.md](token-push-job.md)
- Trusted/pull: `EnqueueTrustedJob` queues the job for the persistent `job:work` supervisor, which runs it in a fresh `job:run-trusted` child. Use it when the job needs a specific execution identity or user-scoped credentials. [trusted-pull-job.md](trusted-pull-job.md)

In both modes, an adapter reads a job's status, result and log lines with `(new GetJobStatus($this->app))->execute($jobId, $afterSeq, $logLimit)`, and requests cancellation with `(new CancelJob($this->app))->execute($jobId)`, both in `CitOmni\JobRunner\Operation`. A queued job is cancelled at once; a running job only gets the request, which its handler honors at its next check.

## Setup

A package that ships a handler requires citomni/jobrunner in `composer.json`, because the handler implements its interface. The app that installs the package lists `\CitOmni\JobRunner\Boot\Registry::class` in `config/providers.php`, and imports `vendor/citomni/jobrunner/sql/citomni_jobrunner.sql`. citomni/jobrunner brings the services, the `jobrunner` cfg baseline and the `job:*` commands.

## Contract

- `class ImportFeedJobHandler implements \CitOmni\JobRunner\Contract\JobHandlerInterface` in `src/JobRunner/`. The name ends with `JobHandler`.
- `public function run(JobContext $context): array`.
- No constructor arguments: JobRunner instantiates handlers with `new $class()`. The App comes from `$context->app()`; never inject services, Repositories, options or cfg through the constructor.
- Per-job state lives in locals or the JobContext, never in properties: one handler instance runs every job of its type in a process.
- The handler lives in the package that owns its job type. Never move it into citomni/jobrunner or edit that package's Registry for it.

## Rules

- Thin: validate the payload, adapt progress, logging, heartbeat and cancellation, and delegate. Non-trivial work goes to an Operation; trivial persistence to an explicitly instantiated Repository. No SQL.
- Check `$context->isCancellationRequested()` before and between long steps. When it is set, log a warning and return at once. JobRunner then marks the job cancelled and does not store the returned array, so nothing in it can tell the adapter about the cancellation.
- Call `$context->heartbeat()` around long-running boundaries.
- Report meaningful steps with `$context->setStep($key, $label, $index, $total)`.
- Log deliberately with `info()`, `warning()`, `error()`, `debug()`, `stdout()` and `stderr()`. No low-value noise, and never secrets.
- Payloads hold task inputs only: minimal, deterministic and JSON-encodable. Never passwords, API tokens, private keys or worker tokens; credentials are resolved when the job executes.
- No secret in a result, a job title, a log line or an exception message either: JobRunner stores all four, and logs the message of a failed job.
- Return a stable, domain-shaped result array. JobRunner stores it as the result of a succeeded job.
- Document the payload and the result shape on `run()`. The job type is documented where it is registered.
- `\InvalidArgumentException` for an invalid payload. Let unexpected failures bubble; no `\Throwable` catches unless the job contract defines a narrow best-effort step.

## Registration

Register each job type in the Registry's `CFG_COMMON`, so the map is visible both where the job is enqueued (usually HTTP) and where it runs (CLI); enqueueing already checks that a handler is registered. The job type starts with the package's cfg root, so it never collides with the app's or another package's.

```php
// src/Boot/Registry.php
public const array CFG_COMMON = [
	'jobrunner' => [
		'handlers' => [
			'vendor_package.import_feed' => \Vendor\Package\JobRunner\ImportFeedJobHandler::class,  // Imports one feed in batches.
		],
	],
];
```

citomni/jobrunner ships `'handlers' => []` in its own baseline. An empty array merged into a map changes nothing, so the package may come before or after citomni/jobrunner in the app's `config/providers.php`.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\JobRunner;

use CitOmni\JobRunner\Contract\JobHandlerInterface;
use CitOmni\JobRunner\Value\JobContext;
use Vendor\Package\Operation\ImportFeed;


/**
 * ImportFeedJobHandler: Job handler that imports a feed in batches.
 *
 * Adapts a queued feed import to the ImportFeed Operation: It reads the payload, reports
 * progress and honors cancellation, and leaves the import itself to the Operation.
 *
 * Rules:
 * - Change data only through the ImportFeed Operation.
 */
class ImportFeedJobHandler implements JobHandlerInterface {

	/**
	 * Import one feed, one batch per step.
	 *
	 * Runs one queued import of the feed in payload feed_id, so the import can take longer than
	 * an HTTP request and be cancelled between batches.
	 *
	 * Behavior:
	 * - Stops before any batch when cancellation is requested, with a warning in the job log.
	 * - Reports each batch as a step, and logs an info line when every batch is done.
	 *
	 * @param  JobContext  $context
	 * @return array{feed_id:int,imported:int}  imported counts the entries of the finished batches.
	 * @throws \InvalidArgumentException  When the payload has no feed_id that is an int of at least 1.
	 */
	public function run(JobContext $context): array {
		$feedId = $context->payload()['feed_id'] ?? null;

		if (!\is_int($feedId) || $feedId < 1) {
			throw new \InvalidArgumentException('Payload feed_id must be an integer >= 1.');
		}

		$import = new ImportFeed($context->app());
		$imported = 0;
		$batches = 3;

		for ($batch = 1; $batch <= $batches; $batch++) {
			if ($context->isCancellationRequested()) {
				$context->warning('Feed import cancelled.', ['feed_id' => $feedId, 'batch' => $batch]);

				return ['feed_id' => $feedId, 'imported' => $imported];
			}

			$context->setStep('batch-' . $batch, 'Batch ' . $batch . ' of ' . $batches, $batch, $batches);
			$imported += $import->execute($feedId, $batch)['imported'];
			$context->heartbeat();
		}

		$context->info('Feed import completed.', ['feed_id' => $feedId, 'imported' => $imported]);

		return ['feed_id' => $feedId, 'imported' => $imported];
	}


}
```

## Example

This package has no job handler. [citomni/demo-app](https://github.com/citomni/demo-app) has the same guides for handlers in an app.
