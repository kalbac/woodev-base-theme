<?php
/**
 * Wraps the loop add-to-cart button in the hover-reveal `.card-actions` shell.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

/**
 * Adds a `.card-actions` wrapper around the loop add-to-cart button without a
 * template override, by registering two more callbacks on the SAME
 * `woocommerce_after_shop_loop_item` hook core already uses for the anchor
 * close and the button.
 *
 * Priorities matter: core closes the loop-product anchor at 5 and prints the
 * add-to-cart button at 10 (verified s9,
 * docs/gotchas/woo-loop-anchor-spans-the-card-hooks.md). Opening the wrapper
 * at 6 (after the anchor closes) and closing it at 20 (after the button
 * prints) keeps the wrapper entirely OUTSIDE the anchor — the one invariant
 * that gotcha requires — with no template override needed.
 */
final class CardActionsWrapper {

	/**
	 * Hook the wrapper's open/close callbacks into the loop item action.
	 */
	public function register(): void {
		add_action( 'woocommerce_after_shop_loop_item', [ $this, 'open' ], 6 );
		add_action( 'woocommerce_after_shop_loop_item', [ $this, 'close' ], 20 );
	}

	/**
	 * Open the wrapper. Runs after the anchor has closed (priority 5).
	 */
	public function open(): void {
		echo '<span class="wtb-product-card__actions card-actions">';
	}

	/**
	 * Close the wrapper. Runs after the add-to-cart button has printed
	 * (priority 10).
	 */
	public function close(): void {
		echo '</span>';
	}
}
