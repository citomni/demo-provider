# Enum

An Enum is a closed set of values. No App, no IO, no dependencies.

## Rules

- Backed (`string` or `int`) when values cross a boundary: database columns, job payloads, JSON, HTTP or CLI input. Pure, without a backing type, when the value never leaves PHP. `TransactionIsolation` in citomni/infrastructure is pure for that reason.
- Case names are PascalCase: `Draft`, `ReadCommitted`.
- Persisted and transmitted values are a contract. Never renumber, rename, normalize or reinterpret an existing case value.
- Convert external input with `tryFrom()` and handle null. `from()` only where an invalid value is a bug.
- Keep it small and deterministic. Methods only when the contract needs them, such as a predicate or a list of values, and without dependencies.
- Only the cases and methods the contract defines; no speculative additions.
- Every case gets a one-line PHPDoc that says what the value means, not where it is used, also when the name seems to say it.

## Example

[`NoticeLevel.php`](NoticeLevel.php): a backed enum for a stored level that also travels in `/notices.json` and names the CSS class of the notices partial. `NoticeRepository` passes its values as SQL parameters, the management page converts the form field with `tryFrom()`, and `ScheduleNoticeCommand` takes the `allowed` values of `--level` from its cases.
