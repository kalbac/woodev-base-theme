# M1-02: Templates & Template Parts — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Session roles (per AGENTS.md):** Opus 4.8 orchestrator, Sonnet 5 subagent workers, Codex critic review before merge.
> **Read first:** `AGENTS.md`, spec §4 and §7, `docs/GOTCHAS.md` — especially [[qa-gates-cover-less-than-they-claim]] and [[tailwind-v4-layer-precedence]].

**Goal:** The full classic template hierarchy from spec §7, with two header variants, two footer variants, an optional right sidebar, widget areas, and accessible navigation — a theme that renders a real blog properly instead of the M0 placeholder.

**Architecture:** Templates stay thin; decisions live in PHP classes under `Woodev\Theme\Base\Templates`. Variant selection and sidebar visibility go through one small resolver class each, reading `theme_mod` values with sane defaults — M1-04 will add the Customizer UI on top of the *same* settings, so this plan must not hard-code the choice anywhere a template can see it. Markup is Basecoat component classes plus Tailwind utilities; anything repeated three times is promoted to an adapter class per spec §5.

**Tech Stack:** PHP 8.1+, WordPress classic template hierarchy, `get_template_part()`, Basecoat CSS, Alpine.js for the mobile-nav disclosure, Lucide icons via `woodev_base_icon()` from M1-01.

**Prerequisites:** PR #3 (M1-01) merged — this plan calls `woodev_base_icon()` throughout. Docker running for integration and e2e.

**Git:** branch `feat/m1-templates` off `main`. Commit after every task.

---

## Scope note, read before starting

This plan is deliberately ordered so the theme renders at every commit. Tasks 1–3 build the machinery with no visible change; Tasks 4–8 replace markup. Do not reorder: the templates in Tasks 5–8 call helpers that Tasks 1–3 create, and a worker starting at Task 6 will find undefined functions.

**Out of scope, deliberately:** the Customizer UI (M1-04 — this plan reads the settings, it does not expose them), the scheme switcher button (M1-05 — the header variants leave a documented slot for it), and the 8 style packs (M1-03).

---

### Task 1: Widget areas and the footer menu

Sidebar and footer columns are widget areas per spec §7, and the `footer` menu joins the existing `primary`.

**Files:**
- Modify: `woodev-base-theme/inc/Setup.php`
- Modify: `tests/php/Unit/SetupTest.php`
- Modify: `tests/integration/Integration/SetupTest.php`

- [ ] **Step 1: Write the failing integration test**

Integration first here, not unit: `register_sidebar()` normalises and stores its arguments, and what matters is what WordPress ends up with. Add to `tests/integration/Integration/SetupTest.php`:

```php
	public function test_sidebar_and_footer_widget_areas_are_registered(): void {
		global $wp_registered_sidebars;

		self::assertArrayHasKey( 'sidebar-1', $wp_registered_sidebars );
		self::assertArrayHasKey( 'footer-1', $wp_registered_sidebars );
		self::assertArrayHasKey( 'footer-2', $wp_registered_sidebars );
		self::assertArrayHasKey( 'footer-3', $wp_registered_sidebars );
	}

	public function test_both_nav_menus_are_registered(): void {
		$menus = get_registered_nav_menus();

		self::assertArrayHasKey( 'primary', $menus );
		self::assertArrayHasKey( 'footer', $menus );
	}
```

Note `test_primary_nav_menu_is_registered` already exists; the second method above supersedes it — delete the old one rather than keeping both.

- [ ] **Step 2: Run it and watch it fail**

```bash
npm run wp:test:start && npm run test:integration
```

Expected: FAIL — `Failed asserting that an array has the key 'sidebar-1'`.

- [ ] **Step 3: Implement**

In `Setup.php`, add `widgets_init` to `register()`:

```php
	public function register(): void {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );
		add_action( 'widgets_init', [ $this, 'register_widget_areas' ] );
	}
```

and add the method plus the `footer` menu. Register the footer columns in a loop — three near-identical `register_sidebar()` calls is the DRY violation the project's own rules call out at three occurrences:

