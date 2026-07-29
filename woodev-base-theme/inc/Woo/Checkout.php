<?php
/**
 * Checkout page: a thumbnail on the review-order line items, and the
 * optional secure-payment note under the place-order button.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Customizer\Settings;
use Woodev\Theme\Base\Icons;
use WC_Product;

/**
 * Checkout review-panel hooks that WooCommerce's own
 * `templates/checkout/review-order.php` has no hook or class for —
 * docs/plans/2026-07-28-cart-checkout-account.md section B (K8, K9-note).
 * Targets the CLASSIC (shortcode) checkout only; the block Checkout page is
 * ADR-009's bound and none of these hooks fire there.
 *
 * Hooks/filters this class registers:
 *
 *   10  woocommerce_review_order_before_cart_contents   start_review_order()
 *   10  woocommerce_cart_item_name                       prepend_review_thumbnail() (filter)
 *   10  woocommerce_review_order_after_cart_contents     end_review_order()
 *   10  woocommerce_review_order_after_submit            secure_note()
 *
 * K8's guard, in detail — why a flag toggled by the first two hooks above,
 * and not `is_checkout()`:
 *
 * `woocommerce_cart_item_name` fires from BOTH `templates/cart/cart.php` and
 * `templates/checkout/review-order.php` (Woo shares the filter across the two
 * line-item renderers), so this class must only act on the SECOND of those.
 * `is_checkout()` looks like the obvious guard and is wrong twice over:
 *
 *  1. The classic review table also re-renders over AJAX
 *     (`wc_ajax=update_order_review`, `WC_AJAX::update_order_review()` in
 *     `includes/class-wc-ajax.php`) by calling `woocommerce_order_review()`
 *     directly from inside `admin-ajax.php`'s request handling — no front-end
 *     query has run, `is_checkout()`'s underlying conditional
 *     (`CartCheckoutUtils::is_checkout_page()`, gated on the main query) is
 *     never true there regardless of which page the visitor is looking at.
 *  2. Even on the classic checkout's OWN initial page load, this theme's own
 *     e2e fixture serves it from a page that is deliberately NOT
 *     `woocommerce_checkout_page_id` (`tests/e2e-woo/global-setup.mjs`,
 *     `CLASSIC_CHECKOUT_PAGE_SLUG`'s docblock — the option keeps pointing at
 *     the block Checkout page so `assertBlockPageExists()` still holds), so
 *     `is_checkout()` is false there too.
 *
 * What IS true in every one of those three renders — initial classic
 * checkout, the AJAX re-render, and the fixture page — and false on the cart
 * page: `review-order.php` itself fires
 * `woocommerce_review_order_before_cart_contents` immediately before its
 * cart-item loop and `woocommerce_review_order_after_cart_contents`
 * immediately after it, unconditionally, every time that template runs no
 * matter what called it. A private flag flipped true/false by those two
 * hooks brackets exactly the loop this class needs to act inside, with no
 * dependency on the main query at all.
 */
final class Checkout {

	/**
	 * True only while `checkout/review-order.php`'s cart-item loop is
	 * rendering — see the class docblock for why this, not `is_checkout()`,
	 * is the guard `prepend_review_thumbnail()` needs.
	 *
	 * @var bool
	 */
	private bool $rendering_review_order = false;

	/**
	 * Hook every callback in this class into WordPress.
	 */
	public function register(): void {
		add_action( 'woocommerce_review_order_before_cart_contents', [ $this, 'start_review_order' ] );
		add_action( 'woocommerce_review_order_after_cart_contents', [ $this, 'end_review_order' ] );

		// K8 — prepend a thumbnail to each review-order line item.
		add_filter( 'woocommerce_cart_item_name', [ $this, 'prepend_review_thumbnail' ], 10, 3 );

		// K9-note — the same secure-payment note C10 prints on the cart,
		// from its own independent (also empty-by-default) setting.
		add_action( 'woocommerce_review_order_after_submit', [ $this, 'secure_note' ] );
	}

	/**
	 * Marks the start of `review-order.php`'s cart-item loop. See the class
	 * docblock for why this flag exists instead of an `is_checkout()` check.
	 */
	public function start_review_order(): void {
		$this->rendering_review_order = true;
	}

	/**
	 * Marks the end of `review-order.php`'s cart-item loop.
	 */
	public function end_review_order(): void {
		$this->rendering_review_order = false;
	}

	/**
	 * K8 — prepend a 40px product thumbnail to the review-order line name.
	 *
	 * Guarded on `$rendering_review_order`, not `is_checkout()` — see the
	 * class docblock. Every argument is `mixed`: this filter is third-party
	 * filterable and shared with the cart page, so a plugin ahead of this one
	 * in the filter chain could in principle hand it anything; failing closed
	 * to the untouched `$name` is the house rule (Catalogue.php's docblock).
	 *
	 * @param mixed $name          The line item's name markup, already built.
	 * @param mixed $cart_item     The cart item data.
	 * @param mixed $cart_item_key Unused; part of the hook signature (both
	 *                             `cart.php` and `review-order.php` pass it).
	 */
	public function prepend_review_thumbnail( mixed $name, mixed $cart_item, mixed $cart_item_key ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- part of the woocommerce_cart_item_name filter signature; see the @param note above.
		if ( ! $this->rendering_review_order || ! \is_string( $name ) || ! \is_array( $cart_item ) ) {
			return $name;
		}

		$product = $cart_item['data'] ?? null;

		if ( ! $product instanceof WC_Product ) {
			return $name;
		}

		$thumbnail = $product->get_image( 'woocommerce_gallery_thumbnail', [ 'class' => 'wtb-review-thumb__img' ] );

		// `review-order.php` wraps this filter's return value in
		// wp_kses_post() before echoing it, exactly like the cart page's own
		// `woocommerce_cart_item_thumbnail`/`_name` filters — the same
		// contract every other `<img>`-producing filter on this hook already
		// relies on, so this returns unescaped markup by the same convention.
		return '<span class="wtb-review-thumb">' . $thumbnail . '</span>' . $name;
	}

	/**
	 * K9-note — optional secure-payment note under the place-order button.
	 * Same contract as Cart::secure_note(), from its own independent setting.
	 */
	public function secure_note(): void {
		$note = Settings::checkout_secure_note();

		if ( null === $note ) {
			return;
		}

		// No local fallback for the icon, deliberately. Settings::secure_note()
		// already resolves an icon-less or unrecognised line to
		// Settings::SECURE_NOTE_ICON_DEFAULT before returning, so a `?: 'lock'`
		// here would be unreachable — and worse, it would hard-code a second
		// copy of that default, free to drift from the constant.
		printf(
			'<p class="wtb-secure-note">%s<span>%s</span></p>',
			Icons::get( $note['icon'], [ 'size' => 16 ] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() builds its wrapper from esc_attr()'d attributes and a vendored, XML-parsed SVG (see Icons.php); not user input.
			esc_html( $note['label'] )
		);
	}
}
