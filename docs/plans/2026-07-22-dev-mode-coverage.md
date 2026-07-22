# Dev-mode coverage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give `Assets::enqueue()`'s dev branch the integration and browser coverage it has never had, so the "Vite ran but no styles reached the page" defect class cannot come back silently.

**Architecture:** Two independent parts. Part A adds a second PHPUnit configuration whose bootstrap defines `WOODEV_BASE_DEV`, running against the existing `.wp-env.test.json` environment — the constant is never set through wp-env's `config` key, which leaks into both environments and is never removed again. Part B adds a third, permanently-dev wp-env environment plus a Playwright config that owns a live Vite dev server, and one spec that asserts *computed style*, not markup.

**Tech Stack:** PHPUnit 9.6 (WP core test suite), wp-env, Playwright, Vite 6.

**Spec:** `docs/specs/2026-07-22-dev-mode-coverage.md`

---

## Facts established before writing this plan

Verified against the running containers and the real files — do not re-derive, and do not "correct" them from memory:

1. **Script modules print in `wp_footer`, not `wp_head`.** `wp-includes/class-wp-script-modules.php::add_hooks()` picks the position from `wp_is_block_theme()`. Woodev Base is a classic/hybrid theme (no `templates/*.html`), so `print_enqueued_script_modules` is hooked to **`wp_footer`**. Only block themes get `print_head_enqueued_script_modules` on `wp_head`. WP under test is 7.0.2.
2. **`wp_enqueue_scripts` fires from `wp_head`.** Core hooks `wp_enqueue_scripts()` to `wp_head` at priority 1. So a test must render `wp_head` **and then** `wp_footer`, and assert against the concatenation. Rendering only `wp_footer` enqueues nothing and passes vacuously.
3. **Stylesheets (`wp_enqueue_style`) print in `wp_head`.** That is where the production `assets/dist` `<link>` appears.
4. **Vite's dev server CORS is an allow-list, not `true`.** `vite.config.mjs` sets `cors: { origin: ['http://localhost:8888', 'http://localhost:8889'] }` deliberately (see its comment). A new origin must be added there or the modules are blocked.
5. **Ports in use:** `:8888` dev (`.wp-env.json`), `:8889` its tests env, `:8890` integration (`.wp-env.test.json`). `:8892` is free.
6. **`tests/e2e/global-setup.mjs` seeds via `npx wp-env run cli`** — the *default* config, i.e. `:8888`. It must not be reused by the dev-mode Playwright config, which would seed the wrong site.
7. **PSR-4 in `tests/integration/composer.json`** maps `Woodev\Theme\Base\Tests\Integration\` → `Integration/`, so a `Integration/DevMode/` subdirectory needs **no** Composer change — but the existing testsuite globs `./Integration` recursively and would pick the dev tests up, so it must exclude them.
8. **`--font-sans` is the load-bearing token.** Every colour token we ship is byte-identical to Basecoat's default; `--font-sans` is not (Basecoat's vega sets "Geist Sans", ours is the system stack from `src/tokens/tokens.mjs`). It is the only cheap probe that detects "our CSS did not arrive or did not win".
9. **`.btn` with no `data-size` is `h-9` in vega** (`node_modules/basecoat-css/dist/styles/vega.css:24-26`) → **36px** computed height. `.btn` is a component rule shipped by the pack, not a utility, so it applies to an element created at runtime — the probe does not depend on page content.

## A note on TDD order for this plan

`Assets::enqueue()`'s dev branch already exists and is correct. These are
**characterization tests over existing behaviour**, so an honest red phase is not
available: written correctly, they pass on the first run. Faking a red phase by
writing a deliberately wrong assertion first proves nothing.

The discipline that replaces it, and it is not optional (AGENTS.md, "Definition
of done"): **every guard is mutation-tested**. A test does not count as written
until the mutation named in its task has been applied, the test observed red, and
the mutation reverted. Report the actual failure message, not "it went red".

## File structure

| Path | Status | Responsibility |
|---|---|---|
| `tests/integration/bootstrap-dev.php` | create | Define `WOODEV_BASE_DEV`, then delegate to `bootstrap.php` |
| `tests/integration/phpunit.dev.xml.dist` | create | PHPUnit config for the dev-mode suite only |
| `tests/integration/phpunit.xml.dist` | modify | Exclude `Integration/DevMode` from the production suite |
| `tests/integration/Integration/DevMode/AssetsDevModeTest.php` | create | Dev-branch assertions |
| `tests/integration/Integration/AssetsProductionTest.php` | create | Mirror-image production assertions |
| `.wp-env.dev-mode.json` | create | Third wp-env environment, permanently in dev mode, port 8892 |
| `vite.config.mjs` | modify | Add `http://localhost:8892` to the CORS allow-list |
| `playwright.dev.config.mjs` | create | Dev-mode Playwright project; owns the Vite dev server |
| `tests/e2e-dev/dev-mode.spec.mjs` | create | The one browser assertion: the page is really styled |
| `package.json` | modify | `test:integration:dev`, `wp:dev-mode:start`, `wp:dev-mode:stop`, `e2e:dev` |
| `docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md` | modify | Its "no e2e coverage by decision" paragraph becomes false |
| `docs/CURRENT-STATE.md` | modify | Close the deferred item |

