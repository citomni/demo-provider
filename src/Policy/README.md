# Policy

A Policy holds App-aware rules: whether something is allowed, required, valid or withheld. Callers instantiate it explicitly and act on its answer.

## Rules

- `class NamePolicy` in `src/Policy/`, constructed with the App: `new SchedulingPolicy($this->app)`. Never registered in a service map.
- It decides or validates; it does not change state and does not coordinate workflows.
- Rule values such as limits and switches come from cfg, with a baseline in the Registry (see `src/README.md`).
- The current time arrives as an argument, so the Policy reads no clock and a test chooses the time.
- Data the rule needs comes from the arguments or from Repositories it instantiates, only when the check that needs them is reached. No SQL in the Policy itself.
- No transport concerns: no request parsing, rendering, redirects or output. Adapters turn a refusal into HTTP or CLI output.
- Public methods return plain values (`bool`, a list of reasons, a domain-shaped array) or throw a documented exception when a rule is violated, such as a `PasswordPolicyException` from `validate()`.
- Run cheap checks before expensive ones.
- Authorization rules for admin Controllers live here; see `src/Controller/admin-controller.md`.

## Example

[`SchedulingPolicy.php`](SchedulingPolicy.php) returns the reasons a notice cannot be scheduled: the text length against the column size, the start against cfg `demo_provider.notices.max_lead_days`, and the end against the start and the current time. `ScheduleNotice` refuses on any reason, and the management page shows each one at its form field, so the rules live in one place. An invalid cfg value throws `NoticeConfigException`.

Tests: [`tests/notices/run.php`](../../tests/notices/run.php), isolated with an app double that carries only the cfg the Policy reads.
