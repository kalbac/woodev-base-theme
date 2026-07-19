# M1-01: Lucide Icon Helper — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Session roles (per AGENTS.md):** Opus 4.8 orchestrator, Sonnet 5 subagent workers, Codex critic review before merge.
> **Read first:** `AGENTS.md`, `docs/specs/2026-07-17-woodev-base-v1-design.md` §7 and §9, `docs/GOTCHAS.md`.

**Goal:** A server-side PHP helper that inlines Lucide SVG icons into theme markup, shipping only the icons actually used, with correct accessibility semantics for both decorative and meaningful icons.

**Architecture:** Icons are vendored from the `lucide-static` npm package into `woodev-base-theme/assets/static/icons/` by a declarative copy script — the icon list lives in one place and the copy is reproducible and upgradable. At runtime `Woodev\Theme\Base\Icons` reads a file, discards the upstream `<svg>` element's attributes entirely, and re-emits its own wrapper around the untouched inner paths. Re-emitting rather than rewriting means the helper controls every attribute it outputs instead of pattern-matching upstream markup that could change on an upgrade.

**Tech Stack:** PHP 8.1+, `lucide-static` (ISC), PHPUnit 10.5 + Brain\Monkey (unit), `WP_UnitTestCase` under wp-env (integration).

**Why this is its own plan:** M1 in the spec is six independent subsystems. This one is small, has no dependencies, and unblocks two others (navigation chevrons and the mobile-nav toggle in M1-02; the sun/moon scheme switcher in M1-05).

**Prerequisites:** Docker Desktop running for the integration task, Node ≥ 20, PHP 8.1+ and Composer on PATH.

