# Trusted/pull job

A complete job of a package in trusted/pull mode: an adapter only enqueues it with `EnqueueTrustedJob`; the persistent `job:work` supervisor runs it in a fresh trusted child. Use this mode when the job must run under a specific user identity or needs user-scoped credentials. The handler rules of `README.md` apply.

## Flow

```text
Controller / Command
    -> EnqueueTrustedJob
    -> queued row with claim_mode = trusted
    -> persistent job:work supervisor
    -> handoff prepared internally
    -> fresh job:run-trusted child
    -> guarded queued -> running claim
    -> the package's JobHandler
    -> Operation / Services / Repositories
    -> terminal JobRunner state
```

## Rules

- Dispatch with `\CitOmni\JobRunner\Operation\EnqueueTrustedJob`. Not `StartJob`; that is the token/push mode.
- The adapter stops at enqueue. It never starts `job:work`, `job:run-trusted` or any other worker process; the supervisor owns handoff and launch.
- The adapter stays thin, as in token/push: input, CSRF, payload, enqueue, redirect or terminal output, job status. Handle both statuses: `queued` and `already_active`.
- Never create, expose, persist, log or return trusted handoff tokens.
- One stable, explicit job type, registered as in `README.md`. Reuse an existing Operation when the workflow exists; never duplicate business logic in the handler.
- Credentials are resolved at execution time inside the trusted child, through the mechanism the task names, such as Git Credential Manager, ssh-agent or the app's secret store. They never enter payloads, results or logs.
- Execution identity is part of the job contract when file ownership or user-scoped credentials matter. The HTTP process identity is not the execution identity; the trusted child is the process that must see the intended user context (USERPROFILE, APPDATA, Git and GCM, OpenSSH and ssh-agent, DPAPI CurrentUser).
- Lock keys only when concurrent duplicates must be prevented, derived from stable, non-secret task identity.
- Cancellation is cooperative here too: `CancelJob` only asks a running job to stop. Do not claim to interrupt a running external process unless the runner contract supports it.
- A caller that can enqueue a trusted job can request the action of that job type under the trusted identity. The adapter authorizes and validates as strictly as the action itself requires.

## Dispatch

```php
use CitOmni\JobRunner\Operation\EnqueueTrustedJob;

$result = (new EnqueueTrustedJob($this->app))->execute(
	'vendor_package.sync_repository',
	['repository_id' => $repositoryId],
	'vendor_package.sync_repository:' . $repositoryId,
	'Sync repository ' . $repositoryId
);
```

## Verification

Check the whole path:

1. The adapter enqueues a trusted job.
2. The persistent supervisor picks it up.
3. One fresh trusted child claims and runs it exactly once.
4. The handler reaches the expected terminal result, and the result and progress data are stored.
5. When identity matters, the child runs under the intended user. When credentials matter, access works without any secret value being exposed.
