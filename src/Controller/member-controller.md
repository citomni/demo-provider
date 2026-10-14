# Member Controller

A Controller in the authenticated member section, in a package that requires citomni/authenticate. `README.md` applies except where this file says otherwise.

## Contract

- `final class XController extends \CitOmni\Authenticate\Controller\MemberBaseController` (citomni/authenticate), not `BaseController`. Concrete member Controllers are final, as in the base class's usage example.
- Extend it only when every action of the Controller requires a signed-in member. A Controller that mixes public and member-only actions keeps explicit guards per action instead.
- Declare no `init()`: `MemberBaseController::init()` is final. It resolves the identity through `auth`, remembers the intended URL for an unauthenticated GET, redirects to the login path, assigns `$this->identity` and sends the member response headers. Its source is authoritative for the current behavior.
- The package requires citomni/authenticate in `composer.json`, and the app lists its Registry in `config/providers.php`.

## Rules

- Routes in Registry `ROUTES_HTTP` under `/member/`, with the package's own templates; see Templates below.
- `$this->identity` holds the authenticated identity; prefer `$this->identityId()` when only the id is needed. Never call `auth` just to find the same identity again; other Auth APIs only when the workflow needs more than the inherited identity.
- Render route-configured pages with `$this->render([...])`. It renders `template_file@template_layer` and adds the pulled flash data as the `flash` variable. Do not call `tplEngine` directly for that path, and do not pull flash before `render()`.
- Session only for explicit member workflow state, never for authentication state that citomni/authenticate owns. Cookies only when the task needs them.
- Unsafe actions verify CSRF; `verify()` takes optional log context such as a stable action id.
- Form posts use Post/Redirect/Get with flash messages, field errors and old input, and an explicit 303 redirect.
- Every member-facing text, flash messages included, comes from `txt` with the package slug as layer and an English default. New texts go into the package's language files; see [`language/`](../../language/README.md).
- A sensitive action that needs a recent credential check, such as changing an e-mail address, uses the reauthentication of citomni/authenticate: `$this->app->auth->requireReauth($maxAgeSeconds)` throws `ReauthRequiredException`, which the action turns into a redirect to the reauthentication page.
- Authentication, authorization and reauthentication rules, session keys and cookie names belong to citomni/authenticate or to existing code of the package. Read them from the source; never invent them in a Controller.
- A form action may be declared `void` even when its branches end in redirects; `never` when every path ends in a terminating Response call.

## Templates

- A member page of the package extends `member/member_layout.html@citomni/authenticate`, the member frame of the package it requires, or a layout of its own. It never extends an app template. An app that wants the page in its own layout overrides the route's template with a page that includes the package's content partial; see [`templates/`](../../templates/README.md).

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Controller;

use CitOmni\Authenticate\Controller\MemberBaseController;
use Vendor\Package\Repository\NoteRepository;


/**
 * MemberNoteController: HTTP adapter for the signed-in member's own notes.
 *
 * Gives each member access to their own notes and to no one else's. Authentication belongs to
 * MemberBaseController.
 *
 * Rules:
 * - Read and change only notes that the signed-in member owns.
 */
final class MemberNoteController extends MemberBaseController {

	/** Path that every action redirects to; it must equal the list page's route path. */
	private const LIST_PATH = '/member/notes.html';


	/**
	 * Render the signed-in member's notes.
	 *
	 * @return void
	 */
	public function index(): void {
		$notes = (new NoteRepository($this->app))->listByOwner($this->identityId());

		$this->render([
			'notes' => $notes,
		]);
	}


	/**
	 * Delete one of the member's notes and report the outcome through a flash message and a redirect.
	 *
	 * Answers a note of another member like a missing note, so the response never tells whether
	 * the id exists.
	 *
	 * Behavior:
	 * - Every path ends in a 303 redirect to LIST_PATH with exactly one flash message:
	 *     - Error when the CSRF token is rejected. Nothing is deleted.
	 *     - Success when the note was deleted.
	 *     - Warning when no note of the member has the id.
	 *
	 * @param  string  $id  Note id as captured by the route.
	 * @return void
	 */
	public function delete(string $id): void {
		if (!$this->app->csrf->verify(['action' => 'member_note_delete'])) {
			$this->app->flash->error($this->app->txt->get('flash_form_expired', 'notes', 'vendor/package', 'The form expired. Try again.'));
			$this->app->response->redirect(self::LIST_PATH, 303);
		}

		$deleted = (int)$id > 0
			&& (new NoteRepository($this->app))->deleteForOwner((int)$id, $this->identityId());

		if ($deleted) {
			$this->app->flash->success($this->app->txt->get('flash_deleted', 'notes', 'vendor/package', 'Note deleted.'));
		} else {
			$this->app->flash->warning($this->app->txt->get('flash_not_deleted', 'notes', 'vendor/package', 'The note was not deleted.'));
		}

		$this->app->response->redirect(self::LIST_PATH, 303);
	}


}
```
