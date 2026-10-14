# State

A State class owns one piece of runtime state outside the database: its storage keys, TTL, shape and transitions, in the session, in files or similar. No other class reads or writes that storage directly. Callers instantiate it explicitly.

## Rules

- `class NameState` in `src/State/`, constructed with the App: `new MfaPendingState($this->app)`. Never registered in a service map.
- Owns the keys, the TTL, the stored shape and the allowed transitions. Correct use spans several methods in order, so the class PHPDoc shows the lifecycle as its `Typical usage:`, for example `set()` -> `advance()` -> `clear()`, and states the guarantees as `Rules:`.
- Storage keys start with the package's cfg root, like `vendor_package.`, because the session is shared with the app and the other packages.
- Methods name the transitions (`set()`, `advance()`, `clear()`, `consume()`); `get()` returns the current state or null.
- `get()` treats expired or malformed stored data as absent: it clears it and returns null, so callers need no separate expiry check.
- Nothing is cached on the instance unless the class documents why; instantiating it ad hoc in any caller is then safe.
- No SQL; database-backed state belongs in a Repository. No workflow coordination, no transport output, no audit events; callers own those.
- Validate stored references and payloads strictly; never trust what comes back from storage.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\State;

use CitOmni\Kernel\App;


/**
 * PublishConfirmationState: State class for a pending publish confirmation in the session.
 *
 * Owns the session key, the TTL and the stored shape of the confirmation, so no other class
 * touches that session entry. Deciding what a confirmation allows belongs to the caller.
 *
 * Rules:
 * - Treat expired or malformed stored data as absent: Clear it and report no confirmation.
 * - Keep nothing on the instance; the session holds the only copy.
 *
 * Typical usage:
 *   $state = new PublishConfirmationState($this->app);
 *   $state->set($articleId);       // When the confirmation is asked for.
 *   $articleId = $state->get();    // When it is confirmed; null after the TTL.
 *   $state->clear();               // Once the article is published.
 */
class PublishConfirmationState {

	/** Session key of the confirmation. */
	private const string KEY = 'vendor_package.publish_confirmation';

	/** Seconds that a confirmation stays valid. */
	private const int TTL_SECONDS = 300;


	/**
	 * Create the state for one app's session.
	 *
	 * @param  App  $app
	 */
	public function __construct(private readonly App $app) {
	}


	/**
	 * Store a confirmation for one article, replacing any earlier one.
	 *
	 * @param  int  $articleId  Article id.
	 * @return void
	 * @throws \InvalidArgumentException  When the id is below 1.
	 */
	public function set(int $articleId): void {
		if ($articleId < 1) {
			throw new \InvalidArgumentException('Article id must be >= 1.');
		}

		$this->app->session->set(self::KEY, ['article_id' => $articleId, 'created_at' => \time()]);
	}


	/**
	 * Return the article id of the pending confirmation.
	 *
	 * @return int|null  The article id, or null when there is no valid confirmation.
	 */
	public function get(): ?int {
		$state = $this->app->session->get(self::KEY);

		if ($state === null) {
			return null;
		}

		$valid = \is_array($state)
			&& \is_int($state['article_id'] ?? null)
			&& \is_int($state['created_at'] ?? null)
			&& \time() - $state['created_at'] <= self::TTL_SECONDS;

		if (!$valid) {
			$this->clear();
			return null;
		}

		return $state['article_id'];
	}


	/**
	 * Remove the confirmation.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->app->session->remove(self::KEY);
	}


}
```

This package has no State class: a notice lives in the database, so `NoticeRepository` owns it.
