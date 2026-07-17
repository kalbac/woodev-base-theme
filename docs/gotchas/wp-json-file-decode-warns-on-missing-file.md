# `wp_json_file_decode()` is not a silent read — it warns before returning null

> Discovered s3 (17.07.2026) triaging the Codex finding on PR #1. Reproduced in wp-env against the real WP 7.0.1, not taken on the reviewer's word.

## The trap

`wp_json_file_decode()` is WP canon for reading a JSON file and the obvious replacement for `file_get_contents()` + `json_decode()` (it also dodges a PHPCS filesystem warning). It returns `null` for a file it cannot read — which reads like a quiet, guard-free API. It is not: WP core calls `wp_trigger_error()` **before** returning null (`wp-includes/functions.php`).

So this, which looks total and safe:

```php
$decoded = wp_json_file_decode( $path, [ 'associative' => true ] );
return \is_array( $decoded ) ? $decoded : [];
```

emits, on any front-end request with the file absent:

```
PHP Notice:  wp_json_file_decode(): File  doesn't exist! in /var/www/html/wp-includes/functions.php on line 6170
```

For us the absent file is `assets/dist/.vite/manifest.json` — **the normal state of a fresh checkout**, since `assets/dist/` is gitignored and only `npm run build` creates it. Clone the theme, activate it, and the front page emits a notice.

Note the level: it is a **Notice**, not a Warning (the PR #1 review said "warning"). With `display_errors=stderr` in wp-env it never appears in the HTML — it only shows in the container log, which is exactly why it survived M0's "zero PHP notices" check on a built tree.

## Existence is not enough — there are two diagnostics, not one

Read core's actual implementation (WP 7.0.1, `wp-includes/functions.php:4676-4693`) rather than inferring from the docblock:

```php
$filename = wp_normalize_path( realpath( $filename ) );
if ( ! $filename ) { wp_trigger_error( … "File %s doesn't exist!" ); return null; }
$decoded_file = json_decode( file_get_contents( $filename ), $options['associative'] );
```

Two separate traps fall out of those three lines:

1. `realpath()` returns false for a missing path → `wp_trigger_error()` → **Notice**.
2. Whatever survives goes straight to `file_get_contents()` with **no readability check**. An existing file we cannot open emits `file_get_contents()`'s own **Warning**. `is_file()` is `true` for such a file, so an existence-only guard does not stop it.

Hence the guard is `is_file( $path ) && is_readable( $path )` — the second half is not belt-and-braces, it closes a different hole. (Raised by the Codex critic reviewing the first fix; confirmed against core's source, not accepted on assertion.)

Note also that `realpath()` resolves a **directory** to a truthy path, so a directory reaches `file_get_contents()` and warns "Is a directory". `is_file()` is what excludes that — `file_exists()` would not.

A file replaced between the guard and the decode still warns (TOCTOU). Not worth closing for our own build artifact under the theme directory; it would take an atomic read to fix properly.

## How to apply here

- Guard with `is_file()` **and** `is_readable()` **before** the decode; that is the only thing keeping the "absent manifest means enqueue nothing" contract true (`Assets::read_manifest()`).
- Guarded by `AssetsTest::test_read_manifest_never_decodes_an_absent_manifest` and `…_an_unreadable_manifest` — both assert the decode is **never reached**, because asserting on the return value alone cannot see the diagnostic. The unreadable case self-skips where POSIX bits don't apply (Windows, root); ubuntu CI is where it really runs.
- Generally: before treating any `wp_*` reader as silent-on-failure, read core's implementation. Returning `null` is not evidence that it stayed quiet.
- To reproduce: `mv woodev-base-theme/assets/dist/.vite/manifest.json /tmp/`, request the front page, then `docker logs --since 6s <wp-env wordpress container>`.

## Related

- [[vite-css-entry-is-not-imported-by-the-js-entry]] — the other PR #1 finding, same file
- [[wp-env-config-constants-persist]] — wp-env's `display_errors=stderr` is why this needs the container log
