# Constant

A Constant class holds code-level invariants that several parts of the package must agree on: values fixed in code, not set by the app, and read by more than one class, the Registry or the templates. No App, no IO, no logic.

## Use another place when

- The app may change the value: cfg, with a baseline in the Registry.
- The values are a closed set that code compares, stores or transmits: Enum.
- One class owns the value and the others read it from there: a class constant on the owner, like `NoticeRepository::TEXT_MAX_LENGTH`, which mirrors the column that the Repository owns.
- The value decides something: Policy.

## Rules

- `class Name` in `src/Constant/`, named after what its values describe, like `Route` or `PackagePath`. No `Constant` suffix; the namespace says it.
- Typed public constants only: `public const string PREFIX = '/feeds';`. No properties, no methods, no constructor. It is never instantiated.
- Constant expressions only: literals, other constants, and `__DIR__` for paths inside the package. Never a value that differs per app or environment.
- A one-line PHPDoc on every constant that says what the value means, not where it is used. The class PHPDoc states, as a rule, that the parts which must agree use the constants instead of literal values.
- The Registry may use the constants in its own constant expressions, such as route keys. An app may use them in its config files, such as the key of a route override.
- A value that the Registry, the templates or the app use is part of the package's contract. Changing it is a breaking change.

## Pattern

Placeholder names; replace them with the real ones. Route paths are the typical case: the Registry routes them, and Controllers and templates link and redirect to them.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Constant;


/**
 * Route: Constant class for the URL paths of the package's routes.
 *
 * Keeps every path that the package routes, links to or redirects to in one place, relative
 * to the app's public root, so routes, links and redirects cannot drift apart.
 *
 * Rules:
 * - Route keys, links and redirects use these constants, never a literal path.
 */
class Route {

	/** Common prefix of the package's routes. */
	public const string PREFIX = '/feeds';

	/** Path of the feed list page. */
	public const string LIST_PAGE = self::PREFIX . '/list.html';

	/** Path that refreshes one feed; {id} is the feed id. */
	public const string REFRESH = self::PREFIX . '/{id}/refresh';

}
```

```php
// src/Boot/Registry.php
public const array ROUTES_HTTP = [
	\Vendor\Package\Constant\Route::LIST_PAGE => [
		'controller' => \Vendor\Package\Controller\FeedController::class,
		'action'     => 'list',
		'methods'    => ['GET'],
	],
	'regex' => [
		\Vendor\Package\Constant\Route::REFRESH => [
			'controller' => \Vendor\Package\Controller\FeedController::class,
			'action'     => 'refresh',
			'methods'    => ['POST'],
		],
	],
];
```

## Example

This package has no Constant class.