---

## Task 1: The dev-mode PHPUnit harness

**Files:**
- Create: `tests/integration/bootstrap-dev.php`
- Create: `tests/integration/phpunit.dev.xml.dist`
- Modify: `tests/integration/phpunit.xml.dist`
- Modify: `package.json`

- [ ] **Step 1: Write the dev bootstrap**

Create `tests/integration/bootstrap-dev.php`:

```php
<?php
/**
 * Integration bootstrap for the dev-mode suite.
 *
 * Identical to bootstrap.php except that WOODEV_BASE_DEV is defined before
 * WordPress boots, so Assets::enqueue() takes its dev branch.
 *
 * Why a whole second bootstrap and not a wp-env config constant: wp-env writes
 * `config` keys into BOTH the dev and the tests environment, and appends them to
 * wp-config.php without ever removing them again — dropping the config file and
 * restarting leaves the constant in place, and `--update` does not help either
 * (docs/gotchas/wp-env-config-constants-persist.md). The integration environment
 * would silently stay in dev mode for every later run. A define here is scoped to
 * one PHPUnit process and leaves no residue.
 *
 * The constant cannot be unset once set, which is also why this is a separate
 * process rather than a test-level toggle.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

define( 'WOODEV_BASE_DEV', true );

require_once __DIR__ . '/bootstrap.php';
```

- [ ] **Step 2: Write the dev PHPUnit config**

Create `tests/integration/phpunit.dev.xml.dist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!--
	The dev-mode half of the integration suite. Same PHPUnit 9.6 schema and the
	same flags as phpunit.xml.dist; the only differences are the bootstrap (which
	defines WOODEV_BASE_DEV) and the directory it collects tests from.

	Two configs rather than one because WOODEV_BASE_DEV cannot be undefined once
	defined, so the two branches of Assets::enqueue() cannot be covered in one
	PHPUnit process.
-->
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	bootstrap="bootstrap-dev.php"
	colors="true"
	failOnWarning="true"
	failOnRisky="true"
	failOnEmptyTestSuite="true"
	convertDeprecationsToExceptions="true">
	<testsuites>
		<testsuite name="integration-dev">
			<directory suffix="Test.php">./Integration/DevMode</directory>
		</testsuite>
	</testsuites>
</phpunit>
```

- [ ] **Step 3: Exclude the dev directory from the production suite**

`tests/integration/phpunit.xml.dist` globs `./Integration` recursively, so without
this the dev tests also run in production mode and fail. Change the `<testsuite>`
element to:

```xml
		<testsuite name="integration">
			<directory suffix="Test.php">./Integration</directory>
			<!--
				DevMode/ runs only under phpunit.dev.xml.dist, whose bootstrap
				defines WOODEV_BASE_DEV. Collected here it would assert dev-server
				URLs against a production-mode WordPress and fail.
			-->
			<exclude>./Integration/DevMode</exclude>
		</testsuite>
```

- [ ] **Step 4: Add the npm script**

In `package.json`, directly after `"test:integration"`, add:

```json
    "test:integration:dev": "wp-env run cli --config=.wp-env.test.json --env-cwd=wp-content/woodev-tests vendor/bin/phpunit -c phpunit.dev.xml.dist"
```

- [ ] **Step 5: Prove the harness is wired before writing assertions against it**

