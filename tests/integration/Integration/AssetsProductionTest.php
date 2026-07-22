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
	 * Render a full front-end request's head and footer, concatenated, and
	 * memoize the result for the lifetime of the process.
	 *
	 * See AssetsDevModeTest::render_front_end_assets() for why both halves are
	 * required (wp_enqueue_scripts fires from wp_head; script modules print in
	 * wp_footer for classic themes) and why the render is memoized in a
	 * function-static (WP_Script_Modules::$done silently skips already-printed
	 * module IDs within the same process).
	 */
	private static function render_front_end_assets(): string {
		static $html = null;

		if ( null === $html ) {
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

	public function test_built_assets_are_enqueued_from_the_manifest(): void {
		$manifest = get_template_directory() . '/assets/dist/.vite/manifest.json';

		if ( ! is_file( $manifest ) ) {
			self::markTestSkipped( 'No Vite build present — run `npm run build` to cover this path.' );
		}

		self::assertStringContainsString( 'assets/dist', self::render_front_end_assets() );
	}
}