```php
	/**
	 * Register the sidebar and footer widget areas.
	 */
	public function register_widget_areas(): void {
		register_sidebar(
			[
				'id'            => 'sidebar-1',
				'name'          => __( 'Sidebar', 'woodev-base-theme' ),
				'description'   => __( 'Shown beside blog, archive and single-post content when the sidebar layout is active.', 'woodev-base-theme' ),
				'before_widget' => '<section id="%1$s" class="wtb-widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="wtb-widget__title">',
				'after_title'   => '</h2>',
			]
		);

		for ( $column = 1; $column <= 3; $column++ ) {
			register_sidebar(
				[
					'id'   => 'footer-' . $column,
					/* translators: %d: footer column number. */
					'name' => \sprintf( __( 'Footer column %d', 'woodev-base-theme' ), $column ),
					'before_widget' => '<section id="%1$s" class="wtb-widget %2$s">',
					'after_widget'  => '</section>',
					'before_title'  => '<h2 class="wtb-widget__title">',
					'after_title'   => '</h2>',
				]
			);
		}
	}
```

In `setup()`, extend the menu registration:

```php
		register_nav_menus(
			[
				'primary' => __( 'Primary Menu', 'woodev-base-theme' ),
				'footer'  => __( 'Footer Menu', 'woodev-base-theme' ),
			]
		);
```

- [ ] **Step 4: Update the unit test**

`tests/php/Unit/SetupTest.php` asserts `register_nav_menus` was called with exactly `[ 'primary' => 'Primary Menu' ]`. That assertion is now wrong — update it to both menus. Do NOT loosen it to `Mockery::type( 'array' )`; pinning the exact slugs is the point (a renamed slug silently orphans every assigned menu).

- [ ] **Step 5: Run both suites**

```bash
composer test:unit && npm run test:integration
```

Expected: both green.

- [ ] **Step 6: Mutation-check**

Change `'sidebar-1'` to `'sidebar-x'` in `Setup.php` → the integration test must go red. Change the `for` loop bound to `2` → red. Restore, confirm green. A widget area registered under the wrong id is invisible in the admin and silently drops the user's widgets, so this needs to be caught.

- [ ] **Step 7: Commit**

```bash
git add woodev-base-theme/inc/Setup.php tests/php/Unit/SetupTest.php tests/integration/Integration/SetupTest.php
git commit -m "feat(templates): register the sidebar, footer columns and footer menu"
```

---

### Task 2: The layout resolver

One class answers "which header variant, which footer variant, does this view get a sidebar". Templates ask it; nothing else decides. M1-04 will write the same `theme_mod`s from the Customizer without touching this class.

**Files:**
- Create: `woodev-base-theme/inc/Templates/Layout.php`
- Create: `tests/php/Unit/Templates/LayoutTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Templates;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Templates\Layout;
use Woodev\Theme\Base\Tests\Unit\TestCase;

final class LayoutTest extends TestCase {

	public function test_header_variant_defaults_to_inline(): void {
		Functions\when( 'get_theme_mod' )->alias( static fn( string $key, $default = false ) => $default );

		self::assertSame( 'inline', Layout::header_variant() );
	}

	public function test_header_variant_reads_the_theme_mod(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'centered' );

		self::assertSame( 'centered', Layout::header_variant() );
	}

	/**
	 * An unknown stored value must not reach get_template_part(): a stale or
	 * hand-edited theme_mod would otherwise ask for a part file that does not
	 * exist and render nothing at all.
	 */
	public function test_an_unknown_header_variant_falls_back_to_the_default(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'does-not-exist' );

		self::assertSame( 'inline', Layout::header_variant() );
	}

	public function test_footer_variant_defaults_to_simple_and_validates(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'nonsense' );

		self::assertSame( 'simple', Layout::footer_variant() );
	}

	public function test_sidebar_is_off_by_default(): void {
		Functions\when( 'get_theme_mod' )->alias( static fn( string $key, $default = false ) => $default );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'is_page' )->justReturn( false );

		self::assertFalse( Layout::has_sidebar() );
	}

	/**
	 * A sidebar setting of 'right' with an empty widget area would render an
	 * empty column and shrink the content for nothing.
	 */
	public function test_sidebar_requires_widgets_to_be_present(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'is_page' )->justReturn( false );

		self::assertFalse( Layout::has_sidebar() );
	}

	/**
	 * Spec §7: the sidebar applies to blog/archive/single contexts. A static
	 * page is a layout the author controls with blocks; a sidebar bolted onto it
	 * fights the page's own design.
	 */
	public function test_pages_never_get_the_sidebar(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'is_page' )->justReturn( true );

		self::assertFalse( Layout::has_sidebar() );
	}

	public function test_sidebar_shows_on_a_single_post_when_enabled_and_filled(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'is_page' )->justReturn( false );

		self::assertTrue( Layout::has_sidebar() );
	}
}
```

