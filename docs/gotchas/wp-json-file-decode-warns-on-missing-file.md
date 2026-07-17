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

## How to apply here

- Guard with `is_file()` **before** the decode; that is the only thing keeping the "absent manifest means enqueue nothing" contract true (`Assets::read_manifest()`).
- Guarded by `AssetsTest::test_read_manifest_never_decodes_an_absent_manifest` — it asserts the decode is **never reached**, because asserting on the return value alone cannot see the notice.
- Generally: before treating any `wp_*` reader as silent-on-failure, read core's implementation. Returning `null` is not evidence that it stayed quiet.
- To reproduce: `mv woodev-base-theme/assets/dist/.vite/manifest.json /tmp/`, request the front page, then `docker logs --since 6s <wp-env wordpress container>`.

## Related

- [[vite-css-entry-is-not-imported-by-the-js-entry]] — the other PR #1 finding, same file
- [[wp-env-config-constants-persist]] — wp-env's `display_errors=stderr` is why this needs the container log
