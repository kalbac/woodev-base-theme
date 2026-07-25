<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\CardActionsWrapper;

final class CardActionsWrapperTest extends TestCase {

	// The priority-window test (open @6 / close @20 on
	// woocommerce_after_shop_loop_item) is gone, not skipped: that machinery
	// no longer exists. The class now filters woocommerce_loop_add_to_cart_link
	// instead of straddling a shared action hook — see the class docblock for
	// why (a third-party callback at priority 7..19 used to land inside the
	// wrapper).

	public function test_register_hooks_the_add_to_cart_link_filter(): void {
		$wrapper = new CardActionsWrapper();
		$wrapper->register();

		self::assertNotFalse( \has_filter( 'woocommerce_loop_add_to_cart_link', [ $wrapper, 'wrap' ] ) );
	}

	/**
	 * Inside the classic loop's own hook, wrap() must actually wrap. This is
	 * the "the wrapper still does its job at all" test — a guard that always
	 * returned the input unchanged would otherwise pass every other test in
	 * this file, including the new block-markup one below.
	 */
	public function test_wraps_a_classic_link_inside_the_loop_item_action(): void {
		Functions\when( 'doing_action' )->justReturn( true );

		$result = ( new CardActionsWrapper() )->wrap( '<a href="?add-to-cart=1" class="button">Add to cart</a>' );

		self::assertSame(
			'<span class="wtb-product-card__actions card-actions">'
				. '<a href="?add-to-cart=1" class="button">Add to cart</a>'
				. '</span>',
			$result
		);
	}

	/**
	 * `woocommerce_loop_add_to_cart_link` also fires from
	 * `ProductButton.php`'s block render_callback (WooCommerce 10.9.4,
	 * `src/Blocks/BlockTypes/ProductButton.php:308`), which never runs
	 * inside `woocommerce_after_shop_loop_item`. Feed wrap() block-shaped
	 * markup — a `<div class="wp-block-button …">` — with `doing_action()`
	 * reporting "not in the classic hook", and it must come back
	 * byte-for-byte unchanged: nesting that block-level `<div>` inside an
	 * inline `<span class="card-actions">` is invalid markup, and outside
	 * the classic loop that span also carries woo.css rules the block
	 * button was never designed for (absolute position, hover-hidden).
	 */
	public function test_returns_block_markup_unchanged_outside_the_loop_item_action(): void {
		Functions\when( 'doing_action' )->justReturn( false );

		$block_markup = '<div class="wp-block-button wp-element-button product">'
			. '<a href="?add-to-cart=1" class="wp-block-button__link wp-element-button">Add to cart</a>'
			. '</div>';

		$result = ( new CardActionsWrapper() )->wrap( $block_markup );

		self::assertSame( $block_markup, $result );
	}

	/**
	 * The filter is third-party-controlled and this file has
	 * declare(strict_types=1) — a typed `string $link` parameter would
	 * TypeError on a plugin returning null, so wrap() accepts `mixed` and
	 * must return anything that is not a non-empty string completely
	 * unchanged rather than fatal.
	 *
	 * @return list<array{0: mixed}>
	 */
	public static function non_wrappable_inputs(): array {
		return [
			'empty string'    => [ '' ],
			'whitespace only' => [ "  \t\n" ],
			'null'            => [ null ],
			'array'           => [ [ '<a href="#">Add to cart</a>' ] ],
		];
	}

	/**
	 * @dataProvider non_wrappable_inputs
	 */
	public function test_returns_non_wrappable_input_unchanged( mixed $input ): void {
		$result = ( new CardActionsWrapper() )->wrap( $input );

		self::assertSame( $input, $result );
	}
}