- [ ] **Step 2: Run and watch it fail**

```bash
composer test:unit -- --filter LayoutTest
```

Expected: FAIL — class not found.

- [ ] **Step 3: Check the autoloader handles the subdirectory**

`inc/autoload.php` maps `Woodev\Theme\Base\*` to `inc/*.php`. `Templates\Layout` must resolve to `inc/Templates/Layout.php`. Read the autoloader and confirm it splits on the namespace separator; if it does not, fix it and add a case to `tests/php/Unit/AutoloadTest.php` **before** continuing — a silently unautoloadable class will look like a template bug later.

- [ ] **Step 4: Implement**

```php
<?php
/**
 * Layout decisions: header/footer variants and sidebar visibility.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Templates;

/**
 * Single source of truth for which layout a view gets.
 *
 * Templates ask this class; they never read theme_mods directly. M1-04 adds the
 * Customizer controls that write the same settings, so the validation here is
 * what keeps a stale or hand-edited value from reaching get_template_part().
 */
final class Layout {

	public const HEADER_VARIANTS = [ 'inline', 'centered' ];
	public const FOOTER_VARIANTS = [ 'simple', 'columns' ];

	/**
	 * Which header part to load.
	 */
	public static function header_variant(): string {
		return self::validate( (string) get_theme_mod( 'header_variant', 'inline' ), self::HEADER_VARIANTS, 'inline' );
	}

	/**
	 * Which footer part to load.
	 */
	public static function footer_variant(): string {
		return self::validate( (string) get_theme_mod( 'footer_variant', 'simple' ), self::FOOTER_VARIANTS, 'simple' );
	}

	/**
	 * Whether the current view renders the sidebar column.
	 */
	public static function has_sidebar(): bool {
		if ( 'right' !== get_theme_mod( 'sidebar_position', 'none' ) ) {
			return false;
		}

		// An empty widget area would render a column of nothing and narrow the
		// content for no reason.
		if ( ! is_active_sidebar( 'sidebar-1' ) ) {
			return false;
		}

		// Spec §7: blog, archive and single contexts only. Static pages are
		// author-composed layouts and keep the full width.
		return ! is_page();
	}

	/**
	 * @param string   $value    Stored value.
	 * @param string[] $allowed  Permitted values.
	 * @param string   $fallback Value to use when $value is not permitted.
	 */
	private static function validate( string $value, array $allowed, string $fallback ): string {
		return \in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
```

- [ ] **Step 5: Run to green, then mutation-check**

```bash
composer test:unit -- --filter LayoutTest
```

Expected: PASS, 8 tests.

Then: delete the `is_active_sidebar()` guard → `test_sidebar_requires_widgets_to_be_present` must go red. Replace `validate()`'s `in_array` with `return $value;` → both "unknown value" tests must go red. Restore and confirm green.

- [ ] **Step 6: Commit**

```bash
git add woodev-base-theme/inc/Templates/Layout.php tests/php/Unit/Templates/LayoutTest.php
git commit -m "feat(templates): add the layout resolver for variants and sidebar"
```

---

### Task 3: Wire the resolver into header.php and footer.php

**Files:**
- Modify: `woodev-base-theme/header.php`, `woodev-base-theme/footer.php`
- Create: `woodev-base-theme/template-parts/header/inline.php`, `centered.php`
- Create: `woodev-base-theme/template-parts/footer/simple.php`, `columns.php`

- [ ] **Step 1: Reduce header.php to the document shell**

Everything above `<main>` that is *not* variant-specific stays; the branding/nav block moves out:

