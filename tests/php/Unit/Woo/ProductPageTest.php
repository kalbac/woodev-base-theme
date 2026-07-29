<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Automattic\WooCommerce\Enums\ProductType;
use Brain\Monkey\Functions;
use Mockery;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\ProductPage;

// woodev_base_icon() lives in inc/template-tags.php, a plain functions file
// the class autoloader never touches (TemplateTagsTest.php requires it the
// same way, for the same reason).
require_once __DIR__ . '/../../../../woodev-base-theme/inc/template-tags.php';

final class ProductPageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		unset( $GLOBALS['product'] );

		// woodev_base_icon() → Icons::get() reads a real vendored SVG off
		// disk and calls esc_attr() while assembling the wrapper — point it
		// at the real theme directory rather than faking a Woo-specific
		// icon set the assertions below would then have to know about
		// (strip_icon_markup() is what keeps them decoupled from exactly
		// which icon renders).
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );
		Functions\when( 'esc_attr' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['product'] );
		parent::tearDown();
	}

	private function set_product( mixed $product ): void {
		$GLOBALS['product'] = $product;
	}

	// ------------------------------------------------------------- register()

	public function test_register_hooks_everything_at_the_documented_priorities(): void {
		$page = new ProductPage();
		$page->register();

		self::assertNotFalse( \has_action( 'wp', [ $page, 'relocate_for_single_product' ] ) );
		self::assertSame( 11, \has_action( 'woocommerce_single_product_summary', [ $page, 'sku_in_rating_row' ] ) );
		self::assertSame( 13, \has_action( 'woocommerce_single_product_summary', [ $page, 'savings_badge' ] ) );
		self::assertSame( 35, \has_action( 'woocommerce_single_product_summary', [ $page, 'trust_badges' ] ) );
		self::assertNotFalse( \has_action( 'woocommerce_before_quantity_input_field', [ $page, 'quantity_step_down' ] ) );
		self::assertNotFalse( \has_action( 'woocommerce_after_quantity_input_field', [ $page, 'quantity_step_up' ] ) );
	}

	// ------------------------------------------------ relocate_for_single_product()

	public function test_relocate_for_single_product_is_a_noop_off_the_product_page(): void {
		Functions\when( 'is_product' )->justReturn( false );

		\add_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		\add_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
		\add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );

		( new ProductPage() )->relocate_for_single_product();

		self::assertSame( 20, \has_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb' ) );
		self::assertSame( 10, \has_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash' ) );
		self::assertSame( 10, \has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price' ) );
		self::assertFalse( \has_action( 'woocommerce_single_product_summary', 'woocommerce_breadcrumb' ) );
	}

	/**
	 * The order this must produce — rating(10) → sku(11) → price(12) →
	 * savings(13) — is why price moves at all; see the class docblock.
	 */
	public function test_relocate_for_single_product_moves_breadcrumb_sale_flash_and_price(): void {
		Functions\when( 'is_product' )->justReturn( true );

		\add_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		\add_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
		\add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );

		( new ProductPage() )->relocate_for_single_product();

		self::assertFalse( \has_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb' ) );
		self::assertSame( 1, \has_action( 'woocommerce_single_product_summary', 'woocommerce_breadcrumb' ) );

		self::assertFalse( \has_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash' ) );
		self::assertSame( 2, \has_action( 'woocommerce_single_product_summary', 'woocommerce_show_product_sale_flash' ) );

		self::assertSame( 12, \has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price' ) );
	}

	// ---------------------------------------------------------- sku_in_rating_row()

	public function test_sku_in_rating_row_prints_the_sku_beside_the_rating(): void {
		Functions\when( 'wc_product_sku_enabled' )->justReturn( true );
		Functions\when( 'esc_html' )->returnArg( 1 );

		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_sku' )->andReturn( 'OBH-1140-GR' );
		$this->set_product( $product );

		\ob_start();
		( new ProductPage() )->sku_in_rating_row();
		$html = \ob_get_clean();

		self::assertSame(
			'<span class="wtb-product-sku">'
				. '<span class="wtb-product-sku__sep" aria-hidden="true">·</span>'
				. '<span class="sku">OBH-1140-GR</span>'
				. '</span>',
			$html
		);
	}

	public function test_sku_in_rating_row_prints_nothing_when_skus_are_disabled_store_wide(): void {
		Functions\when( 'wc_product_sku_enabled' )->justReturn( false );

		// No shouldReceive('get_sku') set up on purpose: the `||` short-circuit
		// means it must never be called once wc_product_sku_enabled() is
		// false, and an unexpected call on a Mockery mock throws.
		$this->set_product( Mockery::mock( 'WC_Product' ) );

		\ob_start();
		( new ProductPage() )->sku_in_rating_row();

		self::assertSame( '', \ob_get_clean() );
	}

	public function test_sku_in_rating_row_prints_nothing_when_the_product_has_no_sku(): void {
		Functions\when( 'wc_product_sku_enabled' )->justReturn( true );

		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_sku' )->andReturn( '' );
		$this->set_product( $product );

		\ob_start();
		( new ProductPage() )->sku_in_rating_row();

		self::assertSame( '', \ob_get_clean() );
	}

	public function test_sku_in_rating_row_prints_nothing_without_a_resolved_product(): void {
		\ob_start();
		( new ProductPage() )->sku_in_rating_row();

		self::assertSame( '', \ob_get_clean() );
	}

	// -------------------------------------------------------------- savings_badge()

	private function stub_savings_badge_wp_functions(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'wc_price' )->alias( static fn ( float $amount ): string => '<span class="amt">' . $amount . '</span>' );
	}

	public function test_savings_badge_prints_the_saved_amount(): void {
		$this->stub_savings_badge_wp_functions();

		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'is_on_sale' )->andReturn( true );
		$product->shouldReceive( 'is_type' )->with( ProductType::VARIABLE )->andReturn( false );
		$product->shouldReceive( 'get_regular_price' )->andReturn( '4590' );
		$product->shouldReceive( 'get_sale_price' )->andReturn( '3490' );
		$this->set_product( $product );

		\ob_start();
		( new ProductPage() )->savings_badge();
		$html = \ob_get_clean();

		self::assertSame(
			'<p class="wtb-product-save"><span class="badge" data-variant="secondary">'
				. 'Save <span class="amt">1100</span>'
				. '</span></p>',
			$html
		);
	}

	public function test_savings_badge_prints_nothing_when_not_on_sale(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'is_on_sale' )->andReturn( false );
		$this->set_product( $product );

		\ob_start();
		( new ProductPage() )->savings_badge();

		self::assertSame( '', \ob_get_clean() );
	}

	/**
	 * A variable product's own price fields are not what any shopper pays —
	 * printing "0" here would be actively wrong, not merely unhelpful, so
	 * this is excluded before the numbers are even read.
	 */
	public function test_savings_badge_prints_nothing_for_a_variable_product(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'is_on_sale' )->andReturn( true );
		$product->shouldReceive( 'is_type' )->with( ProductType::VARIABLE )->andReturn( true );
		// No get_regular_price()/get_sale_price() expectation: the variable
		// check must return before either is read.
		$this->set_product( $product );

		\ob_start();
		( new ProductPage() )->savings_badge();

		self::assertSame( '', \ob_get_clean() );
	}

	/**
	 * @return list<array{0: string, 1: string}>
	 */
	public static function non_positive_saving_prices(): array {
		return [
			'empty regular price'       => [ '', '3490' ],
			'zero regular price'        => [ '0', '3490' ],
			'sale price equal or above' => [ '3490', '3490' ],
		];
	}

	/**
	 * @dataProvider non_positive_saving_prices
	 */
	public function test_savings_badge_prints_nothing_when_the_saving_does_not_resolve_to_a_positive_number( string $regular, string $sale ): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'is_on_sale' )->andReturn( true );
		$product->shouldReceive( 'is_type' )->with( ProductType::VARIABLE )->andReturn( false );
		$product->shouldReceive( 'get_regular_price' )->andReturn( $regular );
		$product->shouldReceive( 'get_sale_price' )->andReturn( $sale );
		$this->set_product( $product );

		\ob_start();
		( new ProductPage() )->savings_badge();

		self::assertSame( '', \ob_get_clean() );
	}

	// --------------------------------------------------------------- trust_badges()

	/**
	 * @param array<string, mixed> $theme_mods Keyed by theme_mod name.
	 */
	private function stub_theme_mods( array $theme_mods ): void {
		Functions\when( 'get_theme_mod' )->alias(
			static fn ( string $name, mixed $default = false ): mixed => $theme_mods[ $name ] ?? $default
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn ( $value ): string => \trim( (string) $value ) );
		Functions\when( 'esc_html' )->returnArg( 1 );
	}

	public function test_trust_badges_prints_nothing_when_both_settings_are_empty(): void {
		$this->stub_theme_mods( [] );

		\ob_start();
		( new ProductPage() )->trust_badges();

		self::assertSame( '', \ob_get_clean() );
	}

	public function test_trust_badges_prints_both_badges_when_both_are_set(): void {
		$this->stub_theme_mods(
			[
				'product_trust_badge_one' => 'Завтра, если заказать до 18:00 | truck',
				'product_trust_badge_two' => 'Гарантия 2 года | shield-check',
			]
		);

		\ob_start();
		( new ProductPage() )->trust_badges();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<div class="wtb-product-trust">'
				. '<span class="badge" data-variant="secondary">Завтра, если заказать до 18:00</span>'
				. '<span class="badge" data-variant="secondary">Гарантия 2 года</span>'
				. '</div>',
			$html
		);
	}

	public function test_trust_badges_prints_only_the_one_badge_that_is_set(): void {
		$this->stub_theme_mods( [ 'product_trust_badge_one' => 'Гарантия 2 года | shield-check' ] );

		\ob_start();
		( new ProductPage() )->trust_badges();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<div class="wtb-product-trust"><span class="badge" data-variant="secondary">Гарантия 2 года</span></div>',
			$html
		);
	}

	// ------------------------------------------------------ quantity_step_down/up()

	public function test_quantity_steppers_print_nothing_off_the_product_page_and_off_the_cart(): void {
		Functions\when( 'is_product' )->justReturn( false );
		Functions\when( 'is_cart' )->justReturn( false );

		\ob_start();
		( new ProductPage() )->quantity_step_down();
		( new ProductPage() )->quantity_step_up();

		self::assertSame( '', \ob_get_clean() );
	}

	/**
	 * C4 — the widened guard: `templates/global/quantity-input.php` fires
	 * these two hooks on the cart page too (see ProductPage's own docblock
	 * for the `is_cart()`/`WOOCOMMERCE_CART` evidence chain), so the stepper
	 * must render there even though `is_product()` is false.
	 */
	public function test_quantity_step_down_prints_the_button_on_the_cart_even_off_a_product_page(): void {
		Functions\when( 'is_product' )->justReturn( false );
		Functions\when( 'is_cart' )->justReturn( true );
		Functions\when( 'esc_attr__' )->returnArg( 1 );

		\ob_start();
		( new ProductPage() )->quantity_step_down();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<button type="button" class="wtb-qty-step" data-step="down" aria-label="Decrease quantity" hidden></button>',
			$html
		);
	}

	/**
	 * @see test_quantity_step_down_prints_the_button_on_the_cart_even_off_a_product_page
	 */
	public function test_quantity_step_up_prints_the_button_on_the_cart_even_off_a_product_page(): void {
		Functions\when( 'is_product' )->justReturn( false );
		Functions\when( 'is_cart' )->justReturn( true );
		Functions\when( 'esc_attr__' )->returnArg( 1 );

		\ob_start();
		( new ProductPage() )->quantity_step_up();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<button type="button" class="wtb-qty-step" data-step="up" aria-label="Increase quantity" hidden></button>',
			$html
		);
	}

	public function test_quantity_step_down_prints_a_hidden_decrement_button(): void {
		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'esc_attr__' )->returnArg( 1 );

		\ob_start();
		( new ProductPage() )->quantity_step_down();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<button type="button" class="wtb-qty-step" data-step="down" aria-label="Decrease quantity" hidden></button>',
			$html
		);
	}

	public function test_quantity_step_up_prints_a_hidden_increment_button(): void {
		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'esc_attr__' )->returnArg( 1 );

		\ob_start();
		( new ProductPage() )->quantity_step_up();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<button type="button" class="wtb-qty-step" data-step="up" aria-label="Increase quantity" hidden></button>',
			$html
		);
	}

	/**
	 * Strips a real, vendored `<svg>…</svg>` icon out of a rendered snippet.
	 *
	 * The icon SVGs (docs/plans/2026-07-28-catalogue-and-product.md, B8) were
	 * still unvendored when this suite was first written — Icons::get()
	 * failed closed to '' for any slug, so the assertions below originally
	 * pinned the icon-free markup directly. They now land mid-flight, in a
	 * parallel change; pinning their exact path data here would duplicate
	 * IconsTest.php's own contract and break every time a Lucide icon is
	 * swapped for a clearer one. This method keeps the assertions scoped to
	 * what this class actually owns — the wrapper markup and hook order —
	 * regardless of which icon (or none) happens to be vendored right now.
	 */
	private static function strip_icon_markup( string $html ): string {
		return (string) \preg_replace( '#<svg\b.*?</svg>#s', '', $html );
	}
}

/**
 * WooCommerce is absent from the unit suite by design (Brain\Monkey fakes the
 * API surface, no plugin is loaded). `ProductPage::savings_badge()` reads
 * `Automattic\WooCommerce\Enums\ProductType::VARIABLE` directly, so that
 * class has to genuinely exist under that name. It is declared at the BOTTOM
 * of this file, guarded by class_exists(), exactly like CatalogueTest.php's
 * WC_Product double: `composer.json`'s `php-stubs/woocommerce-stubs` is a
 * static-analysis-only package (no autoload section), so nothing else in the
 * unit suite defines this class.
 */
if ( ! class_exists( ProductType::class ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, PSR1.Classes.ClassDeclaration.MissingNamespace, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Test double for a class the unit suite has no other source for; see the comment above. Aliased to the real name below.
	final class ProductTypeTestDouble {
		public const SIMPLE   = 'simple';
		public const VARIABLE = 'variable';
	}

	\class_alias( __NAMESPACE__ . '\\ProductTypeTestDouble', ProductType::class );
}
