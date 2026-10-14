# Contract

A Contract is a stable public extension interface: a seam where code outside the package, an app or another package, implements the interface or types against it as documented public API.

## When a Contract earns its existence

- The package calls code that the app or another package supplies, such as a handler, a storage backend or an authorization check, and documents how to plug it in.
- Code outside the package types against the interface as part of the package's public API.

A Contract exists because outside code needs a stable seam, not because an interface looks architectural.

## Not a Contract

- Internal convenience interfaces, and interfaces created only for tests.
- An interface that mirrors one concrete class without a real extension point.
- DTOs, runtime contexts, traits, helpers and implementations.
- An interface that belongs to one layer's own pattern stays in that layer's folder.

## Rules

- `interface Name` in `src/Contract/`, named after the role that an implementation plays. No base class; a Contract is never instantiated.
- Small: only the methods the package calls. No implementation.
- The PHPDoc is the contract, because implementations are written by people who never read the package's code:
  - The method tags: what the package passes in and what it expects back, with array shapes.
  - The interface description: when the package calls it, whether one instance is reused across calls, and what a thrown exception aborts.
  - The interface `Rules:`: what an implementation must not do, such as writing, sending transport output or keeping per-call state.
- How an implementation is plugged in is part of the contract: usually its FQCN in cfg, which the package instantiates explicitly. Document the constructor it expects, such as no arguments or the App.
- Parameters and return values are scalars, documented arrays, enums or the package's own value classes. Never an App-internal object that the package does not own.
- Stable: a changed signature breaks every implementation. Add a method only in a major release; prefer a new Contract.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Contract;


/**
 * FeedSource: Contract for a source of feed entries.
 *
 * The seam through which an app supplies the entries of one kind of feed. The app lists
 * implementations by FQCN in cfg vendor_package.sources. The package creates each one with
 * new $class($app) when it refreshes a feed, and asks it once. An exception from an
 * implementation aborts the refresh of that feed; the other feeds still refresh.
 *
 * Rules:
 * - Read only: No writes and no output.
 */
interface FeedSource {

	/**
	 * Return the newest entries of the feed.
	 *
	 * @param  int  $limit  Maximum number of entries, at least 1.
	 * @return list<array{id:string,title:string,published_at:string}>  At most $limit entries, newest first.
	 */
	public function entries(int $limit): array;

}
```

## Example

This package has no Contract. `JobHandlerInterface` of citomni/jobrunner is one: apps and packages implement it for every job type, see [`src/JobRunner/`](../JobRunner/README.md).