```php
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="wtb-skip-link" href="#wtb-content"><?php esc_html_e( 'Skip to content', 'woodev-base-theme' ); ?></a>

<?php get_template_part( 'template-parts/header/' . \Woodev\Theme\Base\Templates\Layout::header_variant() ); ?>

<main id="wtb-content" class="wtb-container">
```

The skip link is a WCAG 2.1 AA requirement (spec §9) and belongs to the document, not to a variant — putting it in the variants would mean maintaining it twice and losing it the moment someone adds a third.

- [ ] **Step 2: Same for footer.php**

```php
</main>

<?php get_template_part( 'template-parts/footer/' . \Woodev\Theme\Base\Templates\Layout::footer_variant() ); ?>

<?php wp_footer(); ?>
</body>
</html>
```

- [ ] **Step 3: Write the two header variants**

Both render branding + `primary` menu + a documented slot for the M1-05 scheme switcher. `inline` puts branding left and nav right on one row; `centered` stacks branding above centred nav. Use `wp_nav_menu()` with a `Walker` only if the dropdown markup demands it — try Basecoat's dropdown classes with the default walker first and keep the walker for Task 4 if needed.

Both must contain, verbatim, the comment marking the switcher slot so M1-05 has an unambiguous insertion point:

```php
			<?php // M1-05 inserts the colour-scheme switcher here (spec §6). ?>
```

- [ ] **Step 4: Write the two footer variants**

`simple` — site name, `footer` menu, copyright line. `columns` — the three `footer-N` widget areas in a responsive grid, then the same bottom bar. Each widget area is wrapped in `is_active_sidebar()` so an unused column collapses instead of leaving a gap.

Use `number_format_i18n()` and count-agnostic phrasing per AGENTS.md; there is no `_n()` in this theme.

- [ ] **Step 5: Verify in a browser, not by reading**

```bash
npm run build && npm run wp:start
```

Open the site, then switch variants by hand since the Customizer does not exist yet:

```bash
npx wp-env run cli wp theme mod set header_variant centered
npx wp-env run cli wp theme mod set footer_variant columns
```

Confirm both variants render and the skip link appears on first Tab press. Spec §9 requires browser evidence for UI claims — reading the PHP is not evidence.

Reset afterwards:

```bash
npx wp-env run cli wp theme mod remove header_variant
npx wp-env run cli wp theme mod remove footer_variant
```

- [ ] **Step 6: Commit**

```bash
git add woodev-base-theme/header.php woodev-base-theme/footer.php woodev-base-theme/template-parts/
git commit -m "feat(templates): split header and footer into selectable variants"
```

---

### Task 4: Navigation — desktop dropdown and mobile drawer

