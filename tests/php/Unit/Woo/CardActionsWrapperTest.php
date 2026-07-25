<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\CardActionsWrapper;

final class CardActionsWrapperTest extends TestCase {

	public function test_register_hooks_open_at_6_and_close_at_20(): void {
		$wrapper = new CardActionsWrapper();
		$wrapper->register();

		// Priorities are the contract: 5 (anchor close) < 6 (wrapper open) and
		// 10 (add-to-cart button) < 20 (wrapper close) — verified against
		// core's own registration in docs/gotchas/woo-loop-anchor-spans-the-card-hooks.md.
		self::assertSame( 6, \has_action( 'woocommerce_after_shop_loop_item', [ $wrapper, 'open' ] ) );
		self::assertSame( 20, \has_action( 'woocommerce_after_shop_loop_item', [ $wrapper, 'close' ] ) );
	}

	public function test_open_emits_the_wrapper_start_tag(): void {
		ob_start();
		( new CardActionsWrapper() )->open();
		$output = ob_get_clean();

		self::assertSame( '<span class="wtb-product-card__actions card-actions">', $output );
	}

	public function test_close_emits_the_wrapper_end_tag(): void {
		ob_start();
		( new CardActionsWrapper() )->close();
		$output = ob_get_clean();

		self::assertSame( '</span>', $output );
	}
}
