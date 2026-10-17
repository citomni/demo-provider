# src/

Rules for every PHP file under `src/` of a provider package. Read this file first, then the README of the target folder. Both apply.

## Where a class goes

| Folder | Holds | Instantiated by |
|---|---|---|
| `Boot/` | The Registry | Not instantiated: the kernel reads its constants, from the app's `config/providers.php` |
| `Controller/` | HTTP adapters | The Router, from the route map |
| `Command/` | CLI adapters | The CLI runner, from the command map |
| `JobRunner/` | Job handlers for citomni/jobrunner | JobRunner, from cfg `jobrunner.handlers` |
| `Operation/` | One transport-agnostic domain action | Its adapter: `new ScheduleNotice($this->app)` |
| `Repository/` | All SQL and datastore IO | Its caller: `new NoticeRepository($this->app)` |
| `Service/` | Reusable App-aware singletons | The service map: `$this->app->serviceId` |
| `Support/` | Focused App-aware helpers | Its caller, explicitly |
| `Policy/` | App-aware rules and decisions | Its caller, explicitly |
| `State/` | App-aware runtime state with keys, TTLs and transitions | Its caller, explicitly |
| `Util/` | Pure functions: no App, no IO | Static calls |
| `Enum/` | Bounded value sets | Not instantiated |
| `Exception/` | Domain and application failures | `throw` |
| `Contract/` | Stable public extension interfaces | Not instantiated |
| `Constant/` | Shared code-level invariants | Not instantiated |

The transport folders sit directly under `src/`: `Controller/` and `Command/`, without the `Http/` and `Cli/` level of an app. Everything else has the same name as in an app.

Three invariants hold everywhere:

1. SQL lives in Repositories. Never in Controllers, Commands, Operations, Services or helpers. CLI test commands are the one exception; see `Command/test-command.md`.
2. Transport shaping lives in adapters (Controllers, Commands and job handlers). Never in Operations or Repositories.
3. Operations own the decision graph of a workflow, and their results are domain-shaped arrays.

Prefer the shortest architecture that keeps these boundaries. Trivial CRUD goes from the adapter straight to a Repository; a layer is added only when it earns its existence.

## The package and its host app

- The package is installed with Composer and never copied into an app. Its namespace is the vendor and package name in PascalCase, like `CitOmni\DemoProvider\`, autoloaded with PSR-4 from `src/`.
- Everything reaches the app through the Registry: services, cfg baselines, routes, commands, template layers and template variables. No other wiring, no discovery, no files written into the app.
- The app has the last word. It overrides any cfg key, service id, route or command of the package in its own files, so every contribution is a default.
- Names are shared with the app and the other packages. Keep them recognizable as the package's own: one cfg root key (`demo_provider`), one command prefix (`notice:`), one table prefix (`demoprov_`), and the package slug as template and language layer (`citomni/demo-provider`). A service id is short and specific (`notices`).
- Code reads only what the package owns or what another package documents: its own cfg, registered services, its own templates and texts. Never the app's templates, layout, routes or files.
- Environment-specific values belong to the app. The package ships defaults that are safe everywhere; an unsafe feature is off until the app enables it, like the management pages of this package.

## File layout

```php
<?php
declare(strict_types=1);
/*
 * This file is part of the CitOmni framework.
 * Low overhead, high performance, ready for anything.
 *
 * For more information, visit https://github.com/citomni
 *
 * Copyright (c) 2012-present Lars Grove Mortensen
 * SPDX-License-Identifier: MIT
 *
 * For full copyright, trademark, and license information,
 * please see the LICENSE file distributed with this source code.
 */

namespace CitOmni\DemoProvider\Service;