**Files:**
- Create: `woodev-base-theme/template-parts/header/navigation.php`
- Create: `woodev-base-theme/inc/Templates/Menu_Walker.php` (only if Task 3 Step 3 showed the default walker cannot produce Basecoat's dropdown markup)
- Create: `tests/e2e/navigation.spec.mjs`

Spec §5: Basecoat's own JS drives Basecoat components; Alpine owns theme-level behaviour like the mobile nav. Do not reimplement Basecoat's dropdown in Alpine.

- [ ] **Step 1: Write the e2e test first**

This is the level where nav behaviour is provable. Cover, at minimum: the mobile toggle opens the drawer, `Escape` closes it and returns focus to the toggle, and `Tab` from the toggle does not escape the open drawer. Keyboard behaviour is the part that regresses silently.

- [ ] **Step 2: Watch it fail, implement, watch it pass**

```bash
npm run e2e -- navigation.spec.mjs
```

- [ ] **Step 3: Confirm progressive enhancement**

Spec §5 makes this mandatory: with JS disabled the menu must still be reachable. Run the same page with JavaScript off and confirm the nav is visible and navigable, rather than a toggle that does nothing.

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(templates): accessible desktop and mobile navigation"
```

---

### Task 5: Content parts and pagination

**Files:**
- Create: `woodev-base-theme/template-parts/content/content.php`, `content-excerpt.php`, `content-none.php`, `pagination.php`

- [ ] **Step 1: Write the three content parts**

`content.php` — full post for singular views. `content-excerpt.php` — title, meta, excerpt, read-more for lists. `content-none.php` — the empty state, including a search form when the view was a search.

Every part escapes on output and uses `post_class()` / `the_ID()` per WP canon.

- [ ] **Step 2: Write the pagination part**

`the_posts_pagination()` with `prev_text`/`next_text` carrying `woodev_base_icon( 'chevron-left' )` / `chevron-right`. The icons are decorative — the visible text is the accessible name, so do **not** pass a `label`, or screen readers announce both.

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(templates): content parts and icon pagination"
```

---

### Task 6: The template hierarchy

**Files:**
- Modify: `woodev-base-theme/index.php`
- Create: `single.php`, `page.php`, `archive.php`, `search.php`, `404.php`, `comments.php`

- [ ] **Step 1: Write each template thin**

Each is a loop plus `get_template_part()` calls plus the sidebar block. The sidebar block repeats across index/archive/single/search — that is four occurrences, so per AGENTS.md's DRY-at-three rule extract it to `template-parts/sidebar.php` and call that, rather than pasting the `Layout::has_sidebar()` check four times.

- [ ] **Step 2: Integration-test template resolution**

```php
	public function test_the_expected_template_is_chosen_for_each_view(): void {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		self::assertStringEndsWith( 'single.php', get_single_template() );
	}
```

Cover single, page, archive, search and 404. This catches a misnamed file, which otherwise silently falls back to `index.php` and looks merely "a bit wrong".

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(templates): the classic template hierarchy per spec §7"
```

---

### Task 7: e2e smoke across templates, light and dark

Spec §9 requires visual checks of core templates in both schemes.

- [ ] **Step 1: Extend `tests/e2e/smoke.spec.mjs`**

Visit home, a single post, a page, an archive, a search result and a 404. Assert each renders its own template's landmark rather than the 404 fallback, and that no console errors occurred. Run each in light and dark by toggling the `.dark` class on `<html>` — the switcher UI does not exist until M1-05.

- [ ] **Step 2: Seed content so the tests are meaningful**

An archive with one post proves little. Seed via wp-cli in the Playwright global setup so pagination has more than one page.

- [ ] **Step 3: Commit and run everything**

```bash
composer phpcs && composer phpstan && composer test:unit
npm run format && npm run lint:js && npm run test:js && npm run build
npm run test:integration && npm run e2e
git commit -m "test(templates): e2e smoke across the hierarchy in both schemes"
```

---

### Task 8: Review and PR

- [ ] **Step 1: Full gate set** — all eight commands above, all exit 0. Check the PHPCS scanned-file count grew with the new templates; if it did not, the files are outside the ruleset's scope (see [[qa-gates-cover-less-than-they-claim]]).
- [ ] **Step 2: Codex critic** — mandatory per AGENTS.md, never self-certify. Recipe in [[codex-cli-dies-silently]]. Ask specifically about escaping, i18n on every user-facing string, and the a11y of the nav.
- [ ] **Step 3: Fix, then re-review the fixes** — the re-critic rule.
- [ ] **Step 4: PR** with the mutation evidence, not just the green bar.

---

## Self-review against the spec

**Coverage.** §7's templates (Task 6), template parts (Tasks 3, 5), header ×2 and footer ×2 variants (Task 3), sidebar option backed by a widget area (Tasks 1, 2, 6), footer widget areas (Task 1), both menus (Task 1), and the nav/pagination components (Tasks 4, 5). §9's a11y and dual-scheme visual checks are Tasks 4 and 7.

**Deliberately deferred.** Components from §7 that no template here renders — dialog, tabs, accordion, alert, badge, form controls beyond the search form — land with M1-03's adapter work, where they can be styled and demonstrated together instead of being added blind.

**Known risk.** Task 3 Step 3 leaves the walker question open, because whether Basecoat's dropdown markup works with WordPress's default walker cannot be settled by reading either project's docs — it needs the markup in a browser. The plan says try the simple path first and escalate to a custom walker only if it fails, rather than pre-committing to a `Walker_Nav_Menu` subclass nobody may need. **The plan is thinner here on purpose; a worker hitting this should report what the default walker produced rather than improvising a walker silently.**
