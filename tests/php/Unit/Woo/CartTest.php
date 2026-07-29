<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\Cart;

final class CartTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Cart's own methods call Icons::get() directly (not through
		// woodev_base_icon()), but the dependency it drags in is identical to
		// ProductPageTest's: a real vendored SVG read off disk, with
		// esc_attr() called while assembling the wrapper. Same fix.
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );
		Functions\when( 'esc_attr' )->returnArg( 1 );
	}

	/**
	 * Strips a real, vendored `<svg>…</svg>` icon out of a rendered snippet.
	 * See ProductPageTest::strip_icon_markup() for why this exists instead of
	 * pinning icon path data in every assertion below.
	 */
	private static function strip_icon_markup( string $html ): string {
		return (string) \preg_replace( '#<svg\b.*?</svg>#s', '', $html );
	}

	// ------------------------------------------------------------- register()

	public function test_register_hooks_everything_at_the_documented_priorities(): void {
		// Pre-register core's own empty-cart callback so the swap in
		// register() has something real to remove.
		\add_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );

		$cart = new Cart();
		$cart->register();

		self::assertSame( 5, \has_action( 'woocommerce_before_cart', [ $cart, 'open_layout' ] ) );
		self::assertSame( 50, \has_action( 'woocommerce_after_cart', [ $cart, 'close_layout' ] ) );
		self::assertNotFalse( \has_filter( 'woocommerce_cart_item_remove_link', [ $cart, 'remove_link_icon' ] ) );
		self::assertNotFalse( \has_action( 'woocommerce_cart_actions', [ $cart, 'continue_shopping_link' ] ) );
		self::assertNotFalse( \has_action( 'woocommerce_after_cart_totals', [ $cart, 'secure_note' ] ) );

		self::assertFalse( \has_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message' ) );
		self::assertSame( 10, \has_action( 'woocommerce_cart_is_empty', [ $cart, 'render_empty_cart' ] ) );
	}

	// ----------------------------------------------------- open/close_layout()

	public function test_open_layout_prints_the_wrapper_opener(): void {
		\ob_start();
		( new Cart() )->open_layout();

		self::assertSame( '<div class="wtb-cart-layout">', \ob_get_clean() );
	}

	public function test_close_layout_prints_the_wrapper_closer(): void {
		\ob_start();
		( new Cart() )->close_layout();

		self::assertSame( '</div>', \ob_get_clean() );
	}

	// -------------------------------------------------------- remove_link_icon()

	public function test_remove_link_icon_replaces_only_the_times_entity(): void {
		$link = '<a role="button" href="https://example.test/cart/?remove_item=abc123" class="remove" aria-label="Remove Test Product from cart" data-product_id="42" data-product_sku="TP-001">&times;</a>';

		$result = self::strip_icon_markup( ( new Cart() )->remove_link_icon( $link ) );

		self::assertSame(
			'<a role="button" href="https://example.test/cart/?remove_item=abc123" class="remove" aria-label="Remove Test Product from cart" data-product_id="42" data-product_sku="TP-001"></a>',
			$result
		);
	}

	public function test_remove_link_icon_returns_non_string_input_unchanged(): void {
		$cart = new Cart();

		self::assertNull( $cart->remove_link_icon( null ) );
		self::assertSame( [ 'x' ], $cart->remove_link_icon( [ 'x' ] ) );
	}

	/**
	 * Fails closed rather than emitting half an anchor: a shape this method
	 * does not recognise is returned exactly as given.
	 */
	public function test_remove_link_icon_returns_the_link_unchanged_when_the_shape_is_unexpected(): void {
		$link = '<a href="https://example.test/cart/" class="remove">Remove</a>';

		self::assertSame( $link, ( new Cart() )->remove_link_icon( $link ) );
	}

	// -------------------------------------------------- continue_shopping_link()

	public function test_continue_shopping_link_prints_the_button_link(): void {
		Functions\when( 'wc_get_page_permalink' )->justReturn( 'https://example.test/shop/' );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );

		\ob_start();
		( new Cart() )->continue_shopping_link();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<a class="button wtb-continue-shopping" href="https://example.test/shop/">Continue shopping</a>',
			$html
		);
	}

	public function test_continue_shopping_link_prints_nothing_without_a_shop_page(): void {
		Functions\when( 'wc_get_page_permalink' )->justReturn( '' );

		\ob_start();
		( new Cart() )->continue_shopping_link();

		self::assertSame( '', \ob_get_clean() );
	}

	// ------------------------------------------------------------- secure_note()

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

	public function test_secure_note_prints_nothing_when_the_setting_is_empty(): void {
		$this->stub_theme_mods( [] );

		\ob_start();
		( new Cart() )->secure_note();

		self::assertSame( '', \ob_get_clean() );
	}

	public function test_secure_note_prints_the_configured_label_and_icon(): void {
		$this->stub_theme_mods( [ 'cart_secure_note' => 'Оплата на защищённой странице банка | credit-card' ] );

		\ob_start();
		( new Cart() )->secure_note();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<p class="wtb-secure-note"><span>Оплата на защищённой странице банка</span></p>',
			$html
		);
	}

	// -------------------------------------------------------- render_empty_cart()

	public function test_render_empty_cart_prints_the_panel(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );

		\ob_start();
		( new Cart() )->render_empty_cart();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<div class="wtb-cart-empty">'
				. '<span class="wtb-cart-empty__icon"></span>'
				. '<h2 class="wtb-cart-empty__title">Your cart is empty</h2>'
				. '<p class="wtb-cart-empty__lede">Add something from the shop and it will show up here.</p>'
				. '</div>',
			$html
		);
	}
}
