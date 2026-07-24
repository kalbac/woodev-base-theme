<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\Support;

final class SupportTest extends TestCase {

	public function test_register_hooks_setup_on_after_setup_theme(): void {
		$support = new Support();
		$support->register();
		self::assertNotFalse( \has_action( 'after_setup_theme', [ $support, 'setup' ] ) );
	}

	public function test_setup_declares_exactly_the_four_woo_supports(): void {
		// Record each feature's arguments rather than counting calls: a bare
		// times( 4 ) is satisfied by any four features with any arguments, so
		// swapping one feature for an unrelated one, or silently overwriting a
		// duplicate, would stay green. Keyed by feature so a repeated feature
		// overwrites instead of showing up twice; $calls exists to make that
		// visible.
		$supports = [];
		$calls    = 0;
		Functions\when( 'add_theme_support' )->alias(
			static function ( string $feature, ...$args ) use ( &$supports, &$calls ): void {
				$supports[ $feature ] = $args;
				++$calls;
			}
		);

		( new Support() )->setup();

		// Sorted before comparing: assertSame() on an associative array is
		// order-sensitive, and the order of independent add_theme_support() calls
		// is not part of the contract.
		ksort( $supports );

		self::assertSame(
			[
				'wc-product-gallery-lightbox' => [],
				'wc-product-gallery-slider'   => [],
				'wc-product-gallery-zoom'     => [],
				'woocommerce'                 => [],
			],
			$supports
		);
		// No feature was registered twice and silently overwritten above.
		self::assertSame( $calls, \count( $supports ) );
	}
}
