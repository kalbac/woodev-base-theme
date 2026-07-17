# M0 Bootstrap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Session roles (per AGENTS.md):** Opus 4.8 orchestrator, Sonnet 5 subagent workers, Codex critic review before merge.
> **Read first:** `AGENTS.md`, `docs/specs/2026-07-17-woodev-base-v1-design.md`, `docs/gotchas/tailwind-v4-layer-precedence.md`.

**Goal:** A bootable, activatable Woodev Base theme skeleton with the full QA/build toolchain (Vite + Tailwind v4 + Basecoat, token generation, PHPCS/PHPStan/PHPUnit/Vitest/Playwright, wp-env, GitHub Actions CI) — everything M1 feature work will stand on.

**Architecture:** Node tooling and Composer QA tooling live at the repo root; the theme lives in `woodev-base-theme/`. Design tokens have a single source (`src/tokens/tokens.mjs`) that generates both `theme.json` and a CSS custom-properties file. PHP is a small class tree under `Woodev\Theme\Base` bootstrapped from `functions.php` via a hand-rolled autoloader (no Composer in production).

**Tech Stack:** PHP 8.1+, Vite 7, Tailwind CSS v4 (`@tailwindcss/vite`), `basecoat-css`, Alpine.js, `@wordpress/env`, PHPUnit 11 + Brain\Monkey, PHPStan 2 (level 8) + `szepeviktor/phpstan-wordpress`, PHPCS + WPCS 3, Vitest, Playwright.

**Prerequisites:** Docker Desktop running (for wp-env/e2e tasks), Node ≥ 20, PHP 8.1+ and Composer on PATH, `gh` authenticated.

**Out of scope for M0 (deliberate, not forgotten):** WP integration-test harness (`WP_UnitTestCase` via wp-env/wp-phpunit) — first task of M1, its wiring must be researched against current wp-env docs rather than guessed here. M0 covers unit + e2e levels; activation and rendering are verified for real by e2e.

**Git:** work on branch `feat/m0-bootstrap`, PR to `main` at the end. Commit after every task (steps say when).

---

### Task 1: Node tooling init

**Files:**
- Create: `package.json`

- [ ] **Step 1: Create branch**

```bash
git checkout -b feat/m0-bootstrap
```

- [ ] **Step 2: Write `package.json`**

```json
{
  "name": "woodev-base-theme-dev",
  "private": true,
  "type": "module",
  "description": "Dev tooling for the Woodev Base WordPress theme.",
  "license": "GPL-2.0-or-later",
  "scripts": {
    "tokens": "node scripts/build-tokens.mjs",
    "dev": "npm run tokens && vite",
    "build": "npm run tokens && vite build",
    "test:js": "vitest run",
    "lint:js": "eslint .",
    "format": "prettier --check .",
    "e2e": "playwright test",
    "wp:start": "wp-env start",
    "wp:stop": "wp-env stop"
  }
}
```

- [ ] **Step 3: Install dependencies**

```bash
npm install -D vite tailwindcss @tailwindcss/vite basecoat-css alpinejs @wordpress/env vitest @playwright/test eslint @eslint/js prettier
```

Expected: `package.json` gains `devDependencies`; `package-lock.json` created; no peer-dependency errors.

- [ ] **Step 4: Verify toolchain responds**

```bash
npx vite --version && npx vitest --version && npx wp-env --version
```

Expected: three version strings, no errors.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore(m0): init node tooling"
```

---

### Task 2: Design-token source + generators (TDD)

Single source of truth for tokens; generates `theme.json` and `tokens.generated.css`. Values below are the shadcn neutral defaults Basecoat is designed around.

**Files:**
- Create: `src/tokens/tokens.mjs`
- Create: `scripts/lib/build-tokens-lib.mjs`
- Create: `scripts/build-tokens.mjs`
- Test: `tests/js/build-tokens.test.mjs`

- [ ] **Step 1: Write the token source**

```js
// src/tokens/tokens.mjs
/**
 * Single source of truth for design tokens.
 * NEVER edit theme.json or tokens.generated.css by hand — run `npm run tokens`.
 */
