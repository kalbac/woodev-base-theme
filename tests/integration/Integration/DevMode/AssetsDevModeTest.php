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
