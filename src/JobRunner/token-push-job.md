# Token/push job

A complete job of a package in token/push mode: an adapter starts it with `StartJob`, which enqueues it and launches a fresh worker at once. The handler rules of `README.md` apply.

## Flow

```text
Controller / Command
    -> StartJob
    -> queued row with claim_mode = token
    -> JobRunner-owned launch of a detached job:run child
    -> guarded queued -> running claim
    -> the package's JobHandler
    -> Operation / Services / Repositories
    -> terminal JobRunner state
```

## Rules

- Dispatch with `\CitOmni\JobRunner\Operation\StartJob`. Not `EnqueueTrustedJob`; that is the trusted/pull mode.
- `StartJob` owns enqueue-and-launch. Adapters never call `JobLauncher` and never start `job:run`, `job:run-trusted`, `job:work` or any other worker process themselves.
- The adapter stays thin. HTTP parses and validates input, checks CSRF, builds the payload, calls `StartJob`, redirects and exposes job status. CLI parses arguments and options, calls `StartJob` and prints the outcome. Neither performs the background work.
- Handle every `StartJob` status: `started`, `already_active` (a job with the same lock key is active) and `launch_failed`.
- One stable, explicit job type such as `vendor_package.import_feed`, registered as in `README.md`.
- Reuse an existing Operation when the workflow exists. Create one only when the job adds non-trivial orchestration. Never duplicate business logic in the handler.
- Lock keys only when concurrent duplicates must be prevented, derived from stable, non-secret task identity.
- Credentials never enter payloads, results, titles or logs; the child resolves them at execution time.
- Never expose the worker capability outside the JobRunner launch contract. Do not assume the child process can reach user-scoped credentials unless current source establishes that; otherwise use trusted/pull.
- Do not claim to interrupt a running external process unless the runner contract supports it.
- When integrating into an existing UI or CLI flow, keep its observable behavior unless the task changes it.
- The adapter that starts the job may be the package's own Controller or Command, or the app's. Either way it needs the job type, which the Registry declares, and the payload shape, which the handler's `run()` documents.

## Dispatch

```php
use CitOmni\JobRunner\Operation\StartJob;

$result = (new StartJob($this->app))->execute(
	'vendor_package.import_feed',
	['feed_id' => $feedId],
	'vendor_package.import_feed:' . $feedId,
	'Import feed ' . $feedId
);
```

## Verification

Check the whole path:

1. The adapter dispatches through `StartJob`.
2. The worker starts through the existing JobRunner launch path.
3. One fresh child claims and runs the job exactly once.
4. The handler reaches the expected terminal result, and the result and progress data are stored.
5. Lock and cancellation behavior hold.