export const tokens = {
  colors: {
    light: {
      background: 'oklch(1 0 0)',
      foreground: 'oklch(0.145 0 0)',
      primary: 'oklch(0.205 0 0)',
      'primary-foreground': 'oklch(0.985 0 0)',
      secondary: 'oklch(0.97 0 0)',
      'secondary-foreground': 'oklch(0.205 0 0)',
      muted: 'oklch(0.97 0 0)',
      'muted-foreground': 'oklch(0.556 0 0)',
      accent: 'oklch(0.97 0 0)',
      'accent-foreground': 'oklch(0.205 0 0)',
      destructive: 'oklch(0.577 0.245 27.325)',
      border: 'oklch(0.922 0 0)',
      input: 'oklch(0.922 0 0)',
      ring: 'oklch(0.708 0 0)',
    },
    dark: {
      background: 'oklch(0.145 0 0)',
      foreground: 'oklch(0.985 0 0)',
      primary: 'oklch(0.922 0 0)',
      'primary-foreground': 'oklch(0.205 0 0)',
      secondary: 'oklch(0.269 0 0)',
      'secondary-foreground': 'oklch(0.985 0 0)',
      muted: 'oklch(0.269 0 0)',
      'muted-foreground': 'oklch(0.708 0 0)',
      accent: 'oklch(0.269 0 0)',
      'accent-foreground': 'oklch(0.985 0 0)',
      destructive: 'oklch(0.704 0.191 22.216)',
      border: 'oklch(1 0 0 / 10%)',
      input: 'oklch(1 0 0 / 15%)',
      ring: 'oklch(0.556 0 0)',
    },
  },
  radius: '0.625rem',
  fonts: {
    sans: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
    mono: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace",
  },
};
```

- [ ] **Step 2: Write the failing tests**

```js
// tests/js/build-tokens.test.mjs
import { describe, expect, it } from 'vitest';
import { buildThemeJson, buildTokensCss } from '../../scripts/lib/build-tokens-lib.mjs';
import { tokens } from '../../src/tokens/tokens.mjs';

describe('buildThemeJson', () => {
  it('emits theme.json v3 with a palette entry per light color', () => {
    const result = buildThemeJson(tokens);
    expect(result.version).toBe(3);
    expect(result.$schema).toBe('https://schemas.wp.org/trunk/theme.json');
    const palette = result.settings.color.palette;
    expect(palette).toHaveLength(Object.keys(tokens.colors.light).length);
    const primary = palette.find((entry) => entry.slug === 'primary');
    expect(primary).toEqual({
      slug: 'primary',
      name: 'Primary',
      color: tokens.colors.light.primary,
    });
  });

  it('emits font families', () => {
    const families = buildThemeJson(tokens).settings.typography.fontFamilies;
    expect(families.find((f) => f.slug === 'sans').fontFamily).toBe(tokens.fonts.sans);
  });
});