Create a placeholder-free first test, `tests/integration/Integration/DevMode/AssetsDevModeTest.php`, containing only the harness guard for now:

```php
<?php
/**
 * Dev-mode asset integration tests.
 *
 * The unit suite (tests/php/Unit/AssetsTest.php) pins the URLs we hand to
 * WordPress with wp_enqueue_script_module() mocked away. These assert what a
 * real WordPress actually printed.
 *
 * @package Woodev\Theme\Base\Tests\Integration\DevMode
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration\DevMode;

use WP_UnitTestCase;

final class AssetsDevModeTest extends WP_UnitTestCase {

	/**
	 * Guards the harness itself: every other assertion in this file is
	 * meaningless if the bootstrap failed to define the constant or to switch
	 * themes, and both failures are silent.
	 */
	public function test_the_harness_is_in_dev_mode_with_our_theme_active(): void {
		self::assertTrue( \defined( 'WOODEV_BASE_DEV' ), 'WOODEV_BASE_DEV is not defined — wrong bootstrap?' );
		self::assertTrue( WOODEV_BASE_DEV );
		self::assertSame( 'woodev-base-theme', get_stylesheet() );
	}
}
```

- [ ] **Step 6: Run the dev suite**

Run: `npm run test:integration:dev`
Expected: PASS, 1 test.

If it errors with "Could not find the WordPress test suite", the environment is
not up — run `npm run wp:test:start` first.

- [ ] **Step 7: Run the production suite and confirm the exclusion works**

Run: `npm run test:integration`
Expected: PASS, the same test count as before this task (the DevMode directory
must NOT be collected). If the count grew by one, Step 3's `<exclude>` is wrong.

- [ ] **Step 8: Commit**

```bash
git add tests/integration/bootstrap-dev.php tests/integration/phpunit.dev.xml.dist tests/integration/phpunit.xml.dist tests/integration/Integration/DevMode/AssetsDevModeTest.php package.json
git commit -m "test: add a dev-mode PHPUnit harness for the integration suite"
```

---

## Task 2: The dev-mode assertions

**Files:**
- Modify: `tests/integration/Integration/DevMode/AssetsDevModeTest.php`

- [ ] **Step 1: Add the head+footer renderer and the assertions**

Append these members to the class created in Task 1 (keep the harness test):

```php
	/**
	 * Render a full front-end request's head and footer, concatenated.
	 *
	 * Both halves are required and neither is optional:
	 *   - wp_head fires wp_enqueue_scripts (core hooks it there at priority 1),
	 *     so without it nothing is ever enqueued and every assertion below
	 *     passes vacuously;
	 *   - script modules print in wp_footer, not wp_head. Core picks the
	 *     position from wp_is_block_theme() (class-wp-script-modules.php,
	 *     add_hooks()) and this is a classic/hybrid theme, so
	 *     print_enqueued_script_modules() is hooked to wp_footer. Stylesheets
	 *     still print in wp_head.
	 */
	private static function render_front_end_assets(): string {
		ob_start();
		do_action( 'wp_head' );
		do_action( 'wp_footer' );

		return (string) ob_get_clean();
	}

	public function test_the_three_dev_server_modules_are_printed(): void {
		$html = self::render_front_end_assets();

		self::assertStringContainsString( 'http://localhost:5173/@vite/client', $html );
		self::assertStringContainsString( 'http://localhost:5173/src/css/packs/vega.css', $html );
		self::assertStringContainsString( 'http://localhost:5173/src/js/app.js', $html );
	}

	/**
	 * The pack CSS is the one that fails silently: Vite declares it a separate
	 * Rollup input, so app.js never imports it, and omitting it renders a 200
	 * with working JavaScript and no Tailwind, Basecoat or tokens at all.
	 * See docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md.
	 */
	public function test_the_pack_css_is_a_script_module_not_a_stylesheet(): void {
		$html = self::render_front_end_assets();

		self::assertMatchesRegularExpression(
			'#<script[^>]+type=["\']module["\'][^>]+src=["\']http://localhost:5173/src/css/packs/vega\.css["\']#',
			$html,
			'The dev server serves the CSS entry as a JS module; a <link rel=stylesheet> would apply nothing.'
		);
	}

	public function test_no_built_asset_is_referenced_in_dev_mode(): void {
		self::assertStringNotContainsString( 'assets/dist', self::render_front_end_assets() );
	}
```

