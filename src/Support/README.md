# Support

A Support class is a focused, App-aware helper that is neither a pure Util nor a registered Service. Its caller instantiates it explicitly.

## Choose the layer first

- A reusable capability that belongs in the service map: Service.
- Pure and App-independent: Util.
- Mainly policy decisions: Policy.
- An explicit runtime state contract with keys, TTLs and transitions: State.
- A workflow: Operation. Persistence: Repository.
- If the class needs a base class of another layer (`BaseService`, `BaseOperation`, `BaseRepository`, `BaseController`, `BaseCommand`), Support is the wrong layer.
- When a task asks for a Support class whose responsibility belongs to another layer, build it in that layer and say so at the top of the delivery.

## Contract

- `class Name` in `src/Support/`. A Support suffix is not required when a clearer responsibility name exists, like `LocalTime`.
- Never registered in a service map. Callers write `new Name($this->app)`, or use another direct shape when the responsibility clearly warrants it.
- It may hold the App, take it at the method boundary, or use another explicit App-aware shape that follows from its responsibility and nearby source.

## Rules

- A narrow, deterministic responsibility that is reusable within the app or package.
- It may wrap focused App-aware or transport-adjacent mechanics, but it never becomes an adapter: no request parsing, routing, rendering, HTTP response shaping, terminal output, or status and redirect decisions.
- No SQL and no Db access. No non-trivial workflow coordination. No runtime state contracts.
- Registered Services only through their known contracts; cfg only from established paths.
- Cheap construction: no hidden discovery, directory scans, reflection or large object graphs unless the responsibility requires them. Minimal, scoped mutable state. Static process-lifetime caches only when the task needs them or nearby source establishes them.
- Public methods validate input early and document parameters, return shapes, behavior and exceptions.
- Fail fast. A broad `\Throwable` catch only for deliberate best-effort behavior or a narrow translation boundary with explicit failure semantics; never swallow failures silently.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Support;

use CitOmni\Kernel\App;


/**
 * ArticleExportWriter: Support class that writes article exports as JSON files.
 *
 * Gives every exporter the same file format and the same directory. What goes into an export
 * is left to its callers.
 *
 * Rules:
 * - Write each file in full before it becomes visible, so readers never see a partial file.
 */
class ArticleExportWriter {

	/**
	 * Create the writer for the app whose cfg names the export directory.
	 *
	 * @param  App  $app
	 */
	public function __construct(private readonly App $app) {
	}


	/**
	 * Write one export file and return its path.
	 *
	 * Replaces an earlier export with the same name, so a name always holds the newest export.
	 *
	 * Typical usage:
	 *   $path = (new ArticleExportWriter($this->app))->write('weekly', $rows);
	 *
	 * @param  string  $name  File name without extension: lowercase letters, digits and dashes.
	 * @param  array<string,mixed>  $data  Export payload.
	 * @return string  Path of the written file in the directory of cfg article_export.dir.
	 * @throws \InvalidArgumentException  When the name has other characters.
	 * @throws \RuntimeException  When the file cannot be written.
	 */
	public function write(string $name, array $data): string {
		if (\preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
			throw new \InvalidArgumentException('Export name must match [a-z0-9-]+.');
		}

		$path = \rtrim((string)$this->app->cfg->article_export->dir, '/') . '/' . $name . '.json';
		$json = \json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

		// A rename within one directory replaces the file in one step.
		if (\file_put_contents($path . '.tmp', $json) === false || !\rename($path . '.tmp', $path)) {
			throw new \RuntimeException('Could not write export file: ' . $path);
		}

		return $path;
	}


}
```

## Example

[`LocalTime.php`](LocalTime.php): the current time and the dates and times a person types, in the timezone of cfg `locale.timezone`. Support, not Util, because it reads cfg and the clock. `parse()` accepts a few exact forms, including the one of `<input type="datetime-local">`, and rejects impossible dates and local times that daylight saving skips. The Controller, the Command and the Operation share it, so a typed time means the same everywhere.

Tests: [`tests/notices/run.php`](../../tests/notices/run.php).
