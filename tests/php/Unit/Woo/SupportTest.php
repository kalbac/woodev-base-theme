<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Actions;
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

		// The `product_grid` values are asserted in full, not just its
		// presence: `default_columns`/`default_rows` are what
		// wc_reset_product_grid_settings() writes into
		// `woocommerce_catalog_columns`/`_rows` on theme activation, and those
		// two options ARE the catalogue's page size (measured on the live
		// store: 2 rows x 3 columns turned one page of ten products into six
		// plus a pager). Changing a number here changes what every shop page
		// shows, so it is a contract, not configuration.
		self::assertSame(
			[
				'wc-product-gallery-lightbox' => [],
				'wc-product-gallery-slider'   => [],
				'wc-product-gallery-zoom'     => [],
				'woocommerce'                 => [
					[
						'product_grid' => [
							'default_rows'    => 3,
							'min_rows'        => 1,
							'default_columns' => 3,
							'min_columns'     => 1,
							'max_columns'     => 6,
						],
					],
				],
			],
			$supports
		);
		// No feature was registered twice and silently overwritten above.
		self::assertSame( $calls, \count( $supports ) );
	}

	public function test_register_swaps_woo_content_wrapper_actions(): void {
		Functions\expect( 'remove_action' )
			->once()
			->with( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		Functions\expect( 'remove_action' )
			->once()
			->with( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

		$support = new Support();
		$support->register();

		self::assertNotFalse( \has_action( 'woocommerce_before_main_content', [ $support, 'open_wrapper' ] ) );
		self::assertNotFalse( \has_action( 'woocommerce_after_main_content', [ $support, 'close_wrapper' ] ) );
	}

	/**
	 * The full-width shell is still the default: FilterRail::is_active() is
	 * false whenever we are not on a product archive at all, regardless of
	 * whether sidebar-shop has widgets.
	 */
	public function test_open_wrapper_emits_the_theme_layout_shell_when_the_rail_is_not_active(): void {
		Functions\when( 'is_shop' )->justReturn( false );
		Functions\when( 'is_product_taxonomy' )->justReturn( false );
		Functions\when( 'is_active_sidebar' )->justReturn( true );

		$support = new Support();

		ob_start();
		$support->open_wrapper();
		$output = ob_get_clean();

		self::assertSame( '<div class="wtb-layout"><div class="wtb-layout__content">', $output );
	}

	/**
	 * A product archive with an empty sidebar-shop must ALSO get the
	 * full-width shell — an inactive rail must never leave a
	 * `.wtb-shop-layout` grid with only one real column.
	 */
	public function test_open_wrapper_stays_full_width_when_the_shop_sidebar_has_no_widgets(): void {
		Functions\when( 'is_shop' )->justReturn( true );
		Functions\when( 'is_product_taxonomy' )->justReturn( false );
		Functions\when( 'is_active_sidebar' )->justReturn( false );

		$support = new Support();

		ob_start();
		$support->open_wrapper();
		$output = ob_get_clean();

		self::assertSame( '<div class="wtb-layout"><div class="wtb-layout__content">', $output );
	}

	/**
	 * On a product archive with a filled sidebar-shop, open_wrapper() switches
	 * to the two-column grid AND fires `woodev_base_shop_rail` between the
	 * grid's opening tag and the results column — the exact position
	 * FilterRail::render() needs to land the `<aside>` as a grid sibling of
	 * `.wtb-shop-layout__content`, not nested inside it.
	 *
	 * `Actions\expectDone()` (Brain\Monkey's own tool for asserting a hook
	 * fired) is used rather than a fake add_action() listener that echoes a
	 * marker: this test is about WHERE and WHETHER Support fires the hook,
	 * not about what a listener does with it — that belongs to FilterRailTest.
	 */
	public function test_open_wrapper_emits_the_shop_layout_grid_and_fires_the_rail_hook_when_active(): void {
		Functions\when( 'is_shop' )->justReturn( true );
		Functions\when( 'is_product_taxonomy' )->justReturn( false );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Actions\expectDone( 'woodev_base_shop_rail' )->once();

		$support = new Support();

		ob_start();
		$support->open_wrapper();
		$output = ob_get_clean();

		self::assertSame(
			'<div class="wtb-shop-layout"><div class="wtb-shop-layout__content">',
			$output
		);
	}

	public function test_close_wrapper_emits_the_closing_markup(): void {
		$support = new Support();

		ob_start();
		$support->close_wrapper();
		$output = ob_get_clean();

		self::assertSame( '</div></div>', $output );
	}
}
