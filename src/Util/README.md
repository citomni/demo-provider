# Util

A Util holds pure functions: the same arguments always give the same result. No App, no cfg, no IO, no clock, no logging, no caching, no SQL and no mutable state.

## Rules

- `class Name` in `src/Util/` with public static methods. Never instantiated: no constructor, no properties.
- Everything a function needs arrives as arguments. A function that has to read cfg, the clock, the filesystem or the environment belongs in Support.
- Class constants are fine for fixed rules such as patterns and limits.
- Invalid input throws `\InvalidArgumentException`, or yields a documented null or problem value when that is the contract.
- Document each function's contract precisely; Utils are reused widely, so edge cases belong in the PHPDoc.

## Example

This package has no Util. Its one helper reads cfg and the clock, so it is the Support class `LocalTime`. [citomni/demo-app](https://github.com/citomni/demo-app) has Utils with their tests.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Util;


/**
 * Words: Util with pure functions over plain text.
 *
 * Keeps word counting in one place, so every caller counts the same way.
 *
 * Rules:
 * - Pure: Every result depends only on the arguments.
 */
class Words {

	/**
	 * Count the words in a text.
	 *
	 * @param  string  $text  UTF-8 plain text.
	 * @return int  Number of words; 0 for an empty or whitespace-only text.
	 * @throws \InvalidArgumentException  When the text is not valid UTF-8.
	 */
	public static function count(string $text): int {
		$words = \preg_split('/\s+/u', \trim($text), -1, \PREG_SPLIT_NO_EMPTY);

		if ($words === false) {
			throw new \InvalidArgumentException('Text is not valid UTF-8.');
		}

		return \count($words);
	}


}
```
