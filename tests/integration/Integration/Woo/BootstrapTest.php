<?php
/**
 * Woo layer bootstrap integration tests: declared theme support, as a real
 * WordPress + WooCommerce install sees it.
 *
 * The base test env has NO WooCommerce active, so every test here skips
 * there — that is correct and intended. This suite only proves something
 * when it runs against an environment with WooCommerce installed and active.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration\Woo;

use WP_UnitTestCase;

final class BootstrapTest extends WP_UnitTestCase {

	public function test_woo_theme_supports_are_registered_when_woo_is_active(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			self::markTestSkipped( 'WooCommerce not active in this environment.' );
		}

		self::assertTrue( current_theme_supports( 'woocommerce' ) );
		self::assertTrue( current_theme_supports( 'wc-product-gallery-zoom' ) );
		self::assertTrue( current_theme_supports( 'wc-product-gallery-lightbox' ) );
		self::assertTrue( current_theme_supports( 'wc-product-gallery-slider' ) );
	}
}
