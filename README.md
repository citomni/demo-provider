# CitOmni Demo Provider

A reference provider package for the CitOmni PHP framework, and the guide tree that developers and AI assistants read before they write a file in a CitOmni provider package.

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)

- Framework: [CitOmni](https://github.com/citomni)
- Kernel: [citomni/kernel](https://github.com/citomni/kernel)
- The app side: [citomni/demo-app](https://github.com/citomni/demo-app)

---

## What this repository is

- **A guide tree.** Every folder that holds a kind of file has a `README.md` with the rules for that kind of file in a provider package. The tree mirrors a provider package, so the path of the file you are about to write is also the path of its guide.
- **A reference package.** Everything under `src/` is written the way production code should be written, and is meant to be copied. A site notices feature runs through Registry, Service, Controller, Command, Operation, Policy, Repository, Support, Enum and Exception, with its templates, texts, schema and tests. The other guides and the variant files still show patterns with placeholder names.

A provider package is installed with Composer and enabled in an app's `config/providers.php`. Its `Boot\Registry` contributes services, cfg baselines, routes and commands, which the app can override. Code that only one app needs stays in that app. A new package starts from [citomni/provider-skeleton](https://github.com/citomni/provider-skeleton) and follows the guides here. Apps have their own guide tree in [citomni/demo-app](https://github.com/citomni/demo-app), which also installs this package and shows the host app's side.

## Guides

| Writing | Read |
|---|---|
| Any PHP file in `src/` | [`src/README.md`](src/README.md), then the guide of its folder |
| Registry | [`src/Boot/`](src/Boot/README.md) |
| Controller | [`src/Controller/`](src/Controller/README.md), with admin and member variants |
| CLI command | [`src/Command/`](src/Command/README.md), with a test command variant |
| Operation | [`src/Operation/`](src/Operation/README.md) |
| Repository | [`src/Repository/`](src/Repository/README.md) |
| Service | [`src/Service/`](src/Service/README.md) |
| Support, Policy, State | [`src/Support/`](src/Support/README.md), [`src/Policy/`](src/Policy/README.md), [`src/State/`](src/State/README.md) |
| Util, Enum, Exception | [`src/Util/`](src/Util/README.md), [`src/Enum/`](src/Enum/README.md), [`src/Exception/`](src/Exception/README.md) |
| Constant, Contract | [`src/Constant/`](src/Constant/README.md), [`src/Contract/`](src/Contract/README.md) |
| Job handler | [`src/JobRunner/`](src/JobRunner/README.md), with token/push and trusted/pull jobs |
| Template | [`templates/`](templates/README.md) |
| Language file | [`language/`](language/README.md) |
| Database schema | [`sql/`](sql/README.md) |
| Test suite | [`tests/`](tests/README.md) |
| `composer.json` | [Package metadata](#package-metadata) below |

### Looking up a guide

1. Take the path of the file you will write, relative to the package root.
2. Read the `README.md` of every folder from the top folder down to the file's folder. For `src/Service/Mailer.php` that is `src/README.md` and `src/Service/README.md`. A folder without a README adds no rules.
3. Variants are separate files, linked from the folder README.
4. When a guide links example files of this package, read them too. They are the pattern for a new file of that kind.

### Precedence

- The guides are binding for new code.
- When you change an existing file, keep that file's style unless the task is to align it.
- When a guide and the framework source disagree, the source shows what happens today. Report the conflict so one of them gets fixed.

## For AI assistants

Give the assistant read access to this repository and add this to its instructions:

```text
Before writing or changing a file in a CitOmni provider package, read the README.md guides for its path in citomni/demo-provider, from the top folder down to the file's folder, in one batch (for src/Service/Mailer.php: src/README.md and src/Service/README.md). The Registry: src/Boot/README.md. composer.json: the Package metadata section of README.md. A missing README adds no rules. When a guide links example files, read them too. The guides are binding for new code. Never assume an existing contract, such as an API, a service, a cfg key, a route, a table or the template syntax: read it from its source, and stop and ask when it cannot be read. When changing an existing file, keep its style. Report a conflict between a guide and the source instead of silently picking one. Code in an app follows citomni/demo-app instead.
```

## The site notices feature

A site notice is a short message at the top of every page, such as planned maintenance, from its start until its end. Notices are scheduled and ended from the CLI, or from a management page that the host app enables where every visitor may use it. Pages, scripts and other code read the current notices.

| Part | Files |
|---|---|
| Boot | `src/Boot/Registry.php`: the service, the cfg baseline, the template layer, the `$site_notices` template variable, the routes and the commands |
| HTTP | `src/Controller/NoticeController.php` (`/notices.json`) and `src/Controller/NoticeManagementController.php` (the management page and its two forms) |
| CLI | `src/Command/ScheduleNoticeCommand.php`, `ExpireNoticeCommand.php` and `ListNoticesCommand.php` |
| Read API | `src/Service/Notices.php`, the `notices` service |
| Workflow | `src/Operation/ScheduleNotice.php`, `src/Policy/SchedulingPolicy.php` |
| Persistence | `src/Repository/NoticeRepository.php`, `sql/citomni_demo-provider.sql` |
| Values and helpers | `src/Enum/NoticeLevel.php`, `src/Support/LocalTime.php`, `src/Exception/NoticeConfigException.php` |
| Presentation | `templates/partials/` for host layouts, `templates/public/` for the package's own page, `language/da/notices.php`, `language/en/notices.php` |
| Tests | `tests/notices/` |

Scheduling shows the layers working together. `ScheduleNotice` asks `SchedulingPolicy` for refusal reasons and stores the notice through `NoticeRepository`. The command and the management page both call it and turn its result into their own output: a line of text, JSON and an exit code, or flash messages, field errors and a redirect. Ending a notice is one guarded update, so both adapters call `NoticeRepository::expire()` directly.

Reading goes through the `notices` service. `view.vars` in the Registry calls `active()` for every rendered page, so a template shows `$site_notices` with one include, and `/notices.json` serves the same list.

## Requirements

- PHP, its extensions and the CitOmni packages, with their versions: `require`, `suggest` and `conflict` in `composer.json`.
- MySQL 8.0.16+ or MariaDB 10.6+, with the session time zone that the Db service derives from PHP's timezone. An app that sets cfg `db.timezone` sets it to the zone of `locale.timezone`.

## Use it in an app

[citomni/demo-app](https://github.com/citomni/demo-app) has every step below in place.

1. Install the package:

   ```bash
   composer require citomni/demo-provider
   ```

2. Enable the Registry in `config/providers.php`:

   ```php
   \CitOmni\DemoProvider\Boot\Registry::class,
   ```

3. Import the schema into the app's database:

   ```bash
   mysql -u root -p citomni < vendor/citomni/demo-provider/sql/citomni_demo-provider.sql
   ```

   A database that has the schema of an earlier release takes the newer files in `vendor/citomni/demo-provider/sql/migrations/` in name order instead.

4. Show the notices in the app's layout, and style the classes `demoprov-notices`, `demoprov-notice` and `demoprov-notice-<level>`:

   ```html
   {% include "partials/notices.html@citomni/demo-provider" %}
   ```

5. Schedule a notice:

   ```bash
   php bin/citomni notice:schedule "Planned maintenance tonight 22-23." --level=warning
   php bin/citomni notice:list
   ```

   The notice shows until it is ended. `--ends` sets an end, as a local time in the form `YYYY-MM-DD HH:MM`.

Optional:

The management page at `/notices/manage.html` schedules and ends notices without a login. Enable it only where every visitor may change the notices, such as a local dev machine, in `config/citomni_http_cfg.dev.php`:

```php
'demo_provider' => [
	'notices' => [
		'management_enabled' => true,
	],
],
```

To show the management page in the app's layout, override the route's template in `config/citomni_http_routes.php` with an app page that includes `partials/manage_notices.html@citomni/demo-provider`. The entry merges into the package's route, so only the template changes:

```php
'/notices/manage.html' => [
	'template_file'  => 'public/notices.html',
	'template_layer' => 'app',
],
```

Any cfg key of the baseline in `src/Boot/Registry.php`, such as `demo_provider.notices.max_visible`, can be overridden in the app's cfg files. Lists replace lists, so an app that shows notices on some pages only sets its own `view.vars.site_notices.include`.

## Develop the package

Install the dependencies in the package root and run the suites. The database suite connects as `root` to `127.0.0.1:3306` over TCP with the password in `CITOMNI_TEST_PASSWORD`, and creates and drops its own database, so use a local development server. [`tests/README.md`](tests/README.md) has the rules.

```bash
composer install
composer test
```

To try a change in an app before it is released, point the app at the working copy with a Composer path repository, such as `"repositories": [{"type": "path", "url": "../demo-provider"}]`, and require the branch: `composer require "citomni/demo-provider:dev-main"`.

## Package metadata

- `"type": "library"`, `"license"` matching `LICENSE`, and PSR-4 autoloading of the package namespace from `src/` and of its tests from `tests/` (`autoload-dev`).
- `require` holds PHP, the extensions and the packages that the code needs in every mode, directly or through another required package. A runtime package that only one mode needs, such as citomni/http or citomni/cli, goes in `suggest`, with its minimum version in `conflict`, so an app without that mode does not have to install it.
- Constraints with a caret on the lowest release that has what the code uses.
- `scripts.test` runs `tests/run.php`. The branch alias of `main` names the next release line.
- No `composer.lock`: the app that installs the package locks its dependencies.
- Tests and repository metadata are `export-ignore` in `.gitattributes`, so they stay out of the installed package.

---

## Copyright

© 2026-present Lars Grove Mortensen.

The underlying **CitOmni Framework** is © 2012-present **Lars Grove Mortensen**, licensed under the MIT License.

---

## Trademark notice

See [`TRADEMARKS.md`](./TRADEMARKS.md) for the CitOmni trademark notice applicable to this package.

---

## License

citomni/demo-provider is released under the MIT License. See [`LICENSE`](./LICENSE) and [`NOTICE`](./NOTICE).