describe('buildTokensCss', () => {
  it('emits :root light values and .dark overrides inside @layer theme', () => {
    const css = buildTokensCss(tokens);
    expect(css).toContain('@layer theme');
    expect(css).toContain(`--background: ${tokens.colors.light.background};`);
    expect(css).toContain('.dark {');
    expect(css).toContain(`--background: ${tokens.colors.dark.background};`);
    expect(css).toContain(`--radius: ${tokens.radius};`);
    expect(css).toContain(`--font-sans: ${tokens.fonts.sans};`);
  });
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
npx vitest run tests/js/build-tokens.test.mjs
```

Expected: FAIL — cannot resolve `scripts/lib/build-tokens-lib.mjs`.

- [ ] **Step 4: Implement the generator library**

```js
// scripts/lib/build-tokens-lib.mjs
const titleCase = (slug) =>
  slug
    .split('-')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');

export function buildThemeJson(tokens) {
  return {
    $schema: 'https://schemas.wp.org/trunk/theme.json',
    version: 3,
    settings: {
      color: {
        palette: Object.entries(tokens.colors.light).map(([slug, color]) => ({
          slug,
          name: titleCase(slug),
          color,
        })),
      },
      typography: {
        fontFamilies: Object.entries(tokens.fonts).map(([slug, fontFamily]) => ({
          slug,
          name: titleCase(slug),
          fontFamily,
        })),
      },
    },
  };
}

const varsBlock = (colors, indent) =>
  Object.entries(colors)
    .map(([slug, value]) => `${indent}--${slug}: ${value};`)
    .join('\n');

export function buildTokensCss(tokens) {
  const fontVars = Object.entries(tokens.fonts)
    .map(([slug, value]) => `    --font-${slug}: ${value};`)
    .join('\n');

  return `/* AUTO-GENERATED from src/tokens/tokens.mjs — do not edit. Run \`npm run tokens\`. */
@layer theme {
  :root {
${varsBlock(tokens.colors.light, '    ')}
    --radius: ${tokens.radius};
${fontVars}
  }

  .dark {
${varsBlock(tokens.colors.dark, '    ')}
  }
}
`;
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
npx vitest run tests/js/build-tokens.test.mjs
```

Expected: PASS (3 tests).

- [ ] **Step 6: Write the CLI writer**

```js
// scripts/build-tokens.mjs
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildThemeJson, buildTokensCss } from './lib/build-tokens-lib.mjs';
import { tokens } from '../src/tokens/tokens.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const themeJsonPath = resolve(root, 'woodev-base-theme/theme.json');
const tokensCssPath = resolve(root, 'src/css/tokens.generated.css');

mkdirSync(dirname(themeJsonPath), { recursive: true });
mkdirSync(dirname(tokensCssPath), { recursive: true });

writeFileSync(themeJsonPath, `${JSON.stringify(buildThemeJson(tokens), null, '\t')}\n`);
writeFileSync(tokensCssPath, buildTokensCss(tokens));

console.log('Generated theme.json and tokens.generated.css');
```

- [ ] **Step 7: Run the generator and inspect output**

```bash
npm run tokens && node -e "JSON.parse(require('node:fs').readFileSync('woodev-base-theme/theme.json','utf8')); console.log('theme.json valid')"
```

Expected: `Generated…` then `theme.json valid`. `src/css/tokens.generated.css` starts with the AUTO-GENERATED banner.

- [ ] **Step 8: Add generated CSS to .gitignore, commit**

`theme.json` IS committed (ships with the theme); the generated CSS is a build intermediate.

Append to `.gitignore`:

```text
src/css/tokens.generated.css
```

```bash
git add src/tokens scripts tests/js .gitignore woodev-base-theme/theme.json
git commit -m "feat(m0): design-token single source generating theme.json and CSS vars"
```

---

### Task 3: CSS entry, layer order, adapter skeleton

**Files:**
- Create: `src/css/app.css`
- Create: `src/css/adapter/index.css`
- Create: `src/css/states.css`

- [ ] **Step 1: Verify Basecoat's actual import name and token usage**

```bash
node -e "const p=require('./node_modules/basecoat-css/package.json'); console.log(p.version, JSON.stringify(p.exports && Object.keys(p.exports)))"
grep -l -- "--background" node_modules/basecoat-css/dist/*.css | head -3
```

Expected: a version + export keys (confirm `.`/`basecoat-css` CSS export exists). If the grep finds `--background` usage, the shadcn-style token names from Task 2 are correct; if Basecoat uses different variable names, STOP and surface to the orchestrator before continuing (tokens.mjs slugs must then be mapped in the adapter, not renamed in theme.json).

- [ ] **Step 2: Write the CSS entry**

```css
/* src/css/app.css — single CSS entry.
 * Layer order is THE contract (docs/gotchas/tailwind-v4-layer-precedence.md):
 * un-layered CSS beats all layers; adapter is the only place we override Basecoat.
 */
@layer theme, base, components, adapter, utilities;

@import "tailwindcss";
@import "basecoat-css" layer(components);
@import "./tokens.generated.css";
@import "./adapter/index.css" layer(adapter);
@import "./states.css";
```

```css
/* src/css/adapter/index.css
 * The ONLY place Basecoat components are overridden/extended and where
 * project component classes live (spec §5). Loaded into @layer adapter.
 */

body {
  background-color: var(--background);
  color: var(--foreground);
  font-family: var(--font-sans);
}
```

```css
/* src/css/states.css
 * DELIBERATELY UN-LAYERED (wins over all layers, incl. utilities).
 * Only interactive state overrides that must beat utility classes belong here.
 * Every rule needs a comment justifying why it can't live in the adapter layer.
 */
```

- [ ] **Step 3: Commit**

```bash
git add src/css
git commit -m "feat(m0): css entry with layer contract, adapter skeleton, un-layered states file"
```

(Build verification happens in Task 5 once Vite config exists.)

---

### Task 4: JS entry

**Files:**
- Create: `src/js/app.js`

- [ ] **Step 1: Write the JS entry**

```js
// src/js/app.js — theme JS entry.
// Basecoat drives its own components (auto-initializes on import);
// Alpine owns theme-level behavior only (AGENTS.md).
import 'basecoat-css';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
```

- [ ] **Step 2: Commit**

```bash
git add src/js
git commit -m "feat(m0): js entry — basecoat auto-init + alpine start"
```

---

### Task 5: Vite config + production build

**Files:**
- Create: `vite.config.mjs`

- [ ] **Step 1: Write the Vite config**

```js
// vite.config.mjs
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [tailwindcss()],
  build: {
    outDir: 'woodev-base-theme/assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: 'src/js/app.js',
        style: 'src/css/app.css',
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
  },
});
```

- [ ] **Step 2: Build and verify the manifest**

```bash
npm run build && node -e "
const m = JSON.parse(require('node:fs').readFileSync('woodev-base-theme/assets/dist/.vite/manifest.json','utf8'));
if (!m['src/js/app.js']?.file) throw new Error('js entry missing');
if (!m['src/css/app.css']?.file) throw new Error('css entry missing');
console.log('manifest ok:', m['src/js/app.js'].file, m['src/css/app.css'].file);
"
```

Expected: `manifest ok: assets/app-<hash>.js assets/style-<hash>.css` (names may differ slightly — the assertion is what matters). If Tailwind scanning warns about no content found, that is fine at this stage (PHP templates arrive in Task 6).

- [ ] **Step 3: Commit**

```bash
git add vite.config.mjs
git commit -m "build(m0): vite config — two entries, manifest, dist into theme assets"
```

---

### Task 6: Theme skeleton (style.css, functions.php, templates)

**Files:**
- Create: `woodev-base-theme/style.css`
- Create: `woodev-base-theme/functions.php`
- Create: `woodev-base-theme/index.php`
- Create: `woodev-base-theme/header.php`
- Create: `woodev-base-theme/footer.php`

- [ ] **Step 1: Compute the WordPress floor (latest 3 majors, ADR-003)**

```bash
node -e "fetch('https://api.wordpress.org/core/version-check/1.7/').then(r=>r.json()).then(d=>{const [maj,min]=d.offers[0].current.split('.').map(Number);const floorMin=min-2;console.log('current:',d.offers[0].current,'floor:',floorMin<0?(maj-1)+'.x — compute manually':maj+'.'+floorMin)})"
```

Use the printed floor in `Requires at least` below (the `6.7` shown is an example — replace with the computed value) and in `phpcs.xml.dist` `minimum_wp_version` (Task 9).

- [ ] **Step 2: Write `style.css`**

```css
/*
Theme Name: Woodev Base
Theme URI: https://github.com/kalbac/woodev-base-theme
Author: Woodev
Author URI: https://woodev.ru
Description: Universal WordPress theme with optional WooCommerce support. Built on Basecoat UI, Tailwind CSS v4 and Alpine.js.
Version: 0.1.0
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 8.1
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: woodev-base-theme
Update URI: https://github.com/kalbac/woodev-base-theme
Tags: custom-colors, custom-menu, translation-ready
*/
```

(`Tested up to` = current WP major from Step 1. All styles ship from `assets/dist/`; this file is header-only.)

- [ ] **Step 3: Write `functions.php`**

```php
<?php
/**
 * Woodev Base bootstrap.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/autoload.php';

\Woodev\Theme\Base\Theme::boot();
```

(This will fatal until Task 8 creates the classes — that is expected; do not activate the theme yet.)

- [ ] **Step 4: Write the templates**

```php
<?php
/**
 * Header template.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="border-b border-[var(--border)]">
	<div class="mx-auto max-w-5xl p-4">
		<a class="font-semibold" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
	</div>
</header>
<main class="mx-auto max-w-5xl p-4">
```

```php
<?php
/**
 * Footer template.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
</main>
<footer class="border-t border-[var(--border)]">
	<div class="mx-auto max-w-5xl p-4 text-sm text-[var(--muted-foreground)]">
		<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
```

```php
<?php
/**
 * Main fallback template.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

get_header();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		?>
		<article <?php post_class( 'mb-8' ); ?>>
			<h2 class="text-xl font-semibold">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
			</h2>
			<div class="mt-2"><?php the_excerpt(); ?></div>
		</article>
		<?php
	}
} else {
	?>
	<p><?php esc_html_e( 'Nothing found.', 'woodev-base-theme' ); ?></p>
	<?php
}

get_footer();
```

- [ ] **Step 5: Point Tailwind at the PHP templates and rebuild**

Add to the TOP of `src/css/app.css` (Tailwind v4 uses `@source` for content detection; explicit is our spec's rule):

```css
@source "../../woodev-base-theme/**/*.php";
```

```bash
npm run build
```

Expected: build succeeds; the generated CSS now contains the utilities used in templates (spot-check: `grep -c "max-w-5xl" woodev-base-theme/assets/dist/assets/*.css` returns ≥ 1).

- [ ] **Step 6: Commit**

```bash
git add woodev-base-theme src/css/app.css
git commit -m "feat(m0): theme skeleton — headers, bootstrap, base templates"
```

---

### Task 7: PHP QA harness (Composer, PHPUnit + Brain\Monkey)

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/php/bootstrap.php`
- Create: `tests/php/Unit/TestCase.php`

- [ ] **Step 1: Write `composer.json`**

```json
{
	"name": "woodev/woodev-base-theme-dev",
	"description": "Dev-only QA tooling for the Woodev Base theme. The theme itself does not use Composer in production.",
	"license": "GPL-2.0-or-later",
	"require": {
		"php": ">=8.1"
	},
	"require-dev": {
		"phpunit/phpunit": "^11.5",
		"brain/monkey": "^2.6",
		"phpstan/phpstan": "^2.1",
		"szepeviktor/phpstan-wordpress": "^2.0",
		"squizlabs/php_codesniffer": "^3.13",
		"wp-coding-standards/wpcs": "^3.1",
		"dealerdirect/phpcodesniffer-composer-installer": "^1.0"
	},
	"autoload-dev": {
		"psr-4": {
			"Woodev\\Theme\\Base\\Tests\\": "tests/php/"
		}
	},
	"config": {
		"allow-plugins": {
			"dealerdirect/phpcodesniffer-composer-installer": true
		}
	},
	"scripts": {
		"phpcs": "phpcs",
		"phpstan": "phpstan analyse",
		"test:unit": "phpunit -c phpunit.xml.dist"
	}
}
```

- [ ] **Step 2: Install**

```bash
composer install
```

Expected: lockfile created, no conflicts. Contingency: if `brain/monkey` refuses PHPUnit 11, pin `"phpunit/phpunit": "^10.5"` and re-run — record the pin reason in the commit message.

- [ ] **Step 3: Write the PHPUnit config and bootstrap**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	bootstrap="tests/php/bootstrap.php"
	colors="true"
	failOnWarning="true"
	failOnNotice="true">
	<testsuites>
		<testsuite name="unit">
			<directory>tests/php/Unit</directory>
		</testsuite>
	</testsuites>
</phpunit>
```

```php
<?php
/**
 * Unit test bootstrap: Composer autoload (dev) + theme autoloader.
 *
 * @package Woodev\Theme\Base\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../woodev-base-theme/inc/autoload.php';
```

(The bootstrap requires `inc/autoload.php`, created in Task 8 — write Task 8's autoloader before first running PHPUnit, or run it and expect a "file not found" failure that Task 8 fixes.)

```php
<?php
/**
 * Base unit test case with Brain\Monkey lifecycle.
 *
 * @package Woodev\Theme\Base\Tests
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
```

- [ ] **Step 4: Add vendor + phpunit cache to `.gitignore` check**

`vendor/` is already ignored (root `.gitignore`); verify:

```bash
git check-ignore vendor && echo ignored
```

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist tests/php
git commit -m "chore(m0): php qa harness — composer, phpunit 11, brain monkey"
```

---

### Task 8: Theme autoloader + core classes (TDD)

**Files:**
- Create: `woodev-base-theme/inc/autoload.php`
- Create: `woodev-base-theme/inc/Theme.php`
- Create: `woodev-base-theme/inc/Setup.php`
- Test: `tests/php/Unit/AutoloadTest.php`
- Test: `tests/php/Unit/SetupTest.php`

- [ ] **Step 1: Write the failing autoloader test**

```php
<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use function Woodev\Theme\Base\class_path;

final class AutoloadTest extends TestCase {

	public function test_maps_namespaced_class_to_inc_path(): void {
		$path = class_path( 'Woodev\\Theme\\Base\\Customizer\\Colors' );
		self::assertNotNull( $path );
		self::assertStringEndsWith( 'inc/Customizer/Colors.php', \str_replace( '\\', '/', $path ) );
	}

	public function test_returns_null_for_foreign_namespace(): void {
		self::assertNull( class_path( 'OtherVendor\\Thing' ) );
	}

	public function test_theme_class_is_autoloadable(): void {
		self::assertTrue( \class_exists( \Woodev\Theme\Base\Theme::class ) );
	}
}
```

- [ ] **Step 2: Run to verify failure**

```bash
composer test:unit
```

Expected: FAIL (bootstrap can't find `inc/autoload.php`, or `class_path` undefined).

- [ ] **Step 3: Implement the autoloader**

```php
<?php
/**
 * Theme class autoloader. PSR-4-style file names under inc/ (WPCS filename sniff disabled by design).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

const NS_PREFIX = __NAMESPACE__ . '\\';

/**
 * Map a fully-qualified class name in our namespace to its file path.
 */