- [ ] **Step 2: Run the dev suite**

Run: `npm run test:integration:dev`
Expected: PASS, 4 tests.

If `test_the_pack_css_is_a_script_module_not_a_stylesheet` fails, print the
captured HTML (`echo $html;`) and compare the real attribute order and quoting
before adjusting the pattern — WordPress's own printer decides both.

- [ ] **Step 3: Mutation-test the guard**

In `woodev-base-theme/inc/Assets.php`, temporarily neuter the dev branch by
changing the guard in `enqueue()`:

```php
		if ( false && \defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV ) {
```

Run: `npm run test:integration:dev`
Expected: FAIL — the three URL assertions red, `assets/dist` assertion red or
skipped depending on whether a build exists.

Record the actual failure message in the task report, then revert `Assets.php`
exactly (`git checkout -- woodev-base-theme/inc/Assets.php`) and re-run to
confirm green.

- [ ] **Step 4: Second mutation — drop only the pack CSS**

In `enqueue_dev()`, temporarily comment out the `woodev-base-style` line.

Run: `npm run test:integration:dev`
Expected: FAIL on `test_the_three_dev_server_modules_are_printed` and
`test_the_pack_css_is_a_script_module_not_a_stylesheet`; the `@vite/client` and
`app.js` assertions stay green, which is the point — the guard is specific.

Revert and re-run to confirm green.

- [ ] **Step 5: Commit**

```bash
git add tests/integration/Integration/DevMode/AssetsDevModeTest.php
git commit -m "test: assert the dev branch prints three dev-server modules and no dist asset"
```

---

## Task 3: The production mirror test

**Files:**
- Create: `tests/integration/Integration/AssetsProductionTest.php`

The dev assertions alone cannot tell "the dev config works" from "both configs
boot the same mode". The mirror is what makes the pair non-vacuous.

- [ ] **Step 1: Write the test**

Create `tests/integration/Integration/AssetsProductionTest.php`:

```php
<?php
/**
 * Production-mode asset integration tests.
 *
 * The mirror image of Integration/DevMode/AssetsDevModeTest.php. Neither file
 * means much alone: each would also pass if both PHPUnit configs booted the same
 * mode. Together they prove the two configs really do exercise the two branches
 * of Assets::enqueue().
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_UnitTestCase;

final class AssetsProductionTest extends WP_UnitTestCase {

	public function test_the_harness_is_not_in_dev_mode(): void {
		self::assertFalse(
			\defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV,
			'This suite must run WITHOUT the dev constant — is it collecting Integration/DevMode?'
		);
	}

	/**
	 * See AssetsDevModeTest::render_front_end_assets() for why both halves are
	 * rendered: wp_head fires wp_enqueue_scripts, script modules print in
	 * wp_footer, stylesheets print in wp_head.
	 */
	private static function render_front_end_assets(): string {
		ob_start();
		do_action( 'wp_head' );
		do_action( 'wp_footer' );

		return (string) ob_get_clean();
	}

	public function test_no_dev_server_url_is_referenced(): void {
		self::assertStringNotContainsString( 'localhost:5173', self::render_front_end_assets() );
	}

	/**
	 * assets/dist is gitignored, so a fresh checkout has no manifest and the
	 * theme correctly enqueues nothing. Skipping is the honest outcome there —
	 * failing would report a missing build as a broken theme.
	 */
	public function test_built_assets_are_enqueued_from_the_manifest(): void {
		$manifest = get_template_directory() . '/assets/dist/.vite/manifest.json';

		if ( ! is_file( $manifest ) ) {
			self::markTestSkipped( 'No Vite build present — run `npm run build` to cover this path.' );
		}

		self::assertStringContainsString( 'assets/dist', self::render_front_end_assets() );
	}
}
```

- [ ] **Step 2: Build, so the skip path is not the one that runs**

Run: `npm run build`
Expected: exits 0, `woodev-base-theme/assets/dist/.vite/manifest.json` exists.

- [ ] **Step 3: Run the production suite**

Run: `npm run test:integration`
Expected: PASS, previous count + 3.

- [ ] **Step 4: Mutation-test the mirror**

In `Assets::enqueue()`, temporarily force the dev branch:

```php
		if ( true || ( \defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV ) ) {
```