use CitOmni\Kernel\Service\BaseService;
```

- Order: `<?php`, `declare(strict_types=1);`, license header, `namespace`, sorted `use` statements, then one class, interface, enum or trait. No blank lines between the first three.
- The header above is for `CitOmni\*` packages, this one included. `Aserno\*` packages carry the Aserno proprietary header of their package. Any other package uses the header that matches its own LICENSE. Never mix headers.

## Code style

- PHP 8.5+. PSR-1 and PSR-4. Classes PascalCase, methods and variables camelCase, constants UPPER_SNAKE_CASE.
- Tabs for indentation. K&R braces: the opening brace stays on the same line.
- A method signature stays on one line until it has 11 or more parameters.
- Call global functions and constants fully qualified: `\trim()`, `\count()`, `\PHP_EOL`. CitOmni code is written this way throughout; it skips the runtime namespace fallback.
- Explicit and deterministic: no discovery, no reflection-driven wiring, no magic. No abstraction, IO or allocation that the task does not need.
- Classes are not final, so an app or another package can extend them. `final` is an exception that needs a concrete argument: extension would break security, an invariant or another explicit design requirement. When a final method is enough, the class stays open, like `MemberBaseController` in citomni/authenticate, whose final `init()` keeps a subclass from skipping the login check. Accepted exceptions are the concrete admin and member Controllers (see their variants) and every class in citomni/kernel except its abstract Base classes, which are the extension points of the kernel.

## PHPDoc and comments

The rules are in the [CitOmni Code Documentation Guide](https://github.com/citomni/docs/blob/main/contribute/code-documentation-guide.md). Where this summary and the guide differ, the guide wins. [`Controller/NoticeManagementController.php`](Controller/NoticeManagementController.php) is the reference file.

- English and plain text, no backticks. Write `->`, `-`, `"`, `'`, `...` and plain spaces instead of arrows, typographic dashes, curly quotes, the ellipsis character and non-breaking spaces. Avoid text that looks like HTML, such as `<name>`; a renderer swallows it.
- After a colon, start a new statement with a capital letter.
- Document only what the element's own code makes true. A statement that a diff elsewhere could make false is removed, rewritten as a requirement, or replaced by an `@see` to its owner. Routes, cfg values, templates, callers and the current behavior of other files never go in PHPDoc; a stable contract may be referenced.
- The contract goes in PHPDoc. Why a line looks the way it does goes in an inline comment at that line. A comment may state an assumption, never another file's current state, and never repeats what the next line says.
- A class description names what this class leaves to which other class or layer, such as "every scheduling rule belongs to SchedulingPolicy". It does not restate general layer rules such as "no SQL here"; they live in these guides, and copies drift.

Every class gets this PHPDoc:

```php
/**
 * <ClassName>: <Role: what it is and which layer, one line.>
 *
 * <Purpose and scope: why it exists and what it deliberately leaves to others. Prose.>
 *
 * Rules:
 * - <Requirement every present and future member must meet.>
 *
 * Typical usage: <only when correct use spans several members in a required order>
 *   <short example>
 */
```

- No summaries of member behavior, no lists of members, collaborators, routes or cfg keys, and nothing that the declaration below already says, such as `final` or the base class.

Every method gets this PHPDoc. Leave out a section that has nothing to say.

```php
/**
 * <One-line summary, imperative.>
 *
 * <Purpose and context. Prose. Mandatory when the method changes state, has more than two
 * outcomes or coordinates several domain collaborators; optional otherwise.>
 *
 * Behavior: <optional when the summary and tags already say everything>
 * - <Outcome or guarantee the caller can rely on>
 *     - <Detail or alternative; 1), 2) only when order matters>
 * - <Precedence/ordering rules>
 * - <Side effects>
 *
 * Notes: <only for caveats, performance, determinism or environment assumptions not evident from the method itself>
 * - <Caveat>
 *
 * Typical usage: <only for public methods that code calls directly; never on dispatch targets>
 *   <Short code example>
 *
 * @param  type  $name  <Description, only when it adds to the type.>
 * @return type  <Description, only when it adds to the type.>
 * @throws \Fully\Qualified\Exception  <When and why; one per contractual exception that can escape.>
 * @see \Fully\Qualified\Element::member()  <Why to look there; only instead of copying its behavior.>
 */
```

