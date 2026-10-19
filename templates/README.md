# templates/

HTML templates of the package for the `tplEngine` service of citomni/http. A Controller prepares the data and renders a template; the template only presents that data.

## Layout

- The Registry registers this folder as the package's template layer under its slug, in `CFG_HTTP` `view.template_layers`: `'citomni/demo-provider' => \CITOMNI_APP_PATH . '/vendor/citomni/demo-provider/templates'`.
- A template is referenced as `<path below this folder>@<package slug>`, like `public/manage_notices.html@citomni/demo-provider`. The app's own templates are the `app` layer; other packages have their own slugs.
- A route names its template in `template_file` and `template_layer`. The Controller renders `template_file . '@' . template_layer` instead of hard-coding the reference, so the app can swap in its own page by overriding the route.
- `<area>/<name>.html`: the package's own pages with their layout, in the areas of an app: `public/`, `member/` and `admin/`. The area follows who may open a page, not what the page does, so the management page, which needs no login, is in `public/`.
- `partials/`: pieces that an app includes in its own templates, in any area.

## Templates for an app's pages

A package does not know the app's layout, so it never extends an `app` template. It offers two kinds of template instead:

- A partial that an app includes where it wants the feature: `{% include "partials/notices.html@citomni/demo-provider" %}`. It reads only the variables that the package supplies, through `view.vars` or the Controller of the route, and guards each one, because an include on a page without the variable still compiles. Its CSS classes start with the package's prefix (`demoprov-`) and are documented at the top of the partial, so the app can style them.
- A page of its own, which extends the package's own layout. It includes the same partial that an app page includes, so an app that overrides the route's template shows the same content in its own layout.

## Syntax

CitOmni has its own template language. Expressions are native PHP expressions: there are no filters and no Twig syntax.

| Syntax | Meaning |
|---|---|
| `{{ expr }}` | Escaped output (`htmlspecialchars` with `ENT_QUOTES` and the runtime charset). Null prints nothing. |
| `{{{ expr }}}` | Raw output, for trusted markup only. |
| `{% set $name = expr %}` | Local variable. The `$` is required. |
| `{% if expr %}`, `{% elseif expr %}`, `{% else %}`, `{% endif %}` | Conditions. |
| `{% foreach ($items as $item) %}`, `{% endforeach %}` | Loops. The parentheses are required. |
| `{% continue %}`, `{% break %}` | Loop control, with an optional level. |
| `{% extends "file@layer" %}` | Inherit a layout. |
| `{% block name %}`, `{% endblock %}` | Content for the layout's `{% yield name %}`. |
| `{% include "file@layer" %}` | Insert another template at compile time. It shares the variables of the including template, also across layers. |
| `{% include expr %}` | Include the template whose `file@layer` reference `expr` yields when the directive runs, such as one partial per content block. It is compiled on its own and shares the variables of the including template, also across layers. An `expr` that starts with a string literal goes in parentheses. |
| `{# ... #}` | Template comment, removed at compile time. |
| `{?= expr ?}`, `{? code ?}` | Inline PHP, when `view.allow_php_tags` is on (the default). |

## Inheritance

- A page starts with `{% extends "public/notices_layout.html@citomni/demo-provider" %}` and consists of blocks. Markup outside the blocks is discarded.
- The yields of a layout are a contract. A child block without a matching yield, a yield that the child does not fill, and a duplicate block name all fail at compile time.
- A page can extend a layout in another layer, like a package admin page that extends `admin/admin_layout.html@citomni/admin`. A layout can itself extend another layout; the engine follows the chain and fails on a cycle.

## Variables

A template sees three sources. The first one wins on a name conflict:

1. The data the Controller passes to `render()`.
2. Path-scoped values from `view.vars`, which the Registries of the packages and the app's cfg declare.
3. Globals and helpers.

Controller data shadows a global or helper with the same name, so never pass keys like `url`, `txt` or `charset`.

Globals: `$app_name`, `$language`, `$charset`, `$base_url`, `$public_root_url`, `$env['name']`, `$env['dev']`, `$marketing_scripts`, and the flags `$csrf_protection`, `$captcha_protection`, `$honeypot_protection` and `$form_action_switching`.

| Helper | Use |
|---|---|
| `$url($path, $query = [])` | Absolute URL of a path in this app. |
| `$asset($path, $version = null)` | Absolute URL of a file in `public/`, with the `view.asset_version` cache buster. |
| `$txt($key, $file, $layer, $default, $vars)` | Translated text, see [`language/`](../language/README.md). |
| `$dt($when, $pattern)` | A date formatted with an ICU pattern in the app's timezone and locale. `$dtNow()`, `$dtMonth()` and `$dtWeekday()` cover the rest. |
| `$csrfField()` | Hidden CSRF input for a form. Raw output. |
| `$captchaField()`, `$captchaUrl()` | Captcha challenge. Empty when captcha is off. |
| `$currentPath()` | Path of the current request from the app root, for active menu items. |
| `$icon($id, $file, $layer)`, `$hasIcon(...)` | Inline SVG icons. Raw output. |
| `$hasService($id)`, `$hasPackage($slug)` | Show parts that need an optional service or package. |
| `$auth(...)`, `$role(...)` | Identity and role checks, with citomni/authenticate. |

## Rules

- Templates present; they do not decide. Data, access checks and business rules come from the Controller. No queries and no domain side effects, like writes or mail. The form helpers `$csrfField()` and `$captchaField()` keep their state in the session; that is expected.
- Escape by default. `{{{ }}}` only for markup that the app or a helper built, like `$csrfField()` and `$icon()`. Never for user content, and never for a text whose placeholders carry user content.
- A runtime include takes its reference from code or cfg, never from request data or other untrusted input: the template it names runs as PHP.
- Every visible text comes from `$txt()` with the package slug as layer and an English default: `$txt('schedule', 'notices', 'citomni/demo-provider', 'Schedule')`. Without the layer argument, `$txt()` reads the app's language files, not the package's.
- Links through `$url()`, files in `public/` through `$asset()`. No hard-coded hosts or base paths.
- Every POST form contains `{{{ $csrfField() }}}`, and its action verifies the token.
- Dates through `$dt()`, with the pattern from the language file, so each language orders the parts its own way.
- Hiding a link is not access control. A form or link to a guarded action points to a route whose Controller checks access itself.

## Notes

- Templates compile to PHP files in `var/cache`. A changed source file is detected on the next render.

## Example

- [`partials/notices.html`](partials/notices.html): the current notices for an app's layout, from `$site_notices`, guarded with `!empty()`, with prefixed classes per level.
- [`partials/manage_notices.html`](partials/manage_notices.html): the management content. Flash messages, a table with `$dt()` and a translated pattern, a POST form per row, and the scheduling form with `$csrfField()`, the typed input from `$flash['old']` and the field errors from `$flash['err']`. Policy reason codes arrive as translated text, because the Controller translates them.
- [`public/notices_layout.html`](public/notices_layout.html) and [`public/manage_notices.html`](public/manage_notices.html): the package's own page. The layout includes the notices partial and styles the prefixed classes; the page fills its blocks with the management partial.

[citomni/demo-app](https://github.com/citomni/demo-app) includes both partials in its own templates.
