<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\Catalogue;

require_once __DIR__ . '/../../Support/wc-product-double.php';

/**
 * `sale_flash()` narrows on `instanceof WC_Product`, a class the unit suite has
 * no source for — tests/php/Support/wc-product-double.php supplies it, and its
 * header explains why that is a hand-written double rather than the WooCommerce
 * stub package.
 */
final class CatalogueTest extends TestCase {

	public function test_register_hooks_every_catalogue_filter(): void {
		$catalogue = new Catalogue();
		$catalogue->register();

		self::assertNotFalse( \has_filter( 'woocommerce_breadcrumb_defaults', [ $catalogue, 'breadcrumb_defaults' ] ) );
		self::assertNotFalse( \has_filter( 'woocommerce_sale_flash', [ $catalogue, 'sale_flash' ] ) );
		self::assertNotFalse( \has_filter( 'woocommerce_pagination_args', [ $catalogue, 'pagination_args' ] ) );
		self::assertNotFalse( \has_action( 'woocommerce_archive_description', [ $catalogue, 'render_subcategories' ] ) );
	}

	// ---------------------------------------------------------------- delimiter

	public function test_breadcrumb_defaults_wraps_the_delimiter_in_a_span(): void {
		$result = ( new Catalogue() )->breadcrumb_defaults(
			[
				'delimiter'   => '&nbsp;&#47;&nbsp;',
				'wrap_before' => '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb">',
			]
		);

		self::assertIsArray( $result );
		self::assertSame(
			'<span class="wtb-breadcrumb__sep" aria-hidden="true">/</span>',
			$result['delimiter']
		);
	}

	/**
	 * `wrap_before` carries WooCommerce's own translated `aria-label`, in
	 * WooCommerce's text domain, which ships translated in every locale. The
	 * class deliberately leaves it alone rather than re-declaring that string
	 * in this theme's domain — assert it, so a later "tidy-up" that rewrites
	 * the wrapper has to argue with a test.
	 */
	public function test_breadcrumb_defaults_leaves_the_wrapper_untouched(): void {
		$wrapper = '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb">';

		$result = ( new Catalogue() )->breadcrumb_defaults( [ 'wrap_before' => $wrapper ] );

		self::assertSame( $wrapper, $result['wrap_before'] );
	}

	// ------------------------------------------------------------- sale flash

	public function test_sale_flash_prints_the_discount_percentage(): void {
		$this->stub_output_functions();

		$product = new \WC_Product();
		$product->set_test_prices( '4590', '3490', true );

		$result = ( new Catalogue() )->sale_flash( '<span class="onsale">Sale!</span>', null, $product );

		// (4590 − 3490) / 4590 = 23.96 %, which rounds to 24.
		self::assertSame( '<span class="onsale wtb-sale-flash">−24%</span>', $result );
	}

	/**
	 * A variable product has no single regular price — `get_regular_price()`
	 * returns an empty string — so there is no one percentage to print and the
	 * class must hand WooCommerce's own wording straight back rather than
	 * inventing a number from a cast-to-zero.
	 */
	public function test_sale_flash_falls_back_when_no_regular_price_resolves(): void {
		$this->stub_output_functions();

		$product = new \WC_Product();
		$product->set_test_prices( '', '3490', true );

		$original = '<span class="onsale">Sale!</span>';

		self::assertSame( $original, ( new Catalogue() )->sale_flash( $original, null, $product ) );
	}

	/**
	 * A saving of under half a percent rounds to zero, and "−0%" reads as a
	 * bug rather than a bargain.
	 */
	public function test_sale_flash_falls_back_when_the_discount_rounds_to_zero(): void {
		$this->stub_output_functions();

		$product = new \WC_Product();
		$product->set_test_prices( '1000', '999.9', true );

		$original = '<span class="onsale">Sale!</span>';

		self::assertSame( $original, ( new Catalogue() )->sale_flash( $original, null, $product ) );
	}

	public function test_sale_flash_falls_back_when_the_product_is_not_on_sale(): void {
		$this->stub_output_functions();

		$product = new \WC_Product();
		$product->set_test_prices( '4590', '3490', false );

		$original = '<span class="onsale">Sale!</span>';

		self::assertSame( $original, ( new Catalogue() )->sale_flash( $original, null, $product ) );
	}

