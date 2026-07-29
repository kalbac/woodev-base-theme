<?php
/**
 * Cart page: the layout wrapper, the remove-link icon, the continue-shopping
 * control, the optional secure-payment note and the empty-cart state.
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

/**
 * Cart-page presentation hooks that WooCommerce's own
 * `templates/cart/cart.php` has no hook or class for — docs/plans/2026-07-28-cart-checkout-account.md
 * section A (C1, C5, C6, C10, C12). Targets the CLASSIC (shortcode) cart
 * only; the block Cart page is ADR-009's bound and this class does not touch
 * it — none of these hooks fire there.
 *
 * Hooks/filters this class registers, in the order `cart.php` fires them:
 *
 *    5  woocommerce_before_cart              open_layout()
 *   10  woocommerce_cart_item_remove_link     remove_link_icon()      (filter)
 *   10  woocommerce_cart_actions              continue_shopping_link()
 *   10  woocommerce_after_cart_totals         secure_note()
 *   10  woocommerce_cart_is_empty             render_empty_cart()      (replaces core's own callback)
 *   50  woocommerce_after_cart                close_layout()
 */
final class Cart {

	/**
	 * Hook every callback in this class into WordPress.
	 */
	public function register(): void {
		// C1 — the two hooks that bracket exactly `form.woocommerce-cart-form`
		// plus `div.cart-collaterals` in `templates/cart/cart.php`; see
		// open_layout()'s docblock for why an unbalanced div cannot happen.
		add_action( 'woocommerce_before_cart', [ $this, 'open_layout' ], 5 );
		add_action( 'woocommerce_after_cart', [ $this, 'close_layout' ], 50 );

		// C5 — swap the remove link's `&times;` entity for the theme's icon.
		add_filter( 'woocommerce_cart_item_remove_link', [ $this, 'remove_link_icon' ] );

		// C6 — "Continue shopping" link inside the actions row.
		add_action( 'woocommerce_cart_actions', [ $this, 'continue_shopping_link' ] );

		// C10 — optional secure-payment note under the totals card.
		add_action( 'woocommerce_after_cart_totals', [ $this, 'secure_note' ] );

		// C12 — replace core's plain empty-cart message with the mockup's
		// panel. Woo's own `p.return-to-shop` still prints right after this
		// (cart-empty.php prints it unconditionally, outside the hook) and is
		// left alone.
		remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
		add_action( 'woocommerce_cart_is_empty', [ $this, 'render_empty_cart' ], 10 );
	}

	/**
	 * C1 — open the grid wrapper around the cart form and its totals panel.
	 *
	 * `woocommerce_before_cart` and `woocommerce_after_cart` are unconditional
	 * `do_action()` calls at the very top and bottom of `cart.php` — nothing
	 * in that template can print one without the other. The ONE place a cart
	 * page skips `cart.php` entirely is an empty cart, where
	 * `WC_Shortcode_Cart::output()` loads `cart-empty.php` instead: neither
	 * hook fires there, so open_layout()/close_layout() are always a pair,
	 * never emitted alone, on an empty cart or a full one.
	 */
	public function open_layout(): void {
		echo '<div class="wtb-cart-layout">';
	}

	/**
	 * C1 — close the wrapper opened in open_layout(). See that method's
	 * docblock for why this can never fire without a matching opener.
	 */
	public function close_layout(): void {
		echo '</div>';
	}

	/**
	 * C5 — replace the remove link's `&times;` entity with the theme's icon,
	 * touching nothing else Woo put on the anchor.
	 *
	 * Woo's markup (`templates/cart/cart.php`) is
	 * `<a role="button" href="…" class="remove" aria-label="…"
	 * data-product_id="…" data-product_sku="…">&times;</a>` — every
	 * attribute on that tag, including the two `data-*` ones, drives Woo's
	 * own cart JS, and a future Woo version may add more. Rebuilding the
	 * anchor from scratch would silently drop whatever this version does not
	 * yet know about, so this only ever touches the literal inner text: if
	 * the string does not end in the exact `>&times;</a>` core has shipped
	 * since this filter existed, the shape assumed here no longer holds and
	 * the original markup is returned untouched rather than emitting half an
	 * anchor.
	 *
	 * @param mixed $link The remove-link markup being filtered.
	 */
	public function remove_link_icon( mixed $link ): mixed {
		if ( ! \is_string( $link ) ) {
			return $link;
		}

		$needle = '>&times;</a>';

		if ( ! \str_ends_with( $link, $needle ) ) {
			return $link;
		}

		return \substr( $link, 0, -\strlen( $needle ) ) . '>' . Icons::get( 'x', [ 'size' => 15 ] ) . '</a>';
	}

	/**
	 * C6 — "Continue shopping" link, printed into the actions row that
	 * already holds the coupon form and "Update cart" (see the C6 row in the
	 * plan for why the coupon stays there rather than moving into the totals
	 * panel). Carries Woo's own `button` class so the generic-button CSS
	 * already covering "Update cart" applies here too.
	 *
	 * `wc_get_page_permalink( 'shop' )` returns `''` when no Shop page is
	 * set (a store can run entirely off product categories/tags with no
	 * dedicated shop page) — printing nothing is the only honest option,
	 * there is no page to link to.
	 */
	public function continue_shopping_link(): void {
		$permalink = wc_get_page_permalink( 'shop' );

		if ( '' === $permalink ) {
			return;
		}

		printf(
			'<a class="button wtb-continue-shopping" href="%s">%s%s</a>',
			esc_url( $permalink ),
			Icons::get( 'chevron-left', [ 'size' => 16 ] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() builds its wrapper from esc_attr()'d attributes and a vendored, XML-parsed SVG (see Icons.php); not user input.
			esc_html__( 'Continue shopping', 'woodev-base-theme' )
		);
	}

	/**
	 * C10 — the same secure-payment note the front page and product page
	 * already print from an admin-configured, empty-by-default Customizer
	 * setting (Settings::product_trust_badge_one()'s pattern). A payment
	 * claim the store cannot honour is worse than no line at all, so this
	 * only ever prints when Settings::cart_secure_note() returns something.
	 */
	public function secure_note(): void {
		$note = Settings::cart_secure_note();

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
			Icons::get( $note['icon'], [ 'size' => 16 ] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see continue_shopping_link()'s ignore comment.
			esc_html( $note['label'] )
		);
	}

	/**
	 * C12 — the empty-cart panel, replacing `wc_empty_cart_message()` on
	 * `woocommerce_cart_is_empty`. Deliberately does not claim a product
	 * count ("214 items" in the mockup): a theme has no way to know that
	 * number honestly, and a wrong count is worse than none.
	 */
	public function render_empty_cart(): void {
		printf(
			'<div class="wtb-cart-empty"><span class="wtb-cart-empty__icon">%s</span><h2 class="wtb-cart-empty__title">%s</h2><p class="wtb-cart-empty__lede">%s</p></div>',
			Icons::get( 'shopping-bag', [ 'size' => 26 ] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see continue_shopping_link()'s ignore comment.
			esc_html__( 'Your cart is empty', 'woodev-base-theme' ),
			esc_html__( 'Add something from the shop and it will show up here.', 'woodev-base-theme' )
		);
	}
}
