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
use Woodev\Theme\Base\Tests\Integration\Support\AssetMarkup;
use Woodev\Theme\Base\Tests\Integration\Support\ScriptModuleGuard;

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
	 *
	 * Memoized for the lifetime of the process: WP_Script_Modules::$done
	 * (class-wp-script-modules.php, print_script_module()) is a singleton-level
	 * array that marks each module ID as printed and silently skips it on any
	 * later call in the same PHPUnit process. WP_UnitTestCase runs every test
	 * method in one process, so a second render() call would re-enqueue the
	 * same handles but print none of them — not a regex bug, an empty second
	 * render. Rendering once and sharing the string across test methods avoids
	 * that trap entirely.
	 *
	 * ScriptModuleGuard checks the same private array before this file's
	 * first render, turning a hypothetical future collision (another test
	 * class rendering wp_head/wp_footer first) into a loud failure instead of
	 * a silently short capture. See ScriptModuleGuard's docblock.
	 */
	private static function render_front_end_assets(): string {
		static $html = null;

		if ( null === $html ) {
			ScriptModuleGuard::assert_none_already_done( [ 'woodev-base-vite-client', 'woodev-base-style', 'woodev-base-app' ] );

			ob_start();
			do_action( 'wp_head' );
			do_action( 'wp_footer' );
			$html = (string) ob_get_clean();
		}

		return $html;
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
	 *
	 * Parsed with AssetMarkup (DOMDocument), not a regex: an earlier version
	 * of this test used a lookahead-based pattern that accepted
	 * `data-type="module"` / `data-src="…"` (a `\b` matches after a hyphen)
	 * and matched `<scripture` on the tag name. See
	 * docs/gotchas/three-rounds-of-fixes-means-change-the-approach.md.
	 */
	public function test_the_pack_css_is_a_script_module_not_a_stylesheet(): void {
		AssetMarkup::assert_script_module_with_exact_src(
			self::render_front_end_assets(),
			'http://localhost:5173/src/css/packs/vega.css',
			'The dev server serves the CSS entry as a JS module; a plain stylesheet <link> tag would apply nothing.'
		);
	}

	public function test_no_built_asset_is_referenced_in_dev_mode(): void {
		self::assertStringNotContainsString( 'assets/dist', self::render_front_end_assets() );
	}
}
