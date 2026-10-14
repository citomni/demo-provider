# language/

Translations of the package for the `txt` service of citomni/infrastructure. PHP code reads them through `$this->app->txt->get()`, templates through `$txt()`, both with the package slug as layer.

## Layout

- `language/<language>/<file>.php`. `<language>` is `locale.language` from the app's config, like `da`. `<file>` is the name that callers pass, without `.php`; it may contain subfolders, like `admin/notices`.
- The `txt` service finds the files of a layer at `vendor/<package slug>/language/`, so the folder needs no registration in the Registry.
- One file per feature or area, shared by its Controllers, Commands and templates. The site notices feature uses `notices.php`.
- Every language folder holds the same files with the same keys. Ship every language the package supports; for another language, the English default at the call site is shown.
- An app cannot override a package text in its own language files, because a lookup reads only the layer it names. An app that needs other wording renders its own template, with its own texts, through a route override.

## File format

- The PHP file header, then `return [ ... ];` with a flat map of keys to strings. Comments group the keys.
- Keys in snake_case, prefixed by purpose where that helps: `label_`, `flash_`, `reason_`.
- Placeholders are `%UPPERCASE%` tokens, filled from the `$vars` argument. The keys of `$vars` are uppercased first, so `['max' => 500]` fills `%MAX%`.

## Lookup

- `get(string $key, string $file, string $layer = 'app', string $default = '', array $vars = []): string`.
- A missing file, a missing key, an empty string or a non-scalar value returns `$default`, with the placeholders applied. Each such lookup writes a `txt.missing_key` or `txt.non_scalar_value` line to `txt.jsonl` in the app's `log.path` directory, and a missing file adds one `txt.missing_file` line per request.
- A malformed file name or layer throws `\InvalidArgumentException`. That is an error in the code, not missing content. A file name consists of letters, digits, `/`, `_` and `-`, and starts and ends with a letter or digit.
- Each file is loaded once per request and kept in memory.

## Rules

- Pass the package slug and an English default at every call: `$this->app->txt->get('flash_scheduled', 'notices', 'citomni/demo-provider', 'The notice was scheduled.')`. The default keeps the page readable when a key is missing, and the log line names the key.
- Results carry codes, not translated text. Operations and Policies return codes, and the adapter or template translates them by key, like `'reason_' . $reason` for the reason codes of `SchedulingPolicy`. Text that the domain produces itself, like the body of a mail, may use `txt`; see [`src/Operation/`](../src/Operation/README.md).
- A date pattern is a translation (`date_pattern`), because languages order the parts of a date differently. Templates pass it to `$dt()`.
- Texts are plain text and are rendered escaped. A text that must carry markup is rendered raw with `{{{ }}}`, and then its placeholders must never carry user input.

## Example

- [`en/notices.php`](en/notices.php) and [`da/notices.php`](da/notices.php): the texts of the site notices feature, for the templates, the flash messages and the reason codes of `SchedulingPolicy` and of the form conversion in `NoticeManagementController`.
