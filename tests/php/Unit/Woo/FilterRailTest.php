<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\FilterRail;

final class FilterRailTest extends TestCase {

	/**
	 * Clears $_GET after every test: reset_url() reads the superglobal
	 * directly, and a test that sets it but forgets to clear it would leak
	 * filter state into whichever test runs next in the same process.
	 */
	protected function tearDown(): void {
		$_GET = [];

		parent::tearDown();
	}

	public function test_register_hooks_render_and_the_column_filter(): void {
		$rail = new FilterRail();
		$rail->register();

		self::assertNotFalse( \has_action( 'woodev_base_shop_rail', [ $rail, 'render' ] ) );
		self::assertNotFalse( \has_filter( 'loop_shop_columns', [ $rail, 'columns' ] ) );
	}

	public function test_render_loads_the_rail_template_part(): void {
		Functions\expect( 'get_template_part' )
			->once()
			->with( 'template-parts/woo/filter-rail' );

		( new FilterRail() )->render();
	}

	/**
	 * Support (which wrapper to emit), render() (via the
	 * woodev_base_shop_rail action Support only fires when this is true) and
	 * columns() all defer to is_active() as their single source of truth —
	 * pinning every combination here is what keeps them from drifting apart.
	 *
	 * @dataProvider active_states
	 */
	public function test_is_active_requires_a_product_archive_with_a_filled_sidebar(
		bool $is_shop,
		bool $is_product_taxonomy,
		bool $sidebar_has_widgets,
		bool $expected
	): void {
		Functions\when( 'is_shop' )->justReturn( $is_shop );
		Functions\when( 'is_product_taxonomy' )->justReturn( $is_product_taxonomy );
		Functions\when( 'is_active_sidebar' )->justReturn( $sidebar_has_widgets );

		self::assertSame( $expected, FilterRail::is_active() );
	}

	/**
	 * @return array<string, array{0: bool, 1: bool, 2: bool, 3: bool}>
	 */
	public static function active_states(): array {
		return [
			'shop archive, sidebar filled'     => [ true, false, true, true ],
			'taxonomy archive, sidebar filled' => [ false, true, true, true ],
			'shop archive, empty sidebar'      => [ true, false, false, false ],
			'taxonomy archive, empty sidebar'  => [ false, true, false, false ],
			'neither shop nor taxonomy'        => [ false, false, true, false ],
			'nothing at all'                   => [ false, false, false, false ],
		];
	}

	public function test_columns_returns_three_when_the_rail_is_active(): void {
		Functions\when( 'is_shop' )->justReturn( true );
		Functions\when( 'is_product_taxonomy' )->justReturn( false );
		Functions\when( 'is_active_sidebar' )->justReturn( true );

		self::assertSame( 3, ( new FilterRail() )->columns( 4 ) );
	}

	/**
	 * When inactive, columns() must hand back WHATEVER Woo (or an earlier
	 * filter callback) passed in, not a hardcoded 4 — a passthrough that
	 * happened to match Woo's default would stay green even if it silently
	 * ignored $columns.
	 */
	public function test_columns_passes_the_input_through_unchanged_when_not_active(): void {
		Functions\when( 'is_shop' )->justReturn( false );
		Functions\when( 'is_product_taxonomy' )->justReturn( false );
		Functions\when( 'is_active_sidebar' )->justReturn( true );

		self::assertSame( 5, ( new FilterRail() )->columns( 5 ) );
	}

	public function test_reset_url_is_empty_with_no_query_vars_at_all(): void {
		Functions\when( 'sanitize_key' )->returnArg();
		$_GET = [];

		self::assertSame( '', FilterRail::reset_url() );
	}

	public function test_reset_url_is_empty_when_only_unrelated_query_vars_are_present(): void {
		Functions\when( 'sanitize_key' )->returnArg();
		$_GET = [
			'orderby' => 'price',
			'paged'   => '2',
			's'       => 'kettle',
		];

		self::assertSame( '', FilterRail::reset_url() );
	}

	/**
	 * Every name here is one WC_Query::is_query_var_valid_on_front_page()
	 * itself recognises (installed WooCommerce 10.9.4,
	 * includes/class-wc-query.php) — the exact key SET matters, not just
	 * "non-empty": min_price/max_price/rating_filter are the scalar names it
	 * special-cases, filter_pa_colour / query_type_pa_colour are the
	 * per-attribute layered-nav pair, and filter_stock_status is the Filter by
	 * Stock block's var — all under the shared `filter_`/`query_type_` prefix
	 * rule. An unrelated var (orderby) must be left out of the removal list
	 * and out of the "is anything active" decision.
	 */
	public function test_reset_url_removes_every_active_filter_var_and_nothing_else(): void {
		Functions\when( 'sanitize_key' )->returnArg();
		$_GET = [
			'orderby'              => 'price',
			'min_price'            => '500',
			'max_price'            => '9000',
			'rating_filter'        => '4',
			'filter_pa_colour'     => 'amber',
			'query_type_pa_colour' => 'or',
			'filter_stock_status'  => 'instock',
		];

		Functions\expect( 'remove_query_arg' )
			->once()
			->with(
				[
					'min_price',
					'max_price',
					'rating_filter',
					'filter_pa_colour',
					'query_type_pa_colour',
					'filter_stock_status',
				]
			)
			->andReturn( 'https://example.test/product-category/kitchen/' );

		self::assertSame( 'https://example.test/product-category/kitchen/', FilterRail::reset_url() );
	}

	/**
	 * Every $_GET key is run through sanitize_key() before being tested or
	 * handed to remove_query_arg() — proven by making the stub transform the
	 * key rather than pass it through, and asserting the TRANSFORMED name is
	 * what shows up. A version that read $_GET keys unsanitized would fail
	 * this the moment sanitize_key() stops being a no-op.
	 */
	public function test_reset_url_sanitizes_every_query_var_name_before_using_it(): void {
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => $key . '_clean' );
		$_GET = [ 'filter_pa_colour' => 'amber' ];

		Functions\expect( 'remove_query_arg' )
			->once()
			->with( [ 'filter_pa_colour_clean' ] )
			->andReturn( 'https://example.test/' );

		self::assertSame( 'https://example.test/', FilterRail::reset_url() );
	}
}