	/**
	 * `woocommerce_sale_flash` is third-party filterable and this file runs
	 * under strict_types — a typed parameter would turn a plugin's `null` into
	 * a TypeError on a front-end request.
	 *
	 * @return list<array{0: mixed, 1: mixed}>
	 */
	public static function foreign_sale_flash_inputs(): array {
		return [
			'null markup'      => [ null, null ],
			'array markup'     => [ [ 'Sale!' ], null ],
			'product is null'  => [ '<span class="onsale">Sale!</span>', null ],
			'product is a int' => [ '<span class="onsale">Sale!</span>', 42 ],
		];
	}

	/**
	 * @dataProvider foreign_sale_flash_inputs
	 */
	public function test_sale_flash_returns_foreign_input_unchanged( mixed $html, mixed $product ): void {
		self::assertSame( $html, ( new Catalogue() )->sale_flash( $html, null, $product ) );
	}

	// -------------------------------------------------------------- pagination

	public function test_pagination_args_swaps_both_arrows_for_icons(): void {
		$this->stub_output_functions();
		Functions\when( 'is_rtl' )->justReturn( false );

		$result = ( new Catalogue() )->pagination_args(
			[
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
				'total'     => 4,
			]
		);

		self::assertStringContainsString( 'Previous page', $result['prev_text'] );
		self::assertStringContainsString( 'Next page', $result['next_text'] );
		self::assertStringContainsString( '<span class="sr-only">', $result['prev_text'] );
		self::assertSame( 4, $result['total'] );
	}

	/**
	 * WooCommerce's own template swaps the arrows for RTL
	 * (`templates/loop/pagination.php`); mirroring it is the whole reason the
	 * class reads `is_rtl()` rather than hard-coding left/right. If this ever
	 * regressed, an RTL store would get a "next" chevron pointing backwards
	 * with the accessible name "Previous page" — wrong in both channels at
	 * once.
	 */
	public function test_pagination_args_mirrors_the_arrows_in_rtl(): void {
		$this->stub_output_functions();
		Functions\when( 'is_rtl' )->justReturn( true );

		$ltr = [
			'prev_text' => '',
			'next_text' => '',
		];

		Functions\when( 'is_rtl' )->justReturn( true );
		$rtl = ( new Catalogue() )->pagination_args( $ltr );

		self::assertStringContainsString( 'Next page', $rtl['prev_text'] );
		self::assertStringContainsString( 'Previous page', $rtl['next_text'] );
	}

	// ------------------------------------------------------- foreign-array guards

	/**
	 * Both array filters have to survive a third party replacing the whole
	 * argument array with something else.
	 *
	 * @return list<array{0: mixed}>
	 */
	public static function non_array_inputs(): array {
		return [
			'null'   => [ null ],
			'string' => [ 'nonsense' ],
			'int'    => [ 0 ],
		];
	}

	/**
	 * @dataProvider non_array_inputs
	 */
	public function test_breadcrumb_defaults_returns_non_array_unchanged( mixed $input ): void {
		self::assertSame( $input, ( new Catalogue() )->breadcrumb_defaults( $input ) );
	}

	/**
	 * @dataProvider non_array_inputs
	 */
	public function test_pagination_args_returns_non_array_unchanged( mixed $input ): void {
		self::assertSame( $input, ( new Catalogue() )->pagination_args( $input ) );
	}

	/**
	 * The three WordPress output helpers the class calls, stubbed as
	 * pass-throughs so an assertion measures THIS class's markup rather than
	 * WordPress's escaping.
	 */
	private function stub_output_functions(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'number_format_i18n' )->alias( static fn ( $number ): string => (string) $number );

		// `pagination_args()` builds its arrows through Icons::get(), which reads
		// the real vendored SVG off disk via get_template_directory(). Pointing it
		// at the actual theme directory rather than stubbing Icons out is
		// deliberate: it means these tests would also catch the arrows silently
		// becoming empty because someone dropped `chevron-left` from
		// scripts/copy-icons.mjs — Icons::get() returns '' for a missing file, and
		// an anchor whose only content was that icon would ship with no visible
		// glyph and a green suite.
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );
	}
}
