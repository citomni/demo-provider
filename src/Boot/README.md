# Boot

`src/Boot/Registry.php` declares what the package contributes to an app: services, cfg baselines, HTTP routes and CLI commands. The kernel reads the constants of the Registry classes that the app lists in `config/providers.php`, in that order. A Registry is never instantiated.

## Rules

- `class Registry` in the package's `Boot` namespace, like `CitOmni\DemoProvider\Boot\Registry`.
- Typed constants only: `public const array MAP_COMMON = [...]`.
- Declare only what the package contributes. Leave the other constants commented out with the reason, so a reader sees that they exist and why the package has nothing for them. citomni/provider-skeleton starts with all of them commented out.
- Pure data. No IO, no runtime branching, no environment reads, no closures. Constant expressions such as `\CITOMNI_APP_PATH . '/vendor/...'` are fine.
- Routes, commands, services and cfg are separate maps. Routes never go in cfg.
- Comment each cfg key with what it controls and its unit. The Registry is where app developers look up what they can override, and the one place that documents the meaning of a key; code that reads the key does not repeat it.

## Constants

| Constant | Holds | Notes |
|---|---|---|
| `MAP_COMMON` | Services for HTTP and CLI | The default home of a package Service. |
| `MAP_HTTP`, `MAP_CLI` | Services for one mode | Only for Services that depend on that runtime. Within one provider, they win over `MAP_COMMON` for the same id. |
| `CFG_COMMON` | Cfg baselines for both modes | Every cfg path the package reads in both modes, including intermediate nodes. |
| `CFG_HTTP`, `CFG_CLI` | Mode-specific cfg | Paths that only one mode reads, and contributions to the cfg of a mode package, such as `view` of citomni/http. Merged after `CFG_COMMON` of the same provider. |
| `ROUTES_HTTP` | HTTP routes | Shape: `src/Controller/README.md`. |
| `COMMANDS_CLI` | CLI commands | Shape: `src/Command/README.md`. |

There is no `ROUTES_COMMON` and no `COMMANDS_COMMON`; dispatch is always mode-specific.

## Contributions to citomni/http

Under `view` in `CFG_HTTP`:

- `template_layers`: the package's template root under its slug, `'vendor/package' => \CITOMNI_APP_PATH . '/vendor/vendor/package/templates'`. Templates are then referenced as `file@vendor/package`; see `templates/README.md`.
- `vars`: template variables for the pages of the app, keyed by variable name, so each package and the app add their own next to the others. Every entry has a `type` and a `source`. A `static` source is the value itself. A `dynamic` source names what TemplateEngine calls on every render of a page that the entry covers, preferably a registered service and method: `['service' => 'notices', 'method' => 'active']`. `include` and `exclude` are lists of app paths: `'*'` for every page, `'/'` for the front page, `'/admin/*'` for a subtree. An entry covers a page that `include` matches, or every page when `include` is empty, unless `exclude` matches it.

A dynamic variable costs its source call on every page it covers. Keep the source cheap, or narrow `include`.

## Precedence

- Cfg, last wins: the mode baseline of citomni/http or citomni/cli, then each provider in `providers.php` order (`CFG_COMMON`, then its mode constant), then the app's cfg files and their environment overlays. Associative arrays merge key by key; a list replaces the list before it.
- An empty array counts as a list, so a later `[]` replaces the map before it. A package that adds keys to another package's map, such as `jobrunner.handlers`, comes after that package in `providers.php`.
- Services: app service maps win over providers, later providers win over earlier ones, and the mode baseline has the lowest precedence.
- Routes and commands, last wins: mode baseline, providers, app files, app environment files. They merge like cfg, so an app route with only `template_file` and `template_layer` changes the template of a package route and keeps its controller.
- Environment-specific values belong to the app, never to the package baseline.

## Cache

`php bin/citomni cache:warm` compiles the merged cfg, services, routes and commands of an app into its `var/cache/`. The kernel uses those files as they are and never compares them with the Registry, so an app that has warmed its caches runs `php bin/citomni cache:clear` or `cache:warm` again after it updates the package.

## Example

[`Registry.php`](Registry.php) of the site notices feature:

- `MAP_COMMON` registers the `notices` service, which both modes may use. `MAP_HTTP` and `MAP_CLI` are commented out: no service depends on one mode.
- `CFG_COMMON` holds the two limits that both modes read. `CFG_HTTP` holds the switch of the management pages, off by default, the template layer and the `$site_notices` variable for every page. `CFG_CLI` is commented out: the commands read only keys of `CFG_COMMON`.
- `ROUTES_HTTP` routes the public JSON endpoint, the management page with its template, and the two form actions, one of them a placeholder route under `'regex'`.
- `COMMANDS_CLI` registers three commands under the `notice:` prefix.

[citomni/demo-app](https://github.com/citomni/demo-app) enables this Registry and overrides parts of it from its own config files.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Boot;


/**
 * Registry: Boot contributions of vendor/package.
 *
 * Declares what the package adds to a host app as constant arrays. Merging them with the host
 * app's own maps, and deciding what wins, belongs to the kernel.
 *
 * Rules:
 * - Constants only: No IO, no runtime branching and no environment reads.
 * - Ship a baseline for every cfg path the package reads, with a comment on what each key
 *   controls and its unit.
 */
class Registry {

	/** Services for HTTP and CLI. */
	public const array MAP_COMMON = [
		'articleFeed' => [
			'class'   => \Vendor\Package\Service\ArticleFeed::class,
			'options' => [
				'format' => 'atom',
			],
		],
	];

	// Services for one mode. Not used: articleFeed works in both modes.
	// public const array MAP_HTTP = [];
	// public const array MAP_CLI = [];


	/** Cfg baseline for both modes. */
	public const array CFG_COMMON = [
		'article_feed' => [
			'max_items' => 20,  // Maximum number of entries in one feed.
		],
	];

	// Not used: the package has no mode-specific cfg, routes or commands.
	// public const array CFG_HTTP = [];
	// public const array CFG_CLI = [];
	// public const array ROUTES_HTTP = [];
	// public const array COMMANDS_CLI = [];

}
```
