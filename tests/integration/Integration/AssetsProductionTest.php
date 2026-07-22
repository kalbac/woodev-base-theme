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
use Woodev\Theme\Base\Tests\Integration\Support\AssetMarkup;
use Woodev\Theme\Base\Tests\Integration\Support\ScriptModuleGuard;

final class AssetsProductionTest extends WP_UnitTestCase {

	/**
	 * Guards the harness itself: this suite must NOT define WOODEV_BASE_DEV,
	 * otherwise the assertions below would pass vacuously against a dev-mode
	 * WordPress instead of exercising the production branch.
	 */
	public function test_the_harness_is_not_in_dev_mode(): void {
		self::assertFalse(
			\defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV,
			'This suite must run WITHOUT the dev constant — is it collecting Integration/DevMode?'
		);
	}

	/**
	 * Guards the harness itself, the other half: every assertion below is
	 * meaningless if the bootstrap ever stopped activating our theme — the
	 * built-assets test below would then find no manifest under a foreign
	 * theme's directory and markTestSkipped() away, staying green while
	 * proving nothing. This assertion sits outside that skip path on
	 * purpose, so a broken harness fails loudly instead of skipping quietly.
	 */
	public function test_the_harness_has_our_theme_active(): void {
		self::assertSame( 'woodev-base-theme', get_stylesheet() );
		self::assertSame( 'woodev-base-theme', get_template() );
	}

	/**
	 * Render a full front-end request's head and footer, concatenated, and
	 * memoize the result for the lifetime of the process.
	 *
	 * See AssetsDevModeTest::render_front_end_assets() for why both halves are
	 * required (wp_enqueue_scripts fires from wp_head; script modules print in
	 * wp_footer for classic themes) and why the render is memoized in a
	 * function-static (WP_Script_Modules::$done silently skips already-printed
	 * module IDs within the same process).
	 *
	 * The production PHPUnit config collects the whole Integration/
	 * directory, not just this file, so — unlike the dev-mode suite, which
	 * only ever collects this one class — a second test class in that
	 * directory could in principle render wp_head/wp_footer first and land
	 * our script module handle in WP_Script_Modules::$done before we get
	 * here. ScriptModuleGuard turns that into a loud failure instead of a
	 * silently short markup capture. See ScriptModuleGuard's docblock.
	 */
	private static function render_front_end_assets(): string {
		static $html = null;

		if ( null === $html ) {
			ScriptModuleGuard::assert_none_already_done( [ 'woodev-base-app' ] );

			ob_start();
			do_action( 'wp_head' );
			do_action( 'wp_footer' );
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	public function test_no_dev_server_url_is_referenced(): void {
		self::assertStringNotContainsString( 'localhost:5173', self::render_front_end_assets() );
	}

	/**
	 * Two independent assertions rather than one broad
	 * `assertStringContainsString( 'assets/dist', ... )`: that single check
	 * proved only that SOME built asset was printed, so deleting the JS
	 * module enqueue in Assets::enqueue() stayed green because the stylesheet
	 * still matched, and vice versa.
	 *
	 * Each requires the element **id** and the **exact** URL. The id pins the
	 * element to one handle rather than to "something pointing into
	 * assets/dist" — Assets::enqueue() also enqueues the JS entry's imported
	 * CSS in a loop, so deleting the main stylesheet leaves other assets/dist
	 * links standing. WordPress derives the id from the handle
	 * (`woodev-base-style` → `woodev-base-style-css`, `woodev-base-app` →
	 * `woodev-base-app-js-module`), read off a real response.
	 *
	 * The exact URL is affordable here even though built files carry a content
	 * hash, because the manifest is what the resolver is being tested against:
	 * this decodes it independently (plain json_decode, not Assets' own
	 * reader) and computes the URL the theme should have produced. That makes
	 * the assertion prove the manifest lookup resolved the RIGHT file, not
	 * merely that some file from the build directory was printed.
	 */
	public function test_built_assets_are_enqueued_from_the_manifest(): void {
		$manifest_path = get_template_directory() . '/assets/dist/.vite/manifest.json';

		if ( ! is_file( $manifest_path ) ) {
			self::markTestSkipped( 'No Vite build present — run `npm run build` to cover this path.' );
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		self::assertIsArray( $manifest, 'The Vite manifest is present but did not decode to an array.' );

		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$html     = self::render_front_end_assets();

		// The default style_preset is vega, and this suite never changes it.
		AssetMarkup::assert_stylesheet_link(
			$html,
			'woodev-base-style-css',
			$dist_uri . '/' . $manifest['src/css/packs/vega.css']['file'],
			'Expected wp_enqueue_style( \'woodev-base-style\', … ) to print the exact file the manifest names for the vega pack.'
		);

		AssetMarkup::assert_script_module(
			$html,
			'woodev-base-app-js-module',
			$dist_uri . '/' . $manifest['src/js/app.js']['file'],
			'Expected wp_enqueue_script_module( \'woodev-base-app\', … ) to print the exact file the manifest names for the JS entry.'
		);
	}
}
