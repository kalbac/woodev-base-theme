# The Vite CSS entry is a separate Rollup input — the JS entry never imports it

> Discovered s3 (17.07.2026) triaging the Codex finding on PR #1 (`enqueue_dev()` shipped no CSS). Verified against the running dev server, not reasoned about.

## The trap

`vite.config.mjs` declares two Rollup inputs:

```js
input: { app: 'src/js/app.js', style: 'src/css/app.css' }
```

They are **independent graphs**. `src/js/app.js` contains no `import './app.css'`, so nothing links them. Consequences:

1. **Production is fine by accident of the manifest.** `Assets::enqueue()` looks up the `src/css/app.css` entry explicitly and enqueues its hashed file. It works because the code asks for both entries by name.
2. **Dev mode is not.** `enqueue_dev()` has no manifest to consult, so it must ask the dev server for both entries by name too. Enqueuing only `@vite/client` + `app.js` — the obvious dev wiring, copied from every Vite-with-a-single-entry tutorial — renders the theme with **no Tailwind, no Basecoat, no tokens**. It fails silently: the page is a 200 with working JS and unstyled HTML.

## The non-obvious part: dev CSS is a script module, not a stylesheet

Vite's dev server serves `/src/css/app.css` with `Content-Type: text/javascript` — a JS module that injects a `<style data-vite-dev-id>` tag and carries the HMR hot-context. Verified:

```
$ curl -sD- http://localhost:5173/src/css/app.css | head -2
HTTP/1.1 200 OK
Content-Type: text/javascript
```

So dev mode enqueues it with `wp_enqueue_script_module()`, not `wp_enqueue_style()`. A `<link rel=stylesheet>` to that URL would load JavaScript as CSS and apply nothing (`?direct` would return raw CSS but forfeits HMR — the point of dev mode).

## How to apply here

- `enqueue_dev()` enqueues three modules in order: `@vite/client`, `src/css/app.css`, `src/js/app.js`. Guarded by a unit test that pins all three URLs (`AssetsTest::test_dev_mode_enqueues_vite_client_css_and_js_from_the_dev_server`, isolated process — `WOODEV_BASE_DEV` can't be undefined once set).
- **Dev mode is covered at all three levels since s7.** The s3 decision to skip
  everything above unit was reversed: the second environment turned out to cost
  one JSON file and one entry in Vite's CORS allow-list.
  - *unit* — `AssetsTest` pins the three URLs with WordPress mocked.
  - *integration* — `tests/integration/Integration/DevMode/AssetsDevModeTest.php`,
    run by `npm run test:integration:dev`, asserts what a real WordPress printed,
    including that the CSS entry is a **script module and not a stylesheet**.
    Its mirror, `Integration/AssetsProductionTest.php`, asserts the opposite in
    production mode; neither means much without the other.
  - *e2e* — `tests/e2e-dev/dev-mode.spec.mjs` (`npm run e2e:dev`, against
    `.wp-env.dev-mode.json` on :8892) asserts **computed style**, because the
    failure mode here is a present script tag and absent styles. Markup
    assertions cannot see this bug; that is the whole point of the file.
- Do **not** "simplify" this by adding `import './app.css'` to `app.js`: that folds CSS into the JS graph, so production would emit the stylesheet twice (once as the `style` entry, once as the app entry's imported CSS).

## Related

- [[wp-env-config-constants-persist]] — how to switch wp-env into dev mode to check this by hand
- [[basecoat-js-entry-is-a-subpath-export]] — the other silent import trap in the same entry file
- [[wp-json-file-decode-warns-on-missing-file]] — the other PR #1 finding, same file