Run: `npm run test:integration`
Expected: FAIL on `test_no_dev_server_url_is_referenced` and
`test_built_assets_are_enqueued_from_the_manifest`.

Revert, re-run, confirm green.

- [ ] **Step 5: Commit**

```bash
git add tests/integration/Integration/AssetsProductionTest.php
git commit -m "test: mirror the dev-mode asset assertions in production mode"
```

---

## Task 4: The dev-mode wp-env environment

**Files:**
- Create: `.wp-env.dev-mode.json`
- Modify: `vite.config.mjs`
- Modify: `package.json`

- [ ] **Step 1: Write the environment config**

Create `.wp-env.dev-mode.json`:

```json
{
  "core": null,
  "phpVersion": "8.1",
  "themes": ["./woodev-base-theme"],
  "testsEnvironment": false,
  "port": 8892,
  "config": {
    "WOODEV_BASE_DEV": true
  }
}
```

Why a whole environment rather than toggling the constant on `:8888`: wp-env
appends `config` constants to wp-config.php and never removes them, so toggling
would leave the main dev site permanently in dev mode
(`docs/gotchas/wp-env-config-constants-persist.md`). Here the persistence is the
intended behaviour — this environment exists to be in dev mode forever.

- [ ] **Step 2: Allow the new origin through Vite's CORS**

`vite.config.mjs` restricts the dev server to an explicit origin list; `:8892`
would otherwise be refused and the page would load no modules at all. Change the
`server.cors` block to:

```js
    // Only the local wp-env origins may pull dev-server assets. `cors: true`
    // would reflect any origin, letting any site a developer visits read this
    // server's source over CORS while it runs.
    // 8892 is the dev-mode e2e environment (.wp-env.dev-mode.json).
    cors: { origin: ['http://localhost:8888', 'http://localhost:8889', 'http://localhost:8892'] },
```

- [ ] **Step 3: Add the npm scripts**

In `package.json`, after `"wp:test:stop"`, add:

```json
    "wp:dev-mode:start": "wp-env start --config=.wp-env.dev-mode.json",
    "wp:dev-mode:stop": "wp-env stop --config=.wp-env.dev-mode.json"
```

- [ ] **Step 4: Start it and verify the constant landed**

Run: `npm run wp:dev-mode:start`
Expected: exits 0, reports WordPress at `http://localhost:8892`.

Then verify the constant is really in that install's wp-config.php — read the
file, do **not** probe with `wp eval`, whose output is preceded by wp-env echoing
the command itself, so a grep for the probe string matches the echo and reports
success unconditionally (`docs/gotchas/wp-env-config-constants-persist.md`):

```bash
npx wp-env run cli --config=.wp-env.dev-mode.json bash -c "grep -c WOODEV_BASE_DEV /var/www/html/wp-config.php"
```

Expected: `1`.

If Git Bash mangles the container path, prefix with `MSYS_NO_PATHCONV=1`.

- [ ] **Step 5: Confirm the main environment was not touched**

```bash
npx wp-env run cli bash -c "grep -c WOODEV_BASE_DEV /var/www/html/wp-config.php"
```

Expected: `0` (grep exits 1 with a count of 0 — that is the pass condition, not
an error).

- [ ] **Step 6: Commit**

```bash
git add .wp-env.dev-mode.json vite.config.mjs package.json
git commit -m "test: add a permanently dev-mode wp-env environment on port 8892"
```

---

## Task 5: The dev-mode e2e spec

**Files:**
- Create: `playwright.dev.config.mjs`
- Create: `tests/e2e-dev/dev-mode.spec.mjs`
- Modify: `package.json`

- [ ] **Step 1: Write the Playwright config**

Create `playwright.dev.config.mjs`:

```js
// playwright.dev.config.mjs
//
// The dev-mode e2e run. Separate from playwright.config.mjs on purpose:
//   - it targets the permanently-dev wp-env environment on :8892, not :8888;
//   - it owns a live Vite dev server, which the main gate must not depend on;
//   - it deliberately has NO globalSetup. tests/e2e/global-setup.mjs seeds
//     through `npx wp-env run cli` with the DEFAULT config, i.e. :8888 — reusing
//     it here would seed the wrong site. This spec needs no fixtures: it asserts
//     computed style on the front page.
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e-dev',
  use: {
    baseURL: 'http://localhost:8892',
  },
  reporter: [['list']],
  webServer: {
    command: 'npm run dev',
    // @vite/client is served by the dev server itself and needs no build.
    url: 'http://localhost:5173/@vite/client',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
```

