<?php
/**
 * The order-received ("thank you") page's own hooks — no template override.
 *
 * `templates/checkout/thankyou.php` is left completely alone: the two hooks this
 * class adds (`woocommerce_before_thankyou`, `woocommerce_thankyou`) reach both
 * places the mockup needs markup, so nothing here needs a copied core file.
 *
 * They do NOT, however, bracket everything that template can print — an earlier
 * version of this comment claimed they did, and the difference matters to both
 * callbacks. Read against the 10.9.4 template:
 *
 *   - `if ( $order )` wraps both hooks, so on a receipt URL that resolves to no
 *     order (a bad id, an expired key) NEITHER fires and the page is core's
 *     bare "order received" paragraph. That is the correct degraded state and
 *     needs nothing from us.
 *   - `woocommerce_before_thankyou` fires BEFORE the `has_status( 'failed' )`
 *     branch, so hero() has to check the status itself.
 *   - `woocommerce_thankyou` fires AFTER that branch's `endif`, i.e. on a failed
 *     order too — not only on success, which is the easy thing to assume. So
 *     actions() needs the same status check, for a different reason: see its own
 *     comment.
 *
 * `templates/checkout/order-received.php` (the single `<p>` lede Woo
 * prints right after the hero below) is likewise left alone — the task
 * explicitly rules out overriding it, and its default sentence is judged
 * "already fine" for the mockup's lede in this class's report rather than
 * rewritten through `woocommerce_thankyou_order_received_text`.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Icons;
use WC_Order;

/**
 * Both callbacks narrow on `instanceof WC_Order`, a class the unit suite has
 * no source for — tests/php/Support/wc-order-double.php supplies it and the
 * unit bootstrap loads it, so it is available to every test regardless of
 * discovery order.
 */
final class Receipt {

	/**
	 * Hook the receipt presentation actions into WordPress.
	 */
	public function register(): void {
		add_action( 'woocommerce_before_thankyou', [ $this, 'hero' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'actions' ], 20 );
	}

	/**
	 * R1 — the check-circle hero above Woo's own "order received" lede.
	 *
	 * `checkout/thankyou.php` fires `woocommerce_before_thankyou` right after
	 * `if ( $order ) :`, BEFORE the branch that checks `$order->has_status(
	 * 'failed' )` — so, left unguarded, this would print "Thank you, your
	 * order is in" with a checkmark directly above core's own "your payment
	 * was declined" notice. That pairing is not in the task's row wording;
	 * it is added here because shipping it would be a real, visible defect,
	 * not a hypothetical one — a failed order gets no success hero.
	 *
	 * The page's single `<h1>` is this hero's title: `page.php` passes
	 * `hide_entry_head` to `template-parts/content/content` on this page (see
	 * that file), so the page's own title never prints a second one.
	 *
	 * @param mixed $order_id The order's ID, as `checkout/thankyou.php` passes
	 *                        it (`$order->get_id()`). Typed `mixed`: this is a
	 *                        `do_action()` callback, so a plugin re-firing the
	 *                        hook by hand could hand it anything, and this
	 *                        file runs under strict_types.
	 */
	public function hero( mixed $order_id ): void {
		$order = $this->resolve_order( $order_id );

		if ( $order instanceof WC_Order && $order->has_status( 'failed' ) ) {
			return;
		}

		printf(
			'<div class="wtb-receipt-hero"><span class="wtb-receipt-hero__check">%1$s</span><h1 class="wtb-receipt-hero__title">%2$s</h1></div>',
			Icons::get( 'check', [ 'size' => 32 ] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns theme-controlled, already-safe inline SVG.
			esc_html__( 'Thank you, your order is in', 'woodev-base-theme' )
		);
	}

	/**
	 * R5 — the "Track order" / "Back to shop" cluster, printed on
	 * `woocommerce_thankyou` at priority 20 so it lands after anything a
	 * payment gateway hooks to the same action at the default priority.
	 *
	 * The track link only renders for the customer who OWNS the order: a guest
	 * receipt, or a different logged-in user reaching the URL with the order
	 * key, must not be handed a link into somebody's account. `0 !==
	 * $customer_id` is load-bearing — a guest order stores customer id `0`,
	 * which would otherwise match a logged-out visitor's `get_current_user_id()`
	 * (also `0`).
	 *
	 * @param mixed $order_id The order's ID, as `checkout/thankyou.php` passes
	 *                        it. Typed `mixed` for the same reason as hero().
	 */
	public function actions( mixed $order_id ): void {
		$order = $this->resolve_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		/*
		 * Skip the whole cluster on a FAILED order, for the same reason hero()
		 * does — and it is not the same code path, which is the point.
		 * `checkout/thankyou.php` fires `woocommerce_thankyou` AFTER its
		 * failed/success `endif` (line 82 of the 10.9.4 template, outside both
		 * branches), so this callback runs on a failed order too. Without this
		 * guard the page reads "your payment failed, please try again" and then
		 * offers "Track order" and "Back to shop" underneath it — telling the
		 * buyer to walk away from a payment they still have to complete. Woo's
		 * own failed branch already prints the right controls (pay again, and
		 * "My account" for a logged-in customer); adding ours next to them
		 * would be actively misleading.
		 */
		if ( $order->has_status( 'failed' ) ) {
			return;
		}

		$customer_id = $order->get_customer_id();
		$owns_order  = is_user_logged_in() && 0 !== $customer_id && get_current_user_id() === $customer_id;
		$shop_url    = wc_get_page_permalink( 'shop' );

		// Nothing to print rather than an empty wrapper: a guest order on a
		// store with no Shop page set would otherwise emit `<div
		// class="wtb-receipt-actions"></div>`, which the CSS turns into a
		// centred flex row of nothing — visible as stray vertical space.
		if ( ! $owns_order && '' === $shop_url ) {
			return;
		}

		echo '<div class="wtb-receipt-actions">';

		if ( $owns_order ) {
			printf(
				'<a class="button" href="%1$s">%2$s</a>',
				esc_url( $order->get_view_order_url() ),
				esc_html__( 'Track order', 'woodev-base-theme' )
			);
		}

		// Same reasoning as Cart::continue_shopping_link(): a store can run
		// entirely off product categories with no dedicated Shop page, and
		// `wc_get_page_permalink()` returns '' there. A link to nowhere is
		// worse than no link.
		if ( '' !== $shop_url ) {
			printf(
				'<a class="button wtb-button--outline" href="%1$s">%2$s</a>',
				esc_url( $shop_url ),
				esc_html__( 'Back to shop', 'woodev-base-theme' )
			);
		}

		echo '</div>';
	}

	/**
	 * The real order behind a `woocommerce_before_thankyou` /
	 * `woocommerce_thankyou` order id, or `false` when it does not resolve to
	 * one — a non-numeric id from a third party re-firing the hook, or an id
	 * that resolves to a `WC_Order_Refund` rather than a `WC_Order` (refunds
	 * are a sibling class, not a subtype, so `instanceof WC_Order` already
	 * excludes them; this method exists so both callbacks share one
	 * resolution path rather than repeating it).
	 *
	 * @param mixed $order_id Hook argument, unvalidated.
	 */
	private function resolve_order( mixed $order_id ): WC_Order|false {
		if ( ! is_numeric( $order_id ) ) {
			return false;
		}

		$order = wc_get_order( (int) $order_id );

		return $order instanceof WC_Order ? $order : false;
	}
}