- Behavior lists outcomes, not the steps the body takes, and does not repeat the class's Rules.
- `@param` for every parameter. `@return` always, `void` and `never` included, except on constructors and destructors. Both describe only what the type does not say, such as range, format, unit or shape, and give arrays a shape in PHPStan syntax: `array{id:int,text:string}`, `list<string>`. `@throws` for every exception that is part of the contract and can escape, not for infrastructure failures that the global error handler owns, such as `\JsonException` from encoding or database errors. `@see` with a fully qualified name instead of copying another element's behavior.
- Dispatch targets get no Typical usage: Controller actions, Command `execute()` and job handlers are called by the Router, the Runner or JobRunner.
- Constructors follow the method rules with three differences: No `@return`, no Typical usage, and setting the object's own properties does not make the description mandatory. Every parameter still gets `@param`, promoted ones included.
- Every constant, enum case and declared property gets a one-line PHPDoc that says what the value means, not where it is used. A promoted constructor property is covered by the constructor's `@param`.
- Nested list items are indented 4 spaces, with spaces, inside the DocBlock. `1)`, `2)` only where order matters.

Section dividers mark structure, not length: groups of members with different roles, or phases of a method that run in order. A long method that does one linear thing gets none, and many branches are not a reason either. Between groups of members, indented one tab:

```php
	// ----------------------------------------------------------------
	// Section label
	// ----------------------------------------------------------------
```

- Inside a method, numbered and indented like the surrounding code. The numbers state the order:

```php
		// -- 1. Load and validate identity --------------------------------
```

- When a section needs explanation, put a normal comment right below the divider.

## Cfg

- `$this->app->cfg` is read-only. Associative arrays become nested Cfg nodes; lists stay plain arrays.
- An empty array becomes an empty Cfg node, also for a key that normally holds a list. Read such a key as `$v instanceof Cfg ? $v->toArray() : $v`; never cast a Cfg node with `(array)`.
- A direct read throws `\OutOfBoundsException` at the first missing key, at any depth.
- `??` and `isset()` are both existence guards: every node on the path is checked, so neither throws on a missing key. `??` also falls back when the value is `null`; `isset()` is true for a key whose value is `null`.
- The Registry ships a baseline (`CFG_COMMON`, `CFG_HTTP`, `CFG_CLI`) for every cfg path the package reads, so the package reads its own keys directly, with no `??`, `isset()`, `empty()` or `?->`: `$this->app->cfg->demo_provider->notices->max_visible`. A missing baseline is fixed in the Registry, not with a guard at the call site.
- An option over own cfg is read as `$this->options['prefix'] ?? $this->app->cfg->service_name->prefix`. `??` does not guard its right operand; do not append another `?? <default>`.
- Cfg owned by another package, and a path that exists in one mode only, may be absent; read it with `?? <default>`: `(string)($this->app->cfg->locale->timezone ?? 'UTC')`. Use `isset()` only to branch on whether a node exists.
- A runtime-chosen key in the package's own map, such as a profile or storage name: read the map directly and only the lookup with `?? null`, followed by a specific exception when the key is missing.
- Values the app may set wrongly are checked once, where they are read, and reported with an exception that names the cfg path; see `NoticeConfigException`.
- Routes, commands, services and cfg are separate maps. Never put routes or commands in cfg.
- In the Registry baselines, every key that holds a value gets a comment: what it controls, its unit when it has one, and the accepted values when the type does not make them obvious. An entry in another package's cfg structure, such as a template layer, a template variable or a job handler in `jobrunner.handlers`, gets one comment on what it provides. An override of another package's key does not repeat its meaning; a comment says why the package deviates when that is not evident.

## Failures

- Fail fast. Let exceptions reach the global error handler, which logs and renders them.
- `try`/`catch` only for a recoverable failure with an explicit, documented fallback, or for a translation that the public contract defines.
- No catch-all `\Throwable` in regular code. The layer guides name the few narrow cases where one is allowed.
- Invalid method input: `\InvalidArgumentException`. Other required failures: the closest SPL exception.
- Let lower-layer exceptions bubble unchanged; callers may depend on their type, code and classifiers. Read `Exception/README.md` before creating or translating one.

## New code and existing code

New files follow these guides. When you change an existing file, keep its established style unless the task is to align it. If a guide and the target code disagree in a way that matters, say so instead of silently picking one.

New code designs its own contract where the task leaves it open and the task's intent decides it: class, method and constant names, signatures, return shapes, result constants, service ids, and the package's own cfg keys, options, routes, commands, language keys, tables and columns. Every contract that already exists, such as a framework or package API, a service, a cfg key, a route, a table or the template syntax, is read from its source and never assumed. Stop and ask only when the intent itself is unclear or an existing contract cannot be read.
