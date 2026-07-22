# M1-05: Colour-scheme switcher + no-FOUC head script — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use `- [ ]` checkboxes.
>
> **Roles (AGENTS.md):** Opus orchestrator, Sonnet workers, Codex `gpt-5.6-sol` critic before merge with a re-critic on the fixes.
> **Read first:** `AGENTS.md`, spec §6 (Colour scheme), `docs/GOTCHAS.md` — especially [[basecoat-tokens-are-un-layered]], [[tailwind-v4-layer-precedence]], [[playwright-browser-newpage-skips-config]], [[x-trap-focus-move-is-async]].

**Goal:** Finish M1. Ship the two scheme settings, a `<head>` script that resolves the scheme before first paint (no flash), and the sun/moon switcher into the slot both header variants already reserve.

**Architecture:** The scheme is decided in three places that must agree. PHP puts the admin's choice on `<html>` as a class (so a no-JS visitor gets it server-side); a tiny synchronous inline script in `<head>` refines it from `localStorage` before the body paints; the switcher is an Alpine control that writes `localStorage` and flips the same class. `system` deliberately sets NO class, which lets a generated `prefers-color-scheme` block in the token CSS decide with JS off.

**Tech Stack:** PHP 8.1+, WordPress Customizer + `language_attributes` filter, Alpine.js, the existing `Icons` helper (sun/moon are already vendored — see `scripts/copy-icons.mjs`), Vitest, Playwright.

**Prerequisites:** M1-04 merged (`e480b3a`). Docker running. `npm ci` done.

**Git:** branch `feat/m1-05-scheme-switcher` off `main`. Commit after every task.

---

## Plan-writing note, carried from M1-04

M1-04's plan ran 2200 lines and its *predictions* were the weakest part: it named which test a mutation would turn red, and was wrong more than once because PHPUnit and Vitest both abort a loop at the first failing assertion. **This plan predicts the FACT a mutation exposes, not the test name.** If your observed failure differs from the prediction, report it — do not adjust the plan to match.

Also carried: three separate comments in M1-04 asserted something about WP core, a browser, or PHP that turned out to be false, and each was settled in one command. If you are about to write "WordPress does X" or "browsers do Y" in a comment, **verify it first** (`npx wp-env run cli grep …` reads real core source).

---

## Verified findings — read before starting

1. **Both header variants already reserve the slot.** `template-parts/header/{inline,centered}.php` each carry `// M1-05 inserts the colour-scheme switcher here (spec §6).` inside `.wtb-header__actions`. Replace the comment, do not restructure the header.
2. **The icons are already vendored.** `scripts/copy-icons.mjs` ships `sun` and `moon`, both labelled `(M1-05)`. Use `Icons::get( 'sun', [ 'size' => 20 ] )`; it returns `''` for an unknown slug rather than throwing.
3. **Basecoat's dark variant keys off `html.dark`** — `@custom-variant dark (&:is(html.dark *))` in `dist/base/base.css`. So a `dark:` utility only applies when the class is literally present. Our own token block is what a class-less `system` visitor gets, which is why Task 2 exists and why its limitation is documented rather than hidden.
4. **Our tokens are un-layered and imported after Basecoat** — that is what lets them win. The generated `prefers-color-scheme` block MUST stay un-layered too ([[basecoat-tokens-are-un-layered]]).
5. **M1-04 left a known dev-mode limitation** (Vite injects pack CSS at module execution, after the inline block). The head script in this plan is not affected: it sets a CLASS, not custom properties.

## Scope

| Setting | Type | Values | Default |
|---|---|---|---|
| `color_scheme_default` | select | `system` / `light` / `dark` | `system` |
| `color_scheme_toggle` | checkbox | on / off | on |

Resolution order (spec §6): stored visitor choice (only when the toggle is on) → admin default → `system` follows `prefers-color-scheme` and keeps following it live.

**Out of scope:** any per-post or per-page scheme override; a third "auto by time of day" mode; animating the transition. M2 owns anything WooCommerce.

---

### Task 1: `Scheme` — settings, resolution, and the class

**Files:** create `woodev-base-theme/inc/Scheme.php`, `tests/php/Unit/SchemeTest.php`; modify `woodev-base-theme/inc/Customizer/Customizer.php`, `tests/php/Unit/Customizer/CustomizerTest.php`.

Follow the M1-04 pattern exactly: one validator per setting, used BOTH as the Customizer `sanitize_callback` and as the front-end resolver. Read `inc/Customizer/Settings.php` first — `sanitize_*` naming, `mixed` parameters, fail-closed on non-scalars.

