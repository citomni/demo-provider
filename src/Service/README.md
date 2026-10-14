# Service

A Service is one small, reusable, App-aware capability that lives as a lazy singleton in the service map.

## Use another layer when

- It runs SQL or touches Db: Repository. A Service that needs persistence delegates to a Repository.
- It parses requests, renders templates or writes terminal output: Controller or Command.
- It coordinates a workflow: Operation.
- It is App-aware but should not be a singleton: Support, Policy or State.
- It is pure: Util.

## Contract

- `class ServiceName extends \CitOmni\Kernel\Service\BaseService`.
- Constructor contract: `new ServiceName(App $app, array $options = [])`. Declare no constructor of your own. BaseService stores `$this->app` and `$this->options`, then calls `init()` when it exists.
- One instance per request (HTTP) or process (CLI), created on first access through `$this->app->serviceId`. Never instantiate a registered Service with `new`.
- Service ids are lowerCamelCase, like `tplEngine` and `valueToSql`.

## init()

- Only cheap, deterministic setup derived from options and cfg. Leave `init()` out when there is nothing to derive.
- No IO, runtime discovery, directory scans, reflection or large object graphs. Expensive work happens in explicit public methods.

## Options and cfg

- Options (`$this->options`) are construction-time wiring from the service map entry.
- Cfg is runtime policy, with a baseline in the Registry for every key the Service reads. Cfg rules: `src/README.md`.

## Rules

- One responsibility. A narrow public API that is predictable, deterministic and boring.
- Filesystem IO is fine when it is the Service's own job: log files, storage, caches, produced files.
- No transport concerns and no workflow coordination.
- Public methods validate their input and document return shapes and exceptions.
- Failure rules: `src/README.md`. A `\Throwable` catch is allowed only around one narrowly scoped, documented best-effort call whose failure must not mask an in-flight exception or break a documented no-throw contract, such as optional logging during cleanup.
- Exception classes for the Service's public contract: `src/Exception/README.md`.

## Registration

- Registry `MAP_COMMON`, or `MAP_HTTP` / `MAP_CLI` for a Service that only works in one mode. See `src/Boot/README.md`. The class stays in `src/Service/` whatever its mode.
- An entry is `'serviceId' => Fqcn::class`, or with options:

```php
// src/Boot/Registry.php
public const array MAP_COMMON = [
	'serviceId' => [
		'class'   => \Vendor\Package\Service\ServiceName::class,
		'options' => [
			'prefix' => 'demo-',
		],
	],
];
```

- The service id is public API: the app and other packages reach the Service through it, and the app may replace the class under the same id in its own service map. Keep the public methods stable.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Service;

use CitOmni\Kernel\Service\BaseService;


/**
 * ServiceName: Service that prefixes labels.
 *
 * Gives every caller in a request or process the same prefix, chosen once from the service
 * options or cfg. What a label means is left to its callers.
 *
 * Rules:
 * - Derive the prefix only in init(); public methods never change it.
 */
class ServiceName extends BaseService {

	/** Prefix of every label. */
	private string $prefix;


	/**
	 * Derive immutable state from options and cfg.
	 *
	 * Behavior:
	 * - The prefix option wins over cfg service_name.prefix.
	 *
	 * @return void
	 * @see \CitOmni\Kernel\Service\BaseService::__construct()  Calls it once per instance.
	 */
	protected function init(): void {
		$this->prefix = (string)($this->options['prefix'] ?? $this->app->cfg->service_name->prefix);
	}


	/**
	 * Prefix one non-empty value.
	 *
	 * Typical usage:
	 *   $label = $this->app->serviceId->label('value');
	 *
	 * @param  string  $value  Trimmed before use.
	 * @return string
	 * @throws \InvalidArgumentException  When the value is empty after trimming.
	 */
	public function label(string $value): string {
		$value = \trim($value);

		if ($value === '') {
			throw new \InvalidArgumentException('Value cannot be empty.');
		}

		return $this->prefix . $value;
	}


}
```

## Example

[`Notices.php`](Notices.php), registered as `notices` in `MAP_COMMON`:

- The package's read API: the `$site_notices` template variable, `/notices.json` and any code in the app read the current notices through `active()`.
- `init()` reads `demo_provider.notices.max_visible` once and throws `NoticeConfigException` with the cfg path when the app has set it to something other than an integer of at least 1.
- `active()` delegates the query to `NoticeRepository` and keeps no copy, so a long-running CLI process never sees a stale list.

Tests: [`tests/notices/run.php`](../../tests/notices/run.php) for the cfg check, [`tests/notices/database.php`](../../tests/notices/database.php) for the limit.
