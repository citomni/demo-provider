# Controller

A Controller is the HTTP adapter for a group of routes. It owns everything HTTP: request input, CSRF, session, cookie and flash handling, redirects, JSON or HTML output, headers, status codes, downloads, cache and index directives, and template rendering.

A provider package keeps its Controllers in `src/Controller/`, directly under `src/`. Apps keep theirs in `src/Http/Controller/` and route them in their config files; [citomni/demo-app](https://github.com/citomni/demo-app) is the guide for that.

## Variants

- Admin section, extends `AdminBaseController` from citomni/admin: [admin-controller.md](admin-controller.md).
- Member section, extends `MemberBaseController` from citomni/authenticate: [member-controller.md](member-controller.md).

## Contract

- `class NoticeController extends \CitOmni\Kernel\Controller\BaseController`. The name ends with `Controller`.
- No constructor. The Router creates the Controller with `$this->app` and `$this->routeConfig`. `routeConfig` holds only `template_file` and `template_layer` from the route, null when the route sets none.
- A Controller that extends `BaseController` directly declares `protected function init(): void`, empty when nothing applies. It runs on every dispatch, so it holds only setup or guards that every action needs. Admin and member Controllers follow their variant instead: there, `init()` is the access guard.

## Routes

- The package routes its Controllers in Registry `ROUTES_HTTP`. The app can override the entries, such as the template of a page, in its own route files, or remove one with `null`, like `'/notices.json' => null`; a placeholder route is removed the same way under `'regex'`.
- The paths are part of the package's API. Its templates link and post to them and its Controllers redirect to them, so a package documents its paths in the Registry and keeps them stable.
- Exact routes are keyed by path. Placeholder routes go under `'regex'`, like `'/notice/{id}/expire'`. Built-in placeholders: `{id}` (digits), `{slug}`, `{code}`, `{email}`; any other name matches one path segment.
- The extension states what a route returns: `.html` for HTML, `.json` for JSON, `.txt` for plain text, and the file's own extension for a download. Routes that only redirect, such as a form POST like `'/notices/schedule'`, have none.
- Pick paths that belong to the feature, like `/notices/...`, so they do not collide with the app's own routes.
- Captured values arrive as positional action arguments, always as strings. Declare those parameters `string` and convert explicitly: the Router dispatches under strict types, so an `int` parameter throws a `\TypeError`. `{id}` matches digits only, 0 included, so `(int)$id` needs a range check before it reaches code that requires an id of at least 1.
- The route's `'methods'` decides the allowed verbs. The Router answers 405, adds HEAD for GET and handles OPTIONS, so actions do not check the method again.

```php
// src/Boot/Registry.php
public const array ROUTES_HTTP = [
	'/notices/manage.html' => [
		'controller'     => \CitOmni\DemoProvider\Controller\NoticeManagementController::class,
		'action'         => 'page',
		'methods'        => ['GET'],
		'template_file'  => 'public/manage_notices.html',
		'template_layer' => 'citomni/demo-provider',
	],
];
```

## Access

- An action that changes data or shows more than every visitor may see needs a guard. Without citomni/admin or citomni/authenticate, that guard is a cfg switch that is off in the package baseline. `init()` answers 404 to every action while it is off, and the app turns it on only where every visitor may use it.
- With a login, the Controller follows the admin or member variant instead.
- Hiding a link is not access control.

## Delegation

- No SQL and no Db access.
- Trivial route-specific CRUD: `new NoticeRepository($this->app)`.
- A workflow (shared with CLI, several Repositories, side effects, state transitions): `(new ScheduleNotice($this->app))->execute(...)`, then translate the result to HTTP.
- Registered Services through `$this->app->serviceId`.
- Never push transport concerns down into Operations, Repositories, Services, Support, Policy, State or Utils.

## Output

- Input from `$this->app->request` (`post()`, `get()`, `input()`, `json()`, ...). Output through `$this->app->response`.
- HTML views: `$this->app->tplEngine->render($this->routeConfig['template_file'] . '@' . $this->routeConfig['template_layer'], [...])`. The route names the package's own template, and an app that overrides the route's template gets the same data. CitOmni has its own template language, not Twig: see [`templates/`](../../templates/README.md).
- `redirect()`, `json()`, `jsonStatus()`, `jsonProblem()`, `text()`, `html()` and `download()` end the request (`never`), and so does `$this->app->errorHandler->httpError()`. `render()` returns.
- An action that completes through `render()` returns `void`. Declare `never` only when every path ends in one of the terminating calls.
- `jsonStatus()` instead of `json()` when the status must differ from 200; `jsonProblem()` for problem responses.
- A JSON answer picks the fields it publishes by name. Never pass Repository rows through unchanged: a column added for an HTML page would reach the wire.
- Error pages: `$this->app->errorHandler->httpError(404, [...])` logs, renders the page and ends the request. It is typed `never`, so no `return;` follows it.
- A `.json` action answers JSON also when it fails: `jsonProblem()`, not `httpError()`, which gives a browser, curl and a plain `fetch()` an HTML page. A `.txt` action fails with `text(..., $status)`.
- A page that posts with `fetch()` sends its form as `FormData`, so the fields of `$csrfField()` and `$captchaField()` reach `csrf->verify()` and `captcha->verify()` unchanged, and its action answers JSON.
- A redirect goes to a path that the package routes itself, kept in a class constant next to the action, like `PAGE_PATH`. `redirect()` resolves an app path against the public root.
- Response metadata (`setStatus()`, `setHeader()`, `noCache()`, `noIndex()`) goes before the call that completes the response.
- Unsafe actions verify CSRF when the app enables it: `$this->app->csrf->verify()` returns false on rejection, `requireValid()` throws instead. `verify()` takes log context such as a stable action id.
- A form that posts without a script uses Post/Redirect/Get: messages go to `$this->app->flash`, the typed input to `flash->old()`, field errors to `flash->fieldErrors()`, followed by an explicit 303 redirect. The page action passes `flash->pullAll()` to its template.
- Translated text through `$this->app->txt->get($key, $file, 'vendor/package', $default)`, with the package slug as layer; see [`language/`](../../language/README.md).

## Failures

- Catch only known, recoverable failures that become a deliberate HTTP result. No `\Throwable` catches.

## Example

[`NoticeController.php`](NoticeController.php): `activeJson()` serves the current notices of the `notices` service as JSON and picks the published fields explicitly, so the wire shape does not follow the Repository row.

[`NoticeManagementController.php`](NoticeManagementController.php), with every action behind the cfg switch `demo_provider.notices.management_enabled`, which `init()` checks:

- `page()` renders the route's template with the current and upcoming notices from `NoticeRepository` and the pulled flash data.
- `schedule()` verifies CSRF and converts the form fields: the level with `NoticeLevel::tryFrom()`, the times with `LocalTime`. It delegates to the `ScheduleNotice` Operation and maps each refusal reason to its form field. A refusal goes back to the page with the typed input, translated field errors and a warning; success with a flash message. Both end in a 303 redirect.
- `expire()` range-checks the captured id, verifies CSRF and calls `NoticeRepository::expire()` directly, because ending a notice is one guarded update.
- The templates are `public/manage_notices.html` and the partials in `templates/partials/`, the texts `language/<language>/notices.php`.
