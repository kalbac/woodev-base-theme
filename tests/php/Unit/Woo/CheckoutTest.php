<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Mockery;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\Checkout;

final class CheckoutTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// secure_note() calls Icons::get() directly, the same real
		// vendored-SVG dependency ProductPageTest/CartTest already work
		// around.
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );
		Functions\when( 'esc_attr' )->returnArg( 1 );
	}

	private static function strip_icon_markup( string $html ): string {
		return (string) \preg_replace( '#<svg\b.*?</svg>#s', '', $html );
	}

	// ------------------------------------------------------------- register()

	public function test_register_hooks_everything_at_the_documented_priorities(): void {
		$checkout = new Checkout();
		$checkout->register();

		self::assertNotFalse( \has_action( 'woocommerce_review_order_before_cart_contents', [ $checkout, 'start_review_order' ] ) );
		self::assertNotFalse( \has_action( 'woocommerce_review_order_after_cart_contents', [ $checkout, 'end_review_order' ] ) );
		self::assertSame( 10, \has_filter( 'woocommerce_cart_item_name', [ $checkout, 'prepend_review_thumbnail' ] ) );
		self::assertNotFalse( \has_action( 'woocommerce_review_order_after_submit', [ $checkout, 'secure_note' ] ) );
	}

	// ------------------------------------------------- prepend_review_thumbnail()

	/**
	 * The K8 guard end-to-end: the SAME `woocommerce_cart_item_name` filter
	 * fires from the cart page too, where the review-order loop markers never
	 * fire at all. `get_image()->once()` below is load-bearing: if the guard
	 * ever loosened to "always act", this would fail on the call count, not
	 * just the returned string.
	 */
	public function test_prepend_review_thumbnail_only_acts_while_the_review_order_loop_is_marked_active(): void {
		$checkout = new Checkout();

		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_image' )
			->once()
			->with( 'woocommerce_gallery_thumbnail', [ 'class' => 'wtb-review-thumb__img' ] )
			->andReturn( '<img class="wtb-review-thumb__img" src="thumb.jpg" alt="" />' );

		$cart_item = [ 'data' => $product ];

		// Before the loop is marked active: untouched, exactly as on the
		// cart page's own call to this shared filter.
		self::assertSame( 'Test Product', $checkout->prepend_review_thumbnail( 'Test Product', $cart_item, 'key1' ) );

		$checkout->start_review_order();

		self::assertSame(
			'<span class="wtb-review-thumb"><img class="wtb-review-thumb__img" src="thumb.jpg" alt="" /></span>Test Product',
			$checkout->prepend_review_thumbnail( 'Test Product', $cart_item, 'key1' )
		);

		$checkout->end_review_order();

		// After the loop ends: untouched again.
		self::assertSame( 'Test Product', $checkout->prepend_review_thumbnail( 'Test Product', $cart_item, 'key1' ) );
	}

	public function test_prepend_review_thumbnail_returns_a_non_string_name_unchanged(): void {
		$checkout = new Checkout();
		$checkout->start_review_order();

		self::assertNull( $checkout->prepend_review_thumbnail( null, [ 'data' => Mockery::mock( 'WC_Product' ) ], 'key' ) );
	}

	public function test_prepend_review_thumbnail_returns_unchanged_for_a_non_array_cart_item(): void {
		$checkout = new Checkout();
		$checkout->start_review_order();

		self::assertSame( 'Test Product', $checkout->prepend_review_thumbnail( 'Test Product', 'not-an-array', 'key' ) );
	}

	public function test_prepend_review_thumbnail_returns_unchanged_when_cart_item_data_is_not_a_product(): void {
		$checkout = new Checkout();
		$checkout->start_review_order();

		self::assertSame( 'Test Product', $checkout->prepend_review_thumbnail( 'Test Product', [ 'data' => null ], 'key' ) );
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
		( new Checkout() )->secure_note();

		self::assertSame( '', \ob_get_clean() );
	}

	public function test_secure_note_prints_the_configured_label_and_icon(): void {
		$this->stub_theme_mods( [ 'checkout_secure_note' => 'Подтверждение заказа защищено | shield-check' ] );

		\ob_start();
		( new Checkout() )->secure_note();
		$html = self::strip_icon_markup( \ob_get_clean() );

		self::assertSame(
			'<p class="wtb-secure-note"><span>Подтверждение заказа защищено</span></p>',
			$html
		);
	}
}