function class_path( string $class ): ?string {
	if ( ! \str_starts_with( $class, NS_PREFIX ) ) {
		return null;
	}

	$relative = \substr( $class, \strlen( NS_PREFIX ) );

	return __DIR__ . '/' . \str_replace( '\\', '/', $relative ) . '.php';
}

\spl_autoload_register(
	static function ( string $class ): void {
		$path = class_path( $class );

		if ( null !== $path && \is_file( $path ) ) {
			require $path;
		}
	}
);
```

- [ ] **Step 4: Write the failing Setup test**

```php
<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Woodev\Theme\Base\Setup;

final class SetupTest extends TestCase {

	public function test_register_hooks_after_setup_theme(): void {
		$setup = new Setup();
		$setup->register();
		self::assertNotFalse( \has_action( 'after_setup_theme', [ $setup, 'setup' ] ) );
	}

	public function test_setup_declares_theme_supports_and_menu(): void {
		Functions\expect( 'add_theme_support' )->times( 4 );
		Functions\expect( 'load_theme_textdomain' )
			->once()
			->with( 'woodev-base-theme', \Mockery::type( 'string' ) );
		Functions\expect( 'register_nav_menus' )->once();
		Functions\expect( 'get_template_directory' )->andReturn( '/theme' );
		Functions\when( '__' )->returnArg();

		( new Setup() )->setup();
	}
}
```

- [ ] **Step 5: Run to verify failure, then implement**

```bash
composer test:unit
```

Expected: FAIL — `Setup` class not found.

```php
<?php
/**
 * Theme setup: supports, i18n, menus.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

final class Setup {

	public function register(): void {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );
	}

	public function setup(): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support(
			'html5',
			[ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ]
		);

		load_theme_textdomain( 'woodev-base-theme', get_template_directory() . '/languages' );

		register_nav_menus(
			[ 'primary' => __( 'Primary Menu', 'woodev-base-theme' ) ]
		);
	}
}
```

```php
<?php
/**
 * Composition root.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

final class Theme {

	public static function boot(): void {
		( new Setup() )->register();
		( new Assets() )->register();
	}
}
```

(`Assets` arrives in Task 9 — `boot()` is not exercised by unit tests, only by WP at runtime, so this forward reference is fine; e2e covers it.)

- [ ] **Step 6: Run tests to verify they pass**

```bash
composer test:unit
```

Expected: PASS (5 tests). Note: Brain\Monkey's `has_action` works because `register()` uses a real `add_action` stub from Monkey.

- [ ] **Step 7: Commit**

```bash
git add woodev-base-theme/inc tests/php/Unit
git commit -m "feat(m0): autoloader, Theme composition root, Setup (TDD)"
```

---

### Task 9: Assets — Vite manifest resolver + enqueue (TDD)

**Files:**
- Create: `woodev-base-theme/inc/Assets.php`
- Test: `tests/php/Unit/AssetsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Woodev\Theme\Base\Assets;

final class AssetsTest extends TestCase {

	private const MANIFEST = [
		'src/js/app.js'   => [
			'file' => 'assets/app-B3xY.js',
			'css'  => [ 'assets/app-D4zQ.css' ],
		],
		'src/css/app.css' => [ 'file' => 'assets/style-A1bC.css' ],
	];

	public function test_entry_file_resolves_hashed_file(): void {
		self::assertSame( 'assets/app-B3xY.js', Assets::entry_file( self::MANIFEST, 'src/js/app.js' ) );
		self::assertSame( 'assets/style-A1bC.css', Assets::entry_file( self::MANIFEST, 'src/css/app.css' ) );
	}

	public function test_entry_file_returns_null_for_unknown_entry(): void {
		self::assertNull( Assets::entry_file( self::MANIFEST, 'src/js/missing.js' ) );
	}

	public function test_entry_css_lists_imported_css(): void {
		self::assertSame( [ 'assets/app-D4zQ.css' ], Assets::entry_css( self::MANIFEST, 'src/js/app.js' ) );
		self::assertSame( [], Assets::entry_css( self::MANIFEST, 'src/css/app.css' ) );
	}

	public function test_read_manifest_returns_empty_array_for_missing_file(): void {
		self::assertSame( [], Assets::read_manifest( '/nonexistent/manifest.json' ) );
	}
}
```

- [ ] **Step 2: Run to verify failure**

```bash
composer test:unit
```

Expected: FAIL — `Assets` class not found.

- [ ] **Step 3: Implement**

```php
<?php
/**
 * Asset loading via the Vite manifest.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

final class Assets {

	private const DEV_SERVER = 'http://localhost:5173';
	private const JS_ENTRY   = 'src/js/app.js';
	private const CSS_ENTRY  = 'src/css/app.css';

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue(): void {
		if ( \defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV ) {
			$this->enqueue_dev();
			return;
		}

		$dist     = get_template_directory() . '/assets/dist';
		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$manifest = self::read_manifest( $dist . '/.vite/manifest.json' );

		$css = self::entry_file( $manifest, self::CSS_ENTRY );
		if ( null !== $css ) {
			wp_enqueue_style( 'woodev-base-style', "{$dist_uri}/{$css}", [], null );
		}

		$js = self::entry_file( $manifest, self::JS_ENTRY );
		if ( null !== $js ) {
			wp_enqueue_script_module( 'woodev-base-app', "{$dist_uri}/{$js}", [], null );
		}

		foreach ( self::entry_css( $manifest, self::JS_ENTRY ) as $index => $imported ) {
			wp_enqueue_style( "woodev-base-app-{$index}", "{$dist_uri}/{$imported}", [], null );
		}
	}

	private function enqueue_dev(): void {
		wp_enqueue_script_module( 'woodev-base-vite-client', self::DEV_SERVER . '/@vite/client', [], null );
		wp_enqueue_script_module( 'woodev-base-app', self::DEV_SERVER . '/' . self::JS_ENTRY, [], null );
	}

	/**
	 * Read and decode a Vite manifest; empty array when absent/invalid.
	 *
	 * @return array<string, array{file: string, css?: list<string>}>
	 */
	public static function read_manifest( string $path ): array {
		if ( ! \is_file( $path ) ) {
			return [];
		}

		$decoded = \json_decode( (string) \file_get_contents( $path ), true );

		return \is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * @param array<string, array{file: string, css?: list<string>}> $manifest Decoded manifest.
	 */
	public static function entry_file( array $manifest, string $entry ): ?string {
		return $manifest[ $entry ]['file'] ?? null;
	}

	/**
	 * @param array<string, array{file: string, css?: list<string>}> $manifest Decoded manifest.
	 * @return list<string>
	 */
	public static function entry_css( array $manifest, string $entry ): array {
		return $manifest[ $entry ]['css'] ?? [];
	}
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
composer test:unit
```

Expected: PASS (all suites green).

- [ ] **Step 5: Commit**

```bash
git add woodev-base-theme/inc/Assets.php tests/php/Unit/AssetsTest.php
git commit -m "feat(m0): vite manifest resolver + enqueue with dev-server mode (TDD)"
```

---

### Task 10: PHPCS config, clean run

**Files:**
- Create: `phpcs.xml.dist`

- [ ] **Step 1: Write the ruleset (single source of truth for WPCS deviations — AGENTS.md)**

```xml
<?xml version="1.0"?>
<ruleset name="WoodevBase">
	<description>WPCS + documented modern-PHP-8.1 deviations. See AGENTS.md "Coding standards".</description>

	<file>woodev-base-theme</file>
	<exclude-pattern>woodev-base-theme/assets/*</exclude-pattern>

	<arg name="extensions" value="php"/>
	<arg name="basepath" value="."/>
	<arg value="sp"/>
	<arg name="parallel" value="8"/>

	<config name="minimum_wp_version" value="6.7"/>

	<rule ref="WordPress">
		<!-- DEVIATION: modern PHP — short array syntax is mandatory in this project. -->
		<exclude name="Universal.Arrays.DisallowShortArraySyntax"/>
		<exclude name="Generic.Arrays.DisallowShortArraySyntax"/>
		<!-- DEVIATION: PSR-4-style class file names (inc/Theme.php), see autoloader. -->
		<exclude name="WordPress.Files.FileName"/>
	</rule>

	<!-- Enforce the deviation in the positive direction: -->
	<rule ref="Generic.Arrays.DisallowLongArraySyntax"/>

	<rule ref="WordPress.WP.I18n">
		<properties>
			<property name="text_domain" type="array">
				<element value="woodev-base-theme"/>
			</property>
		</properties>
	</rule>
</ruleset>
```

(Set `minimum_wp_version` to the floor computed in Task 6 Step 1.)

- [ ] **Step 2: Run and fix everything it reports**

```bash
composer phpcs
```

Expected: violations in the files written so far (spacing, docblocks). Fix each reported item in the source files — do NOT add `phpcs:ignore` comments. Re-run until:

```text
............ 0 ERRORS AND 0 WARNINGS
```

Contingency: if a sniff structurally conflicts with a modern construct (e.g. first-class callables), add a documented `<exclude>` to `phpcs.xml.dist` in this commit — never inline ignores.

- [ ] **Step 3: Commit**

```bash
git add phpcs.xml.dist woodev-base-theme tests
git commit -m "chore(m0): phpcs ruleset (wpcs + modern-syntax deviations), codebase clean"
```

---

### Task 11: PHPStan level 8, clean run

**Files:**
- Create: `phpstan.neon.dist`

- [ ] **Step 1: Write the config**

```neon
includes:
	- vendor/szepeviktor/phpstan-wordpress/extension.neon

parameters:
	level: 8
	paths:
		- woodev-base-theme/functions.php
		- woodev-base-theme/inc
	treatPhpDocTypesAsCertain: false
```

- [ ] **Step 2: Run and fix all findings**

```bash
composer phpstan
```

Expected first run: possible findings around `wp_enqueue_script_module` (stubs may lag) or array shapes. Fix by improving types; only if a WP function is genuinely missing from stubs, add it to a project stub file `tests/php/stubs.php` and register via `scanFiles` in `phpstan.neon.dist`:

```neon
	scanFiles:
		- tests/php/stubs.php
```

```php
<?php
/**
 * Project stubs for WP functions missing from wordpress-stubs. PHPStan-only, never loaded at runtime.
 */

declare(strict_types=1);

// phpcs:ignoreFile

/**
 * @param array<string, mixed> $deps
 */
function wp_enqueue_script_module( string $id, string $src = '', array $deps = [], string|false|null $version = false ): void {}
```

Re-run until: `[OK] No errors`.

- [ ] **Step 3: Commit**

```bash
git add phpstan.neon.dist tests/php/stubs.php 2>/dev/null || git add phpstan.neon.dist
git commit -m "chore(m0): phpstan level 8 wired with wordpress extension, zero errors"
```

---

### Task 12: ESLint + Prettier

**Files:**
- Create: `eslint.config.mjs`
- Create: `.prettierrc.json`
- Create: `.prettierignore`

- [ ] **Step 1: Write configs**

```js
// eslint.config.mjs
import js from '@eslint/js';

export default [
  js.configs.recommended,
  {
    files: ['src/js/**/*.js', 'scripts/**/*.mjs', 'tests/js/**/*.mjs', 'tests/e2e/**/*.mjs'],
    languageOptions: {
      ecmaVersion: 2024,
      sourceType: 'module',
      globals: {
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        fetch: 'readonly',
        process: 'readonly',
      },
    },
  },
  {
    ignores: ['woodev-base-theme/assets/dist/**', 'vendor/**', 'node_modules/**'],
  },
];
```

```json
{
  "singleQuote": true,
  "printWidth": 100
}
```

```text
woodev-base-theme/assets/dist/
vendor/
node_modules/
package-lock.json
composer.lock
*.php
```

- [ ] **Step 2: Run both, fix findings**

```bash
npm run lint:js && npx prettier --write . && npm run format
```

Expected: ESLint clean; prettier exits 0 after the write pass.

- [ ] **Step 3: Commit**

```bash
git add eslint.config.mjs .prettierrc.json .prettierignore .
git commit -m "chore(m0): eslint flat config + prettier"
```

---

### Task 13: wp-env — boot WordPress, activate theme

**Files:**
- Create: `.wp-env.json`

- [ ] **Step 1: Write the config**

```json
{
	"core": null,
	"phpVersion": "8.1",
	"themes": [ "./woodev-base-theme" ]
}
```

- [ ] **Step 2: Build assets, start, activate**

Docker Desktop must be running.

```bash
npm run build && npx wp-env start && npx wp-env run cli wp theme activate woodev-base-theme
```

Expected: `Success: Switched to 'Woodev Base' theme.`

- [ ] **Step 3: Verify the front page renders with our assets**

```bash
curl -s http://localhost:8888/ | grep -o "assets/dist/assets/[a-zA-Z0-9._-]*" | sort -u
```

Expected: at least one CSS and one JS path from `assets/dist`. Also verify HTTP 200:

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8888/
```

Expected: `200`.

- [ ] **Step 4: Commit**

```bash
git add .wp-env.json
git commit -m "chore(m0): wp-env config — theme boots and activates on php 8.1"
```

---

### Task 14: Playwright e2e smoke

**Files:**
- Create: `playwright.config.mjs`
- Test: `tests/e2e/smoke.spec.mjs`

- [ ] **Step 1: Write the config**

```js
// playwright.config.mjs
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e',
  use: {
    baseURL: 'http://localhost:8888',
  },
  reporter: [['list']],
});
```

- [ ] **Step 2: Write the smoke test**

```js
// tests/e2e/smoke.spec.mjs
import { expect, test } from '@playwright/test';

test('front page renders with theme assets and no console errors', async ({ page }) => {
  const errors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });

  const response = await page.goto('/');
  expect(response.status()).toBe(200);

  // Theme stylesheet from the Vite dist is loaded.
  const themeCss = page.locator('link[rel="stylesheet"][href*="assets/dist"]');
  await expect(themeCss).toHaveCount(1);

  // Header renders the site name (header.php).
  await expect(page.locator('header a').first()).toBeVisible();

  expect(errors).toEqual([]);
});
```

- [ ] **Step 3: Install browser and run (wp-env from Task 13 still running)**

```bash
npx playwright install chromium && npm run e2e
```

Expected: `1 passed`.

- [ ] **Step 4: Commit**

```bash
git add playwright.config.mjs tests/e2e
git commit -m "test(m0): playwright smoke — front page, theme assets, zero console errors"
```

---

### Task 15: GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Write the workflow**

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

jobs:
  php-qa:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: none
      - run: composer install --no-interaction --no-progress
      - run: composer phpcs
      - run: composer phpstan
      - run: composer test:unit

  js-qa:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: npm
      - run: npm ci
      - run: npm run lint:js
      - run: npm run format
      - run: npm run test:js
      - run: npm run build
      - uses: actions/upload-artifact@v4
        with:
          name: theme-dist
          path: woodev-base-theme/assets/dist

  e2e:
    runs-on: ubuntu-latest
    needs: js-qa
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: npm
      - run: npm ci
      - run: npm run build
      - run: npx wp-env start
      - run: npx wp-env run cli wp theme activate woodev-base-theme
      - run: npx playwright install --with-deps chromium
      - run: npm run e2e
```

- [ ] **Step 2: Push the branch and verify CI**

```bash
git add .github/workflows/ci.yml
git commit -m "ci(m0): php-qa, js-qa and e2e pipelines"
git push -u origin feat/m0-bootstrap
gh run watch --exit-status || gh run view --log-failed
```

Expected: all three jobs green. If e2e is flaky on the runner (wp-env startup), re-run once; persistent failure = investigate, do not mark done.

---

### Task 16: Docs update + PR

**Files:**
- Modify: `docs/CURRENT-STATE.md`
- Modify: `docs/SESSION-LOG.md`

- [ ] **Step 1: Update `docs/CURRENT-STATE.md`**

Set the M0 row to done, move "pin WP floor" out of open items (now pinned in style.css/phpcs), add the M1 next actions:

```markdown
| M0 — Bootstrap | ✅ Done | Toolchain, theme skeleton, CI green (sN, DD.MM.YYYY) |
```

Next actions become:

```markdown
1. M1 kickoff: WP integration-test harness (wp-env + wp-phpunit) — research current wp-env docs first.
2. M1 planning: component/template inventory, Customizer v1 scope.
```

- [ ] **Step 2: Add a SESSION-LOG entry at the top** (10–20 lines: what was done, deviations from this plan, CI run link).

- [ ] **Step 3: Open the PR**

```bash
git add docs && git commit -m "docs(m0): current-state + session log"
git push && gh pr create --title "M0: bootstrap — toolchain, theme skeleton, CI" --body "Implements docs/plans/2026-07-17-m0-bootstrap.md. All tasks TDD where applicable; CI green.

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

- [ ] **Step 4: Codex review pass (mandatory, AGENTS.md)** — run the Codex critic on the PR diff; instruct it to read `.claude/skills/wp-theme-development/SKILL.md` and `.claude/skills/wp-security-review/SKILL.md` first. Address findings; never self-certify.

---

## Exit criteria (M0 definition of done)

1. `composer phpcs`, `composer phpstan`, `composer test:unit`, `npm run test:js`, `npm run lint:js`, `npm run build` all green locally and in CI.
2. Theme activates cleanly in wp-env on PHP 8.1; front page renders with dist assets; Playwright smoke passes.
3. `theme.json` and `tokens.generated.css` produced only by `npm run tokens`.
4. Codex review passed on the PR.