- [ ] **Step 2: Write the spec**

Create `tests/e2e-dev/dev-mode.spec.mjs`:

```js
// tests/e2e-dev/dev-mode.spec.mjs
import { expect, test } from '@playwright/test';
import { tokens } from '../../src/tokens/tokens.mjs';

// Everything here uses the { page } fixture, never browser.newPage(), which
// skips the project config including baseURL —
// docs/gotchas/playwright-browser-newpage-skips-config.md.

test('the dev-mode site really is in dev mode', async ({ page }) => {
  // Harness guard. Without it, a misconfigured environment would run every
  // assertion below against an ordinary production page, where they would
  // mostly still pass — the built CSS produces the same computed values.
  const response = await page.goto('/');
  expect(response.status()).toBe(200);

  await expect(page.locator('link[rel="stylesheet"][href*="assets/dist"]')).toHaveCount(0);
  await expect(page.locator('script[src*="localhost:5173/src/css/packs/"]')).toHaveCount(1);
});

test('the dev server actually styles the page', async ({ page }) => {
  // The defect this exists to catch: the pack CSS entry is a separate Rollup
  // input that app.js never imports, so a dev page can be a 200 with working
  // JavaScript and no Tailwind, Basecoat or tokens at all. Every PHP test stays
  // green through that — the script tag is present, the styles are not.
  // docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md
  //
  // Hence: computed style, never markup. Two probes because the two halves of
  // the bundle fail independently.
  await page.goto('/');

  // Wait for the CSS module to execute — in dev, Vite injects the styles from
  // JavaScript, so they are not present at first paint.
  await expect
    .poll(() =>
      page.evaluate(() =>
        getComputedStyle(document.documentElement).getPropertyValue('--font-sans').trim(),
      ),
    )
    .not.toBe('');

  const result = await page.evaluate(() => {
    // Probe 1 — our token layer arrived AND beat Basecoat's.
    // --font-sans is the only cheap probe that can tell those apart: every
    // colour token we ship is byte-identical to Basecoat's shadcn default,
    // while Basecoat's vega pack sets --font-sans to "Geist Sans" and ours is
    // the system stack. Compare through the browser's own font-family
    // serialization, because the build re-quotes idents ('Segoe UI' -> "Segoe UI").
    const canonicalizeFontFamily = (fontFamilyString) => {
      const probe = document.createElement('div');
      probe.style.fontFamily = fontFamilyString;
      return probe.style.fontFamily;
    };

    // Probe 2 — Basecoat's component layer arrived and its @apply compiled.
    // `.btn` is a component rule shipped by the pack, not a utility, so it
    // applies to an element created here and does not depend on page content.
    const button = document.createElement('button');
    button.className = 'btn';
    document.body.append(button);
    const buttonHeight = getComputedStyle(button).height;
    button.remove();

    return {
      fontSans: canonicalizeFontFamily(
        getComputedStyle(document.documentElement).getPropertyValue('--font-sans').trim(),
      ),
      buttonHeight,
    };
  });

  const expectedFontSans = await page.evaluate((fontFamilyString) => {
    const probe = document.createElement('div');
    probe.style.fontFamily = fontFamilyString;
    return probe.style.fontFamily;
  }, tokens.fonts.sans);

  expect(result.fontSans).toBe(expectedFontSans);
  // vega's `.btn:not([data-size])` is `h-9` = 2.25rem = 36px
  // (node_modules/basecoat-css/dist/styles/vega.css).
  expect(result.buttonHeight).toBe('36px');
});
```

- [ ] **Step 3: Add the npm script**

In `package.json`, after `"e2e"`, add:

```json
    "e2e:dev": "playwright test --config=playwright.dev.config.mjs"
```

- [ ] **Step 4: Run it**

Ensure the environment is up (`npm run wp:dev-mode:start`), then run:
`npm run e2e:dev`
Expected: PASS, 2 tests. Playwright starts the Vite dev server itself.

If the page loads unstyled, check the browser console for a CORS refusal first —
that means Task 4 Step 2 did not take effect (the dev server must be restarted to
pick up a `vite.config.mjs` change; `reuseExistingServer` will happily reuse a
stale one).