**Git:** work on branch `feat/m1-lucide-icons` off `main` (merge PR #2 first — this plan assumes the integration harness exists). Commit after every task.

---

### Task 1: Vendor the icons reproducibly

**Files:**
- Create: `scripts/copy-icons.mjs`
- Create: `woodev-base-theme/assets/static/icons/*.svg` (generated, committed)
- Create: `woodev-base-theme/assets/static/icons/README.md`
- Modify: `package.json` (devDependency + script)

- [ ] **Step 1: Create the branch and install the source package**

```bash
git checkout main && git pull
git checkout -b feat/m1-lucide-icons
npm i -D lucide-static
```

- [ ] **Step 2: Write the copy script**

The icon list is the single source of truth for what ships. Adding an icon later means adding one line here and re-running the script — never hand-copying a file.

Create `scripts/copy-icons.mjs`:

```js
// scripts/copy-icons.mjs
/**
 * Copies the icons the theme actually uses out of lucide-static.
 * Spec §9: only the icons used ship in the markup — no icon font, no full set.
 * Run: npm run icons
 */
import { copyFile, mkdir, readdir, rm } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SRC = join(ROOT, 'node_modules', 'lucide-static', 'icons');
const DEST = join(ROOT, 'woodev-base-theme', 'assets', 'static', 'icons');

// Every icon the theme references, and where. Keep the comments accurate —
// an icon with no listed consumer should be deleted, not left to rot.
const ICONS = [
  'sun', // scheme switcher, light state (M1-05)
  'moon', // scheme switcher, dark state (M1-05)
  'menu', // mobile nav toggle (M1-02)
  'x', // mobile nav close (M1-02)
  'chevron-down', // dropdown nav (M1-02)
  'chevron-left', // pagination, previous (M1-02)
  'chevron-right', // pagination, next (M1-02)
  'search', // search form (M1-02)
];

await rm(DEST, { recursive: true, force: true });
await mkdir(DEST, { recursive: true });

for (const name of ICONS) {
  await copyFile(join(SRC, `${name}.svg`), join(DEST, `${name}.svg`));
}

const written = (await readdir(DEST)).filter((f) => f.endsWith('.svg'));
if (written.length !== ICONS.length) {
  throw new Error(`Expected ${ICONS.length} icons, wrote ${written.length}`);
}
console.log(`Copied ${written.length} icons to ${DEST}`);
```

The `rm` before the copy is deliberate: removing a name from `ICONS` must remove the file, otherwise dropped icons linger and keep shipping.

- [ ] **Step 3: Wire the script into package.json**

Add to `"scripts"` in `package.json`, after `"tokens"`:

```json
    "icons": "node scripts/copy-icons.mjs",
```

- [ ] **Step 4: Run it and verify the output**

```bash
npm run icons
ls woodev-base-theme/assets/static/icons/
```

Expected: `Copied 8 icons to ...` and exactly 8 `.svg` files listed.

- [ ] **Step 5: Confirm the icons are not gitignored**

The build output `assets/dist/` is ignored but `assets/static/` must be committed — it ships in the release ZIP.

```bash
git check-ignore -v woodev-base-theme/assets/static/icons/sun.svg; echo "exit=$?"
```

Expected: `exit=1` and no output (not ignored). If it prints a rule, fix `.gitignore` so only `assets/dist/` is ignored.

- [ ] **Step 6: Record provenance and license**

Create `woodev-base-theme/assets/static/icons/README.md`:

```markdown
# Vendored Lucide icons

These files are **generated** — do not edit them and do not add files by hand.
Run `npm run icons` after changing the `ICONS` list in `scripts/copy-icons.mjs`.

Source: [Lucide](https://lucide.dev), the icon set Basecoat/shadcn is designed
against. License: ISC (see `LICENSE` in the `lucide-static` package). The ISC
license permits redistribution with the copyright notice, which is why the
notice below travels with the files.

```
ISC License

Copyright (c) for portions of Lucide are held by Cole Bemis 2013-2022 as part of Feather (MIT).
All other copyright (c) for Lucide are held by Lucide Contributors 2022.
```
```

- [ ] **Step 7: Commit**

```bash
git add scripts/copy-icons.mjs package.json package-lock.json woodev-base-theme/assets/static/icons/
git commit -m "build(icons): vendor the eight Lucide icons M1 uses"
```

---

### Task 2: Reject unknown and malformed icon names

Path handling comes first because it is the only part of this helper with a security dimension: the name reaches the filesystem.

**Files:**
- Create: `woodev-base-theme/inc/Icons.php`
- Create: `tests/php/Unit/IconsTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/php/Unit/IconsTest.php`:

```php
<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Icons;

final class IconsTest extends TestCase {

	/**
	 * Points the helper at the real committed icons rather than a fixture: the
	 * files it must parse are the files that ship, and a fixture would let the
	 * two drift.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 3 ) . '/woodev-base-theme' );
		Functions\when( 'esc_attr' )->returnArg();
	}

	/**
	 * @dataProvider provide_rejected_names
	 */
	public function test_rejects_names_that_are_not_plain_icon_slugs( string $name ): void {
		self::assertSame( '', Icons::get( $name ) );
	}

	public static function provide_rejected_names(): array {
		return [
			'traversal'          => [ '../../../wp-config' ],
			'traversal encoded'  => [ '..%2Fwp-config' ],
			'absolute path'      => [ '/etc/passwd' ],
			'nested path'        => [ 'sub/sun' ],
			'null byte'          => [ "sun\0.php" ],
			'uppercase'          => [ 'Sun' ],
			'leading dash'       => [ '-sun' ],
			'trailing dash'      => [ 'sun-' ],
			'double dash'        => [ 'sun--moon' ],
			'empty'              => [ '' ],
			'unknown but valid'  => [ 'definitely-not-an-icon' ],
		];
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

```bash
composer test:unit -- --filter IconsTest
```

Expected: FAIL — `Class "Woodev\Theme\Base\Icons" not found`.

- [ ] **Step 3: Write the minimal implementation**

Create `woodev-base-theme/inc/Icons.php`:

```php
<?php
/**
 * Inline SVG icon helper (Lucide).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

/**
 * Renders vendored Lucide icons as inline SVG.
 */
final class Icons {

	/**
	 * Icon slugs: lowercase words joined by single hyphens, nothing else.
	 * Anything outside this shape never reaches the filesystem.
	 */
	private const NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

	/**
	 * Inner markup per icon, memoised for the request.
	 *
	 * @var array<string, string>
	 */
	private static array $cache = [];

	/**
	 * Build the inline SVG markup for an icon.
	 *
	 * @param string $name Icon slug, e.g. 'chevron-down'.
	 * @return string Markup, or '' when the icon does not exist.
	 */
	public static function get( string $name ): string {
		if ( 1 !== \preg_match( self::NAME_PATTERN, $name ) ) {
			return '';
		}

		$path = self::directory() . '/' . $name . '.svg';

		if ( ! \is_file( $path ) || ! \is_readable( $path ) ) {
			return '';
		}

		return '';
	}

	/**
	 * Absolute path of the vendored icon directory.
	 */
	private static function directory(): string {
		return get_template_directory() . '/assets/static/icons';
	}
}
```

Note `is_readable()` alongside `is_file()`, for the reason recorded in `docs/gotchas/wp-json-file-decode-warns-on-missing-file.md`: existence is not readability, and the two failures are separate.

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test:unit -- --filter IconsTest
```

Expected: PASS, 11 tests.

- [ ] **Step 5: Mutation-check the guard**

A guard that never fires is decoration. Temporarily replace the `preg_match` early return with `if ( false ) {` and re-run.

Expected: the `traversal`, `absolute path` and `nested path` cases now FAIL (they resolve outside the icon directory). Restore the line and confirm green again. If they still pass, the pattern is not doing the work the test claims — stop and investigate before continuing.

- [ ] **Step 6: Commit**

```bash
git add woodev-base-theme/inc/Icons.php tests/php/Unit/IconsTest.php
git commit -m "feat(icons): reject icon names that are not plain slugs"
```

---

### Task 3: Emit the SVG wrapper

**Files:**
- Modify: `woodev-base-theme/inc/Icons.php`
- Modify: `tests/php/Unit/IconsTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `IconsTest.php`, inside the class:

```php
	public function test_emits_our_own_svg_wrapper_not_the_upstream_one(): void {
		$svg = Icons::get( 'sun' );

		self::assertStringStartsWith( '<svg ', $svg );
		self::assertStringEndsWith( '</svg>', $svg );
		self::assertStringContainsString( 'viewBox="0 0 24 24"', $svg );
		self::assertStringContainsString( 'stroke="currentColor"', $svg );
		// The upstream element carries its own classes; we discard the whole
		// opening tag, so none of them may survive into our output.
		self::assertStringNotContainsString( 'lucide', $svg );
		// Exactly one root element — proof the inner paths were extracted rather
		// than the whole upstream file being wrapped in a second <svg>.
		self::assertSame( 1, \substr_count( $svg, '<svg' ) );
	}

	public function test_keeps_the_inner_paths_untouched(): void {
		$svg = Icons::get( 'sun' );

		// Lucide's sun is a circle plus 8 rays; if extraction dropped children the
		// icon would render blank while every attribute assertion still passed.
		self::assertStringContainsString( '<circle', $svg );
		self::assertSame( 8, \substr_count( $svg, '<path' ) );
	}

	public function test_the_upstream_license_comment_does_not_leak_into_the_page(): void {
		$svg = Icons::get( 'sun' );

		// Every lucide-static file opens with an HTML comment before <svg>.
		// Extraction anchored to the first '>' in the file would swallow it plus
		// the real opening tag, producing markup that still renders and still
		// contains '<svg' — so assert the comment's absence directly.
		self::assertStringNotContainsString( '@license', $svg );
		self::assertStringNotContainsString( '<!--', $svg );
	}

	public function test_applies_a_custom_class_and_size(): void {
		$svg = Icons::get( 'moon', [ 'class' => 'wtb-nav__icon', 'size' => 16 ] );

		self::assertStringContainsString( 'class="wtb-nav__icon"', $svg );
		self::assertStringContainsString( 'width="16"', $svg );
		self::assertStringContainsString( 'height="16"', $svg );
		// The viewBox is the coordinate system, not the rendered size: it must
		// stay 24 regardless of the pixel size, or the icon crops.
		self::assertStringContainsString( 'viewBox="0 0 24 24"', $svg );
	}

	public function test_defaults_to_24_pixels_and_no_class_attribute(): void {
		$svg = Icons::get( 'x' );

		self::assertStringContainsString( 'width="24"', $svg );
		self::assertStringNotContainsString( 'class=', $svg );
	}
```

- [ ] **Step 2: Run to verify they fail**

```bash
composer test:unit -- --filter IconsTest
```

Expected: 4 failures — `get()` still returns `''`.

- [ ] **Step 3: Implement**

In `Icons.php`, replace the `get()` signature and its final `return '';` with:

```php
	/**
	 * Build the inline SVG markup for an icon.
	 *
	 * @param string               $name Icon slug, e.g. 'chevron-down'.
	 * @param array<string, mixed> $args {
	 *     @type string $class CSS class for the root element. Default ''.
	 *     @type int    $size  Rendered width/height in px. Default 24.
	 * }
	 * @return string Markup, or '' when the icon does not exist.
	 */
	public static function get( string $name, array $args = [] ): string {
		if ( 1 !== \preg_match( self::NAME_PATTERN, $name ) ) {
			return '';
		}

		$inner = self::inner_markup( $name );

		if ( '' === $inner ) {
			return '';
		}

		$class = isset( $args['class'] ) ? (string) $args['class'] : '';
		$size  = isset( $args['size'] ) ? (int) $args['size'] : 24;

		$attributes = [
			'xmlns'            => 'http://www.w3.org/2000/svg',
			'width'            => (string) $size,
			'height'           => (string) $size,
			'viewBox'          => '0 0 24 24',
			'fill'             => 'none',
			'stroke'           => 'currentColor',
			'stroke-width'     => '2',
			'stroke-linecap'   => 'round',
			'stroke-linejoin'  => 'round',
		];

		if ( '' !== $class ) {
			$attributes['class'] = $class;
		}

		$rendered = '';
		foreach ( $attributes as $attribute => $value ) {
			$rendered .= \sprintf( ' %s="%s"', $attribute, esc_attr( $value ) );
		}

		return '<svg' . $rendered . '>' . $inner . '</svg>';
	}

	/**
	 * Everything between the upstream <svg> tags, with the tags themselves
	 * discarded.
	 *
	 * Re-emitting our own wrapper rather than rewriting theirs means an upstream
	 * change to the opening tag cannot leak attributes into our markup.
	 */
	private static function inner_markup( string $name ): string {
		if ( isset( self::$cache[ $name ] ) ) {
			return self::$cache[ $name ];
		}

		$path = self::directory() . '/' . $name . '.svg';

		if ( ! \is_file( $path ) || ! \is_readable( $path ) ) {
			return '';
		}

		$file = (string) \file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a vendored theme asset off local disk, not a remote request.

		// Anchor to the <svg> tag, not to the first '>' in the file: every
		// lucide-static file opens with `<!-- @license lucide-static v1.25.0 - ISC -->`,
		// so searching from position 0 finds the comment's '>' and drags the whole
		// opening <svg> tag into the "inner" markup. Verified against v1.25.0.
		$start = \strpos( $file, '<svg' );

		if ( false === $start ) {
			return '';
		}

		$open  = \strpos( $file, '>', $start );
		$close = \strrpos( $file, '</svg>' );

		if ( false === $open || false === $close || $close <= $open ) {
			return '';
		}

		self::$cache[ $name ] = \trim( \substr( $file, $open + 1, $close - $open - 1 ) );

		return self::$cache[ $name ];
	}
```

- [ ] **Step 4: Run to verify they pass**

```bash
composer test:unit -- --filter IconsTest
```

Expected: PASS, 15 tests.

- [ ] **Step 5: Commit**

```bash
git add woodev-base-theme/inc/Icons.php tests/php/Unit/IconsTest.php
git commit -m "feat(icons): emit our own svg wrapper around the upstream paths"
```

---

### Task 4: Accessibility semantics

An icon is either decorative (adjacent to a visible label — screen readers must skip it) or meaningful (an icon-only button — it must have an accessible name). Getting this wrong is a WCAG 2.1 AA failure, and spec §9 requires per-component verification.

**Files:**
- Modify: `woodev-base-theme/inc/Icons.php`
- Modify: `tests/php/Unit/IconsTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `IconsTest.php`:

```php
	public function test_is_decorative_by_default(): void {
		$svg = Icons::get( 'chevron-down' );

		self::assertStringContainsString( 'aria-hidden="true"', $svg );
		// Without this, IE-era and some current browsers put SVGs in the tab
		// order, so a decorative icon becomes a focus stop with no name.
		self::assertStringContainsString( 'focusable="false"', $svg );
		self::assertStringNotContainsString( 'role="img"', $svg );
	}

	public function test_a_label_makes_the_icon_meaningful(): void {
		$svg = Icons::get( 'search', [ 'label' => 'Search' ] );

		self::assertStringContainsString( 'role="img"', $svg );
		self::assertStringContainsString( 'aria-label="Search"', $svg );
		// A labelled icon carries the name itself; hiding it would erase it.
		self::assertStringNotContainsString( 'aria-hidden', $svg );
	}

	public function test_an_empty_label_is_treated_as_decorative(): void {
		// Guards the common call pattern woodev_base_icon( 'x', [ 'label' => $maybe_empty ] ):
		// an empty accessible name is worse than none, because role="img" with no
		// name is announced as an unlabelled image.
		$svg = Icons::get( 'x', [ 'label' => '' ] );

		self::assertStringContainsString( 'aria-hidden="true"', $svg );
		self::assertStringNotContainsString( 'role="img"', $svg );
	}

	public function test_the_label_is_escaped(): void {
		Functions\when( 'esc_attr' )->alias( static fn( $value ) => \htmlspecialchars( (string) $value, ENT_QUOTES ) );

		$svg = Icons::get( 'menu', [ 'label' => 'Close "menu" & go' ] );

		self::assertStringContainsString( 'aria-label="Close &quot;menu&quot; &amp; go"', $svg );
		self::assertStringNotContainsString( '"menu"', $svg );
	}
```

- [ ] **Step 2: Run to verify they fail**

```bash
composer test:unit -- --filter IconsTest
```

Expected: 4 failures — no aria attributes are emitted yet.

- [ ] **Step 3: Implement**

In `Icons.php`, extend the docblock's `$args` with:

```php
	 *     @type string $label Accessible name. Empty (default) marks the icon
	 *                         decorative and hides it from assistive tech.
```

and insert this immediately after the `$size` assignment:

```php
		$label = isset( $args['label'] ) ? \trim( (string) $args['label'] ) : '';
```

then, immediately before the `if ( '' !== $class )` block:

```php
		if ( '' === $label ) {
			$attributes['aria-hidden'] = 'true';
			$attributes['focusable']   = 'false';
		} else {
			$attributes['role']       = 'img';
			$attributes['aria-label'] = $label;
		}
```

- [ ] **Step 4: Run to verify they pass**

```bash
composer test:unit -- --filter IconsTest
```

Expected: PASS, 19 tests.

- [ ] **Step 5: Add the template tag**

Templates should not call a class statically all over the markup. Create `woodev-base-theme/inc/template-tags.php`:

```php
<?php
/**
 * Template tags — thin, escaping-safe wrappers for use inside templates.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

use Woodev\Theme\Base\Icons;

/**
 * Echo an inline Lucide icon.
 *
 * The SVG is assembled attribute-by-attribute in Icons::get() with esc_attr()
 * on every value, and its inner markup comes from a vendored file in the theme
 * — not from user input. Escaping the result again would destroy it, so this is
 * deliberately unescaped output of already-escaped markup.
 *
 * @param string               $name Icon slug.
 * @param array<string, mixed> $args See Icons::get().
 */
function woodev_base_icon( string $name, array $args = [] ): void {
	echo Icons::get( $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Assembled from esc_attr()'d attributes and a vendored SVG; see the docblock.
}
```

- [ ] **Step 6: Load it from the autoloader bootstrap**

`inc/template-tags.php` holds functions, not classes, so the classmap autoloader will never pull it in. Add to `woodev-base-theme/functions.php`, immediately after the autoloader require:

```php
require_once __DIR__ . '/inc/template-tags.php';
```

Read `functions.php` first and match its existing require style; do not restructure the file.

- [ ] **Step 7: Verify the whole gate set**

Per the project rule — and the s3 lesson that skipping "irrelevant" gates hides real failures — run all of them, not just PHP:

```bash
composer phpcs && composer phpstan && composer test:unit
npm run format && npm run lint:js && npm run test:js && npm run build
```

Expected: every command exits 0. PHPCS will flag the `echo` if the `phpcs:ignore` comment is missing or misplaced — fix the comment, do not delete the escaping rule.

- [ ] **Step 8: Commit**

```bash
git add woodev-base-theme/inc/Icons.php woodev-base-theme/inc/template-tags.php woodev-base-theme/functions.php tests/php/Unit/IconsTest.php
git commit -m "feat(icons): accessible-name handling and the woodev_base_icon template tag"
```

---

### Task 5: Guard the vendored files themselves

The helper trusts the contents of `assets/static/icons/`. That trust needs a test, because the files are copied by a script and could be replaced by hand despite the README.

**Files:**
- Create: `tests/php/Unit/IconAssetsTest.php`

- [ ] **Step 1: Write the test**

```php
<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * The icon files are inlined into pages verbatim, so their contents are part of
 * the theme's output surface. This asserts the shape the helper assumes and the
 * absence of anything executable.
 */
final class IconAssetsTest extends BaseTestCase {

	private const ICON_DIR = __DIR__ . '/../../../woodev-base-theme/assets/static/icons';

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_icons(): array {
		$cases = [];

		foreach ( \glob( self::ICON_DIR . '/*.svg' ) ?: [] as $path ) {
			$cases[ \basename( $path ) ] = [ $path, (string) \file_get_contents( $path ) ];
		}

		return $cases;
	}

	public function test_the_expected_icons_are_all_present(): void {
		$found = \array_map(
			static fn( string $path ): string => \basename( $path, '.svg' ),
			\glob( self::ICON_DIR . '/*.svg' ) ?: []
		);
		\sort( $found );

		self::assertSame(
			[ 'chevron-down', 'chevron-left', 'chevron-right', 'menu', 'moon', 'search', 'sun', 'x' ],
			$found,
			'Icon set drifted from scripts/copy-icons.mjs — re-run `npm run icons`.'
		);
	}

	/**
	 * @dataProvider provide_icons
	 */
	public function test_icons_contain_nothing_executable( string $path, string $svg ): void {
		self::assertStringNotContainsStringIgnoringCase( '<script', $svg );
		self::assertStringNotContainsStringIgnoringCase( '<foreignObject', $svg );
		self::assertStringNotContainsStringIgnoringCase( 'javascript:', $svg );
		self::assertDoesNotMatchRegularExpression( '/\son[a-z]+\s*=/i', $svg, "Event handler attribute in {$path}" );
	}

	/**
	 * @dataProvider provide_icons
	 */
	public function test_icons_have_the_shape_the_helper_assumes( string $path, string $svg ): void {
		// Note the file does NOT start with <svg: lucide-static v1.25.0 emits a
		// license comment first. Icons::inner_markup() anchors on '<svg' for
		// exactly this reason, so assert the real shape rather than the tidy one.
		self::assertStringStartsWith( '<!-- @license', \trim( $svg ), "Missing upstream license header in {$path}" );
		self::assertSame( 1, \substr_count( $svg, '<svg' ), "More than one root element in {$path}" );
		self::assertStringContainsString( 'viewBox="0 0 24 24"', $svg, "Unexpected coordinate system in {$path}" );
	}
}
```

This extends PHPUnit's `TestCase` directly, not the project's Brain\Monkey base: it touches no WordPress functions, and the Monkey lifecycle would be dead weight.

- [ ] **Step 2: Run it**

```bash
composer test:unit -- --filter IconAssetsTest
```

Expected: PASS — 1 + 8 + 8 = 17 tests.

- [ ] **Step 3: Prove the guard fires**

```bash
printf '<svg viewBox="0 0 24 24" onload="alert(1)"><path d="M0 0"/></svg>' > woodev-base-theme/assets/static/icons/evil.svg
composer test:unit -- --filter IconAssetsTest
```

Expected: FAIL on both the event-handler assertion and the expected-icon-set assertion. Then remove it and confirm green:

```bash
rm woodev-base-theme/assets/static/icons/evil.svg
composer test:unit -- --filter IconAssetsTest
```

If the suite stayed green with `evil.svg` present, the data provider is not finding the files — check `ICON_DIR` before moving on.

- [ ] **Step 4: Commit**

```bash
git add tests/php/Unit/IconAssetsTest.php
git commit -m "test(icons): assert the vendored svgs are inert and well-shaped"
```

---

### Task 6: Prove it works inside a real WordPress

Unit tests mock `get_template_directory()`. Only an integration test proves the helper finds its files when WordPress resolves that path for real.

**Files:**
- Create: `tests/integration/Integration/IconsTest.php`

- [ ] **Step 1: Write the test**

```php
<?php
/**
 * Icon helper against a real WordPress: the path resolution that unit tests mock.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_UnitTestCase;

final class IconsTest extends WP_UnitTestCase {

	public function test_the_template_tag_outputs_an_icon_for_the_active_theme(): void {
		\ob_start();
		woodev_base_icon( 'sun' );
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( '<svg', $output );
		self::assertStringContainsString( 'aria-hidden="true"', $output );
		self::assertStringContainsString( '<circle', $output );
	}

	public function test_a_labelled_icon_carries_its_accessible_name(): void {
		\ob_start();
		woodev_base_icon( 'search', [ 'label' => 'Search', 'class' => 'wtb-icon' ] );
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'role="img"', $output );
		self::assertStringContainsString( 'aria-label="Search"', $output );
		self::assertStringContainsString( 'class="wtb-icon"', $output );
	}

	public function test_an_unknown_icon_renders_nothing_and_raises_no_error(): void {
		\ob_start();
		woodev_base_icon( 'no-such-icon' );

		// The suite runs with failOnWarning/failOnNotice, so a PHP notice from a
		// missing file would fail this test on its own — the empty-string
		// assertion and the silence are two separate guarantees.
		self::assertSame( '', (string) \ob_get_clean() );
	}
}
```

- [ ] **Step 2: Run the integration suite**

```bash
npm run wp:test:start
npm run test:integration
```

Expected: `OK (7 tests, ...)` — the 4 existing SetupTest tests plus these 3.

- [ ] **Step 3: Mutation-check it**

Temporarily break the path in `Icons::directory()` (e.g. `/assets/static/iconz`) and re-run.

Expected: the first two tests FAIL. Restore and confirm green. A path typo that the unit tests cannot catch — because they mock the directory — is exactly what this task exists to catch, so if it stays green the test is not testing what it claims.

- [ ] **Step 4: Full gate set and commit**

```bash
composer phpcs && composer phpstan && composer test:unit
npm run format && npm run lint:js && npm run test:js && npm run build
npm run test:integration
git add tests/integration/Integration/IconsTest.php
git commit -m "test(icons): verify path resolution inside a real WordPress"
```

- [ ] **Step 5: Push and open the PR**

```bash
git push -u origin feat/m1-lucide-icons
gh pr create --title "feat(m1): Lucide inline-SVG icon helper" --body "Implements docs/plans/2026-07-19-m1-01-lucide-icons.md. Vendors 8 icons reproducibly, renders them as inline SVG with decorative/labelled a11y semantics, and guards both the name handling and the vendored files."
```

- [ ] **Step 6: Codex critic review before merge**

Mandatory per AGENTS.md — the worker does not certify its own work, and fixes made in response to a review get a second review pass. Recipe in `docs/gotchas/codex-cli-dies-silently.md`: clean `CODEX_HOME`, foreground, prompt inline and under ~15 KB. Report findings verbatim to Maksim and ask which to fix; never auto-fix.

---

## Self-review against the spec

**Coverage.** §9's icon requirement ("Lucide, inlined as SVG server-side via a PHP helper, only the icons actually used ship; no icon-font, no full-set bundle") is covered by Tasks 1–4. §7's icon consumers are covered by the `ICONS` list, each entry annotated with the plan that will use it. §9's a11y requirement is Task 4; its "verified per component, not at the end" clause is why the a11y assertions sit in this plan rather than a later audit.

**Deliberately out of scope.** No icon appears in a template here — this plan ships the helper and its tests only. The first real consumers arrive in M1-02 (nav, pagination, search) and M1-05 (scheme switcher). Rendering an icon into a page and checking it in a browser belongs with those plans, where there is a page to look at.

**Known risk.** `inner_markup()` assumes each file is a license comment followed by exactly one 24×24 `<svg>` root. That shape was verified against **`lucide-static` v1.25.0** while writing this plan — all eight icons carry `viewBox="0 0 24 24"`, one root element, and the `<!-- @license … -->` header. Task 5 asserts it against the committed files, so an upgrade that changes the shape fails a test instead of silently producing broken markup.

Worth noting how that assumption was nearly wrong: the first draft of `inner_markup()` searched for the first `>` in the file, which is the license comment's, not the opening tag's — every icon would have rendered with its own opening `<svg>` tag duplicated inside itself. It was caught by reading a real file rather than trusting the remembered format, and the test `test_the_upstream_license_comment_does_not_leak_into_the_page` exists specifically to keep it caught.