- [ ] **Step 1: Write the failing test** — `tests/php/Unit/SchemeTest.php`:

```php
<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Scheme;

final class SchemeTest extends TestCase {

	public function test_the_default_scheme_is_system_and_validates(): void {
		self::assertSame( 'system', Scheme::sanitize_default( 'system' ) );
		self::assertSame( 'dark', Scheme::sanitize_default( 'dark' ) );
		self::assertSame( 'system', Scheme::sanitize_default( 'sepia' ) );
		self::assertSame( 'system', Scheme::sanitize_default( new \stdClass() ) );
		self::assertSame( 'system', Scheme::sanitize_default( [ 'dark' ] ) );
	}

	public function test_the_toggle_is_a_real_boolean(): void {
		self::assertTrue( Scheme::sanitize_toggle( true ) );
		self::assertTrue( Scheme::sanitize_toggle( '1' ) );
		self::assertFalse( Scheme::sanitize_toggle( '' ) );
		self::assertFalse( Scheme::sanitize_toggle( '0' ) );
		self::assertFalse( Scheme::sanitize_toggle( new \stdClass() ) );
	}

	/**
	 * `system` sets NO class on purpose: that is what lets the generated
	 * prefers-color-scheme block decide for a visitor with JS disabled. An
	 * explicit admin choice IS a class, so it survives with JS off too.
	 */
	public function test_only_an_explicit_default_becomes_a_class(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'system' );
		self::assertSame( '', Scheme::html_class() );

		Functions\when( 'get_theme_mod' )->justReturn( 'dark' );
		self::assertSame( 'dark', Scheme::html_class() );

		Functions\when( 'get_theme_mod' )->justReturn( 'light' );
		self::assertSame( 'light', Scheme::html_class() );
	}
}
```

- [ ] **Step 2: Run it, watch it fail.** `composer test:unit -- --filter SchemeTest`. Expected: class not found.

- [ ] **Step 3: Implement `Scheme`** with `SCHEMES = [ 'system', 'light', 'dark' ]`, `sanitize_default( mixed ): string`, `sanitize_toggle( mixed ): bool`, `default(): string`, `toggle_enabled(): bool`, `html_class(): string`.

`sanitize_toggle()` must accept what a WP checkbox actually submits. Do not guess the truthy set — WP core's own checkbox sanitizers treat `'1'`/`true` as on and everything else as off; mirror that and say so in the docblock.

- [ ] **Step 4: Register both settings** in `Customizer::configure()`, in a new `woodev_base_colors` entry (the section already exists — do NOT create a second Colors section). Add a `add_checkbox()` helper alongside `add_select()`/`add_number()`. Extend `CustomizerTest::expected_settings()` with both; the existing loop tests then cover them for free — that is the point of how they were written.

- [ ] **Step 5: Run the tests.** `composer test:unit`. Expected: green, and the two new settings picked up by `test_every_setting_has_a_callable_sanitize_callback` and `test_the_sanitize_callbacks_reject_junk` without either test being edited.

- [ ] **Step 6: Mutation.** Drop `'sanitize_callback'` from `add_checkbox()`. The FACT to observe: at least one test reports a setting whose callback is missing or not callable. Restore, re-run green.

- [ ] **Step 7:** `composer phpcs && composer phpstan`, then commit.

---

### Task 2: The `prefers-color-scheme` fallback in the generated tokens

Without this, a `system` visitor with JS disabled gets light tokens no matter what their OS says.

**Files:** modify `scripts/lib/build-tokens-lib.mjs`, `tests/js/build-tokens.test.mjs`, and the committed `src/css/tokens.generated.css` (via `npm run tokens`).

- [ ] **Step 1: Write the failing Vitest.** Assert `buildTokensCss( tokens )`:
  - contains `@media (prefers-color-scheme: dark)`;
  - inside it, scopes to `:root:not(.light):not(.dark)`;
  - repeats every dark value (loop `tokens.colors.dark`, do not spot-check one);
  - still contains no `@layer` anywhere (the existing un-layered assertion must keep passing).

- [ ] **Step 2: Run it, watch it fail.**

- [ ] **Step 3: Implement.** Emit after the `.dark` block:

```js
@media (prefers-color-scheme: dark) {
  /* JS-disabled `system` visitors only. An explicit admin default or a stored
   * visitor choice puts .light/.dark on <html>, and either one excludes this
   * block — so it never fights a decision that has already been made. */
  :root:not(.light):not(.dark) {
${varsBlock(tokens.colors.dark, '    ')}
  }
}
```

