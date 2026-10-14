# Repository

A Repository is the persistence boundary. It owns the SQL and datastore IO for one focused persistence area, and nothing else.

## Contract

- `class NoticeRepository extends \CitOmni\Kernel\Repository\BaseRepository`. Name: `<Entity>Repository`.
- Constructor contract: `new NoticeRepository(App $app)`, inherited. Callers instantiate it explicitly with `new NoticeRepository($this->app)`.
- Never registered in a service map and never reached through `$this->app->serviceId`.

## Rules

- All SQL goes through `$this->app->db`, the Db service of citomni/infrastructure. Never open a driver connection yourself. The API is in `src/Service/Db.php` of citomni/infrastructure; the usual calls are `fetchValue()`, `fetchRow()`, `fetchAll()`, `exists()`, `insert()`, `insertBatch()`, `update()`, `delete()`, `execute()` and `transaction()`.
- No transport or cross-cutting work: no request, session, cookie, csrf, flash, response or tplEngine, no mail, no translated text (txt), no general logging, no workflow decisions.
- `valueToSql` only when the Repository deliberately accepts form or UI input that must be normalized at the persistence boundary. `valueFromSql` only when it deliberately returns values shaped for edit forms. Neither is business validation or presentation formatting.
- Only the package's own tables, with its prefix, as defined in `sql/`. Tables of the app or of another package only through that owner's documented Repository or Service.
- Validate persistence-relevant input early and cheaply.
- Return stable, documented scalars or array shapes (`@return array{id:int,text:string}|null`). Cast raw row values before returning them.
- Datetime columns hold local times. The Db service derives the session time zone from PHP's timezone, which the kernel takes from cfg `locale.timezone`, so `NOW()` in SQL and PHP's clock agree. Cfg `db.timezone` replaces the derived zone; the package then relies on the app setting the same zone, and says so. A method that takes a `\DateTimeImmutable` converts it to PHP's timezone before it formats it for a column.
- A Repository-local transaction is fine for a sequence this Repository owns. A transaction that spans a workflow belongs to the Operation. Db does not nest transactions (`beginTransaction()` throws while one is active), so a method that opens its own transaction cannot run inside an Operation's transaction; say so in its PHPDoc.
- Keep methods small, boring and reviewable.

## Failures

- Let `DbConnectException`, `DbQueryException`, `ValueToSqlException` and `ValueFromSqlException` bubble. Callers rely on their type, code, previous exception and classifiers such as `DbQueryException::isDuplicateEntry()` and `isDeadlock()`.
- `\InvalidArgumentException` for invalid method input.
- A custom exception only when the public contract needs a persistence-specific failure that differs from the lower-layer one. Translate only then, and keep the original as previous.

## Changing an existing Repository

- Keep public method names, signatures, return shapes and exception behavior unless the task changes them.
- Change only what the task needs, in the file's existing style.

## Example

[`NoticeRepository.php`](NoticeRepository.php):

- `insert()` checks the text against the column size before it inserts, and stores the `\DateTimeImmutable` times as local times in PHP's timezone.
- `listActive()` and `listNotEnded()` compare with `NOW()` in SQL, so "current" means the same in every caller. Both order with `id` as tie-breaker and take a limit. `listNotEnded()` adds the computed `current` flag.
- `expire()` is a guarded state change: the UPDATE only matches a notice that has not ended, so it reports whether this call ended it, also under concurrency. A notice that has not started gets its start moved to the same moment, so it never shows and the window check of the schema still holds.
- The level reaches SQL as a parameter from `NoticeLevel`, never as a literal.

Tests: [`tests/notices/database.php`](../../tests/notices/database.php), against the real schema through the real Db service.
