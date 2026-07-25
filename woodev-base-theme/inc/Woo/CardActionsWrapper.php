<?php
/**
 * Wraps the loop add-to-cart button in the hover-reveal `.card-actions` shell.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

/**
 * Adds a `.card-actions` wrapper around the loop add-to-cart button by
 * filtering `woocommerce_loop_add_to_cart_link` — the canonical point for
 * changing the loop button markup, applied inside Woo's own
 * `templates/loop/add-to-cart.php` around the button it is about to print.
 *
 * This replaces an earlier approach that opened/closed the wrapper on two
 * more `woocommerce_after_shop_loop_item` callbacks, at priorities 6 and 20,
 * straddling core's anchor-close (5) and add-to-cart (10) callbacks on that
 * SAME hook. That priority WINDOW caught anything else registered on
 * `woocommerce_after_shop_loop_item` between 7 and 19 too — a third-party
 * wishlist or compare button, say — pulling it inside `.card-actions`, which
 * woo.css positions absolutely and hides until hover/focus. Filtering the
 * button's own markup instead only ever touches what this filter is actually
 * handed: the add-to-cart link, nothing a sibling callback prints.
 *
 * `woocommerce_loop_add_to_cart_link` is NOT exclusive to the classic loop
 * template, though: `woocommerce/src/Blocks/BlockTypes/ProductButton.php`
 * applies this same filter around its own block markup — a block-level
 * `<div class="wp-block-button …">…</div>` — while server-rendering a
 * Product Collection / Product Button block. Wrapping that markup in an
 * inline `<span class="card-actions">` would nest a block-level element
 * inside an inline one (invalid nesting) and, inside `.woocommerce
 * li.product`, would also inherit woo.css's absolutely-positioned,
 * hover-hidden `.card-actions` rules that were written only for the classic
 * `.wtb-product-card` markup — the button could be displaced or hidden
 * until hover on a block-built page. `wrap()` therefore only wraps while
 * `doing_action( 'woocommerce_after_shop_loop_item' )` is true: core
 * registers `woocommerce_template_loop_add_to_cart` on that action at
 * priority 10, so the classic template path always runs inside it, while
 * `ProductButton`'s `render_callback` never does.
 *
 * The failure mode this trades in for is broader than "a plugin replaces
 * `woocommerce_template_loop_add_to_cart()` wholesale" (that plugin never
 * runs this filter at all, wrapped or not). The ordinary, fully supported
 * case is a child theme or plugin overriding the
 * `woocommerce/loop/add-to-cart.php` template file without re-applying
 * `woocommerce_loop_add_to_cart_link` around its own output — the button
 * then prints with no wrapper at all. Either way, the visitor gets a plain,
 * static, in-flow button: the SAME treatment `[data-cta="always"]` and
 * `@media (hover: none)` already ship deliberately elsewhere, so this
 * degrades to an already-supported state rather than a broken one. The
 * earlier action-based approach did not have this failure mode — it wrapped
 * whatever that hook's callbacks printed, regardless of markup shape — but
 * it had the priority-window defect above instead. The two approaches trade
 * different failures; neither is strictly better than the other, which is
 * why there is no action-based fallback stacked on top of this filter (that
 * would just reinstate the priority-window defect).
 *
 * The anchor invariant this still has to respect is unchanged: core closes
 * the loop-product anchor at `woocommerce_after_shop_loop_item`@5 and prints
 * the add-to-cart button at @10 (verified s9,
 * docs/gotchas/woo-loop-anchor-spans-the-card-hooks.md) — the button, and so
 * this filtered wrapper around it, is a bare `<li>` child emitted AFTER the
 * anchor closes, never inside it.
 */
final class CardActionsWrapper {

	/**
	 * Hook the wrapper filter into WordPress.
	 */
	public function register(): void {
		add_filter( 'woocommerce_loop_add_to_cart_link', [ $this, 'wrap' ] );
	}

	/**
	 * Wrap a non-empty add-to-cart link in the `.card-actions` shell — but
	 * only while inside the classic loop's `woocommerce_after_shop_loop_item`
	 * action. See the class docblock: `woocommerce_loop_add_to_cart_link`
	 * also fires from `ProductButton.php`'s block render_callback, and that
	 * block markup must pass through unwrapped.
	 *
	 * `woocommerce_loop_add_to_cart_link` is third-party-filterable, so
	 * anything downstream of core can hand this a non-string (`null`, an
	 * array, …) — and this file has `declare(strict_types=1)`, so a typed
	 * `string $link` parameter would TypeError on that instead of failing
	 * closed. This project fails closed on foreign input rather than fatal
	 * (see the `(string) get_theme_mod()` fatal in docs/SESSION-LOG.md, s5):
	 * accept `mixed`, and return it completely unchanged unless it is both a
	 * non-empty string once trimmed AND we are inside the classic loop hook.
	 *
	 * @param mixed $link The filtered add-to-cart link markup.
	 */
	public function wrap( mixed $link ): mixed {
		if ( ! \is_string( $link ) || '' === \trim( $link ) ) {
			return $link;
		}

		if ( ! doing_action( 'woocommerce_after_shop_loop_item' ) ) {
			return $link;
		}

		return '<span class="wtb-product-card__actions card-actions">' . $link . '</span>';
	}
}