- [ ] **Step 4:** `npm run tokens`, confirm LF endings (bytes, not `grep $'\r'` — that misreports in this repo's Git Bash), `npm run test:js`, `npx prettier --check .`.

- [ ] **Step 5: Mutation.** Change the selector to a plain `:root`. The FACT to observe: the assertion that the block is excluded by an explicit class fails. Restore.

- [ ] **Step 6:** Commit. **Document the limitation in the same commit body:** Basecoat's `dark:` utilities key off `html.dark`, so a class-less `system` visitor with JS off gets our dark TOKENS but not Basecoat's dark utility variants. With JS on the class is always set, so this affects no-JS `system` only.

---

### Task 3: The no-FOUC head script

**Files:** modify `woodev-base-theme/inc/Scheme.php`, `woodev-base-theme/inc/Theme.php`; create `tests/php/Unit/SchemeHeadTest.php`.

Two hooks:
- `language_attributes` filter → append `Scheme::html_class()` so the class is in the server HTML.
- `wp_head` priority 1 → print the resolver script. It must be synchronous and inline; anything deferred paints first and the flash is exactly what this task exists to prevent.

The script, kept deliberately tiny and dependency-free:

```js
(function () {
  var root = document.documentElement;
  var stored = null;
  if (TOGGLE) { try { stored = localStorage.getItem('wtb-scheme'); } catch (e) {} }
  var scheme = stored === 'light' || stored === 'dark' ? stored : DEFAULT;
  if (scheme === 'system') {
    root.classList.remove('light', 'dark');
    return;
  }
  root.classList.remove('light', 'dark');
  root.classList.add(scheme);
})();
```

`TOGGLE` and `DEFAULT` are injected from PHP as `wp_json_encode()` output — never string-concatenated. `localStorage` access is wrapped because it **throws**, not returns null, in Safari private mode and when cookies are blocked; an exception here would abort the script and leave the page unstyled-by-class.

- [x] **Step 1: Write the failing test.** Assert: the printed markup contains `<script`; the values come through `wp_json_encode` (set the default to `dark` and assert `"dark"` appears quoted); with the toggle OFF the script contains no `localStorage` read at all; and the script is hooked at `wp_head` priority 1.

- [x] **Step 2: Run it, watch it fail.**

- [x] **Step 3: Implement.** Escape with care: this is a `<script>` block, so `esc_html()` is wrong (it would entity-encode `&&` and `<`). Follow the M1-04 precedent — the values are JSON-encoded closed-set scalars, and `phpcs.xml.dist` already carries a scoped escaping deviation for `InlineStyles.php`; if this file needs the same, **scope it the same way** (an `exclude-pattern` for this file, never a global `customEscapingFunctions` entry — that blinds the sniff theme-wide, which is exactly the finding Codex raised in M1-04).

- [x] **Step 4: Run the tests.**

- [x] **Step 5: Mutation.** Remove the `try`/`catch` around `localStorage`. The FACT to observe: no test fails — this is a browser-only failure mode and unit tests cannot see it, so it must be pinned in Task 6's e2e instead. Record that here rather than pretending the unit test covers it.

- [x] **Step 6:** Lint and commit.

---

### Task 4: The switcher control

**Files:** create `woodev-base-theme/template-parts/header/scheme-toggle.php`; modify both header variants, `src/css/adapter/index.css`, `src/js/app.js` if a named Alpine component is cleaner than inline `x-data`.

- [ ] **Step 1:** Render nothing when `Scheme::toggle_enabled()` is false. A hidden-but-present control is worse than none.

- [ ] **Step 2: Progressive enhancement, same pattern as the nav.** The button is `display: none` in the adapter layer by default and revealed only once Alpine adds an enhancement class — a JS-disabled visitor must never see a control that cannot work. Read `.wtb-nav__toggle` in `src/css/adapter/index.css` and mirror it, including WHY the display rule lives in the adapter layer rather than as a utility ([[tailwind-v4-layer-precedence]]).

- [ ] **Step 3: Accessibility is the substance of this task, not a checkbox.**
  - It is a `<button type="button">`, not a link.
  - It needs an accessible name that describes the ACTION and updates with state (e.g. "Switch to dark theme" / "Switch to light theme"); both strings are translated with the `woodev-base-theme` text domain.
  - Both icons ship in the markup; CSS shows exactly one. The icons are decorative (`Icons::get()` with no `label` sets `aria-hidden`), because the button already has its name.
  - Visible focus ring, matching `.wtb-nav__toggle:focus-visible`.
  - Respect `prefers-reduced-motion` if you add any transition.

- [ ] **Step 4: Behaviour.** Click → flip the class on `<html>`, write `localStorage['wtb-scheme']`, update the accessible name. While the resolved scheme is `system`, a `matchMedia('(prefers-color-scheme: dark)')` listener keeps following the OS live (spec §6). Remove the listener when the visitor makes an explicit choice.

- [ ] **Step 5:** `npm run build`, then verify **in a browser** — a UI claim without browser evidence is not evidence (AGENTS.md). Report what you actually saw in both schemes.

- [ ] **Step 6:** `npm run lint:js && npx prettier --check .`, `composer phpcs`, commit.

---

### Task 5: Integration coverage

**Files:** create `tests/integration/Integration/SchemeTest.php`.

Against real WordPress: both settings registered with their defaults and callable sanitize callbacks; WP's own `->sanitize()` pipeline rejects a bogus scheme; `language_attributes()` output carries the class for an explicit default and carries neither `light` nor `dark` for `system`; `wp_head` output contains the script; with the toggle off the script contains no `localStorage` read.

- [ ] **Step 1:** Write it. **Step 2:** Run it — and if it passes first try, prove the tests are actually collected by removing the file and comparing counts (M1-04's Task 7 had exactly this situation). **Step 3:** Commit.

---

### Task 6: e2e

**Files:** modify `tests/e2e/theme-mods.spec.mjs` (it OWNS every theme_mod mutation — do not create a second mutating spec, read its header), and `tests/e2e/lib/theme-mod.mjs` for the guards.

Cover:
1. **Toggle flips the scheme and it sticks across a navigation** (the localStorage path).
2. **No flash.** The class must be present at first paint, not added later. Assert on the very first evaluation after `goto` with no waiting — and if that turns out to be untestable in Playwright without racing, say so rather than writing a test that passes for the wrong reason. A `page.addInitScript` that records `document.documentElement.className` at `DOMContentLoaded` is one honest way.
3. **`system` follows the OS**, using the project's `colorScheme` emulation on the `{ page }` fixture — never `browser.newPage()` ([[playwright-browser-newpage-skips-config]]).
4. **Toggle off ⇒ no control rendered** and a stored visitor choice is NOT honoured.
5. **The `localStorage`-throws path** from Task 3's Step 5: block storage (`context.addInitScript` overriding `localStorage.getItem` to throw) and assert the page still resolves a scheme instead of dying.

- [ ] Mutations, each stated as a FACT to observe: removing the `try`/`catch` breaks (5); removing the server-side class leaves (2) with a class-less first paint; setting the toggle off without removing the control breaks (4).

---

### Task 7: Gate, critic, PR

- [ ] **Full gate on the branch:** `composer phpcs`, `composer phpstan`, `composer test:unit`, `npm run test:js`, `npm run lint:js`, `npx prettier --check .`, `npm run build`, `npm run test:integration`, `npm run e2e`. Record every count.
- [ ] **Codex critic**, chunked under ~15 KB, foreground, stdin closed, `CODEX_HOME=/c/Users/maksi/.codex-review-clean`; smoke-test with `"Reply with exactly: CODEX_OK"` first. Tell it not to read `.claude/skills/**`. Name out-of-chunk guards in every prompt. Chunks: (a) `Scheme` + Customizer registration + unit tests, (b) the head script + the generated CSS fallback, (c) the switcher markup/CSS/JS + e2e.
  Ask specifically about: XSS in the inline script (this is the one place we emit executable JS), the no-JS path, a11y of the button, and whether the three places that decide the scheme can ever disagree.
- [ ] **Findings presented verbatim; fix accepted ones with a test proven red first; then a RE-CRITIC on the fix diff.** Never self-certify a fix — M1-04's re-critic found a defect inside a fix, and the second re-critic found another one inside that.
- [ ] **Push, open the PR**, summarising: the two settings, the resolution order, the no-JS limitation from Task 2, gate counts, and the Codex findings with their resolutions.
- [ ] **After merge, re-run the FULL gate on merged `main`** — s5's focus-trap bug was green on both branches and red only after the merge.

---

## Definition of done

M1 is complete when this merges: icons (M1-01), templates (M1-02), style packs (M1-03), Customizer (M1-04) and the scheme switcher (M1-05) are all on `main` with a green gate. The remaining M1-era tail is **dev-mode integration coverage** (deferred since s3, still unblocked), which is a separate small plan and does not gate M2.