- [ ] **Step 5: Mutation-test the guard**

In `Assets::enqueue_dev()`, temporarily comment out the `woodev-base-style`
module line — the exact defect from PR #1.

Run: `npm run e2e:dev`
Expected: FAIL. The first test fails on the `script[src*=…packs/]` count; the
second fails at the `expect.poll` on `--font-sans` (empty, because no CSS was
ever injected).

Revert, re-run, confirm green. Report the real failure output.

- [ ] **Step 6: Commit**

```bash
git add playwright.dev.config.mjs tests/e2e-dev/dev-mode.spec.mjs package.json
git commit -m "test: pin that dev mode actually styles the page in a browser"
```

---

## Task 6: Documentation

**Files:**
- Modify: `docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md`
- Modify: `docs/CURRENT-STATE.md`

- [ ] **Step 1: Correct the gotcha**

That file's "How to apply here" section currently states:

> **Dev mode has no e2e coverage by decision** (s3): it is a developer-only path, and covering it in CI needs a second wp-env environment with the constant plus a live dev server. The unit test pins our side of the contract; the Vite side (CSS-entry-as-JS-module) is external and only a Vite major could move it. Re-verify manually then — recipe in [[wp-env-config-constants-persist]].

Replace that bullet with:

```markdown
- **Dev mode is covered at all three levels since s7.** The unit test pins the
  URLs with WordPress mocked; `tests/integration/Integration/DevMode/AssetsDevModeTest.php`
  (run via `npm run test:integration:dev`) asserts what a real WordPress printed,
  including that the CSS entry is a script module and not a stylesheet; and
  `tests/e2e-dev/dev-mode.spec.mjs` (`npm run e2e:dev`, against the permanently-dev
  `.wp-env.dev-mode.json` environment on :8892) asserts the page is actually
  styled in a browser — computed style, because the failure mode here is a
  present script tag and absent styles. The s3 decision to skip e2e was reversed
  in s7: the second environment turned out to cost one JSON file and one entry in
  Vite's CORS allow-list.
```

- [ ] **Step 2: Update CURRENT-STATE**

In `docs/CURRENT-STATE.md`, under "Deferred, tracked", strike through the
"Dev mode has no integration/e2e coverage" bullet the way the other resolved
items in that section are struck through, noting it was closed in s7 and naming
the two new test entry points.

- [ ] **Step 3: Commit**

```bash
git add docs/gotchas/vite-css-entry-is-not-imported-by-the-js-entry.md docs/CURRENT-STATE.md
git commit -m "docs: record that dev mode is now covered at all three levels"
```

---

## Task 7: Full gate and PR

**Files:** none created; this is verification.

- [ ] **Step 1: Run every gate**

```bash
composer phpcs
composer phpstan
composer test:unit
npm run test:js
npm run build
npm run test:integration
npm run test:integration:dev
npm run e2e
npm run e2e:dev
```

Expected: all green. Record the counts (phpcs files, unit, vitest, integration,
integration-dev, e2e, e2e-dev) — they go into the session log.

(Verified against `composer.json` when this plan was written: the scripts are
`phpcs`, `phpstan`, `test:unit` — there is no `composer test`.)

- [ ] **Step 2: Confirm the main e2e run was not disturbed**

`npm run e2e` targets `:8888` and must be unaffected by anything in this branch.
A killed e2e run can leave theme_mods mutated on `:8888`; if `theme-mods.spec.mjs`
fails, clear them before believing the failure.

- [ ] **Step 3: Push and open the PR**

```bash
git push -u origin feat/dev-mode-coverage
gh pr create --title "Dev-mode coverage: integration + one browser spec" --body "..."
```

The PR body states what each part guards, the mutation results from Tasks 2, 3
and 5 verbatim, and the full gate counts.

**Merging is Maksim's call — open the PR and stop.**

---

## Definition of done

1. `npm run test:integration:dev` green; its guards mutation-verified (Task 2).
2. `npm run test:integration` green including the mirror; mutation-verified (Task 3).
3. `npm run e2e:dev` green; mutation-verified (Task 5).
4. `npm run e2e` unchanged and green.
5. phpcs, phpstan L8, unit, vitest, build green.
6. The gotcha no longer claims dev mode is uncovered; CURRENT-STATE's deferred item closed.
7. Codex critic passed on the diff, with a re-critic on any fixes.
