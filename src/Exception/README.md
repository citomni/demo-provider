# Exception

Exception classes for domain and package failures that callers must be able to tell apart. The exceptions of a package are part of its public contract: the app catches them by class.

## When to create one

- Only when a public contract needs a failure semantic that callers handle differently from the generic one, for example rejected untrusted input versus an infrastructure failure, or a configuration error attributed to a component's own cfg.
- Never for ordinary invalid method input; that is `\InvalidArgumentException`.
- Never just to rename a lower-layer failure. Callers may rely on the original type, code, previous exception and classifiers such as `DbQueryException::isDuplicateEntry()`.

## Rules

- Extend the closest SPL exception: `\RuntimeException` for runtime and configuration failures, `\InvalidArgumentException` for rejected values, `\LogicException` for definition errors in code.
- A family of related failures gets a small base class with one subclass per failure, like `DbException` with `DbConnectException` and `DbQueryException`; a single failure is one class.
- Transport-agnostic: an exception does not decide the response status, rendering or translated text. Adapters map exceptions to HTTP or CLI results.
- Carry what a caller needs to react as readonly properties, such as a machine-readable reason. Never secrets.
- Translating: catch only the specific types being translated, pass the original as `$previous`, and document the new type with `@throws` on the method that throws it.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Exception;


/**
 * ArticleImportException: Exception for a rejected article import.
 *
 * Lets callers tell a rejected import from an infrastructure failure, and tells them why in a
 * machine-readable reason, so that an adapter can map it to a response.
 *
 * Rules:
 * - Keep each reason a stable, machine-readable code such as duplicate_slug.
 */
class ArticleImportException extends \RuntimeException {

	/**
	 * Create the exception with the reason for the rejection.
	 *
	 * @param  string  $reason  Machine-readable reason, such as duplicate_slug.
	 * @param  string  $message  Human-readable message.
	 * @param  \Throwable|null  $previous  Lower-layer cause, when translated.
	 */
	public function __construct(public readonly string $reason, string $message, ?\Throwable $previous = null) {
		parent::__construct($message, 0, $previous);
	}


}
```

## Example

[`NoticeConfigException.php`](NoticeConfigException.php): a value under `demo_provider.notices` that the app has set wrongly. The notices service and `SchedulingPolicy` throw it where they read the value, and `$path` names the key to fix. Nothing catches it; the global error handler reports it like any configuration error.
