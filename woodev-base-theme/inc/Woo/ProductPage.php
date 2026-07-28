<?php
/**
 * Single product page: relocates a few core hooks into the buy box and adds
 * the surfaces the mockup has that core has no hook for at all.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Enums\ProductType;
use Woodev\Theme\Base\Customizer\Settings;

/**
 * This class only ever loads inside `class_exists( 'WooCommerce' )`
 * (`inc/Theme.php` → `Woo::register()`), so every Woo conditional tag and
 * template function it calls (`is_product()`, `wc_price()`, …) is always
 * defined by the time any of its callbacks run.
 *
 * Hook priorities used across this class, in the order they end up running
 * inside `woocommerce_single_product_summary` on a single-product page:
 *
 *   1  woocommerce_breadcrumb                    (relocated, was on
 *      woocommerce_before_main_content@20)
 *   2  woocommerce_show_product_sale_flash        (relocated, was on
 *      woocommerce_before_single_product_summary@10)
 *   5  woocommerce_template_single_title          (core, untouched)
 *  10  woocommerce_template_single_rating         (core, untouched)
 *  11  sku_in_rating_row()                        (new)
 *  12  woocommerce_template_single_price          (relocated from its own
 *      native @10 — see relocate_for_single_product()'s docblock for why)
 *  13  savings_badge()                            (new)
 *  20  woocommerce_template_single_excerpt        (core, untouched)
 *  30  woocommerce_template_single_add_to_cart    (core, untouched)
 *  35  trust_badges()                             (new)
 *  40  woocommerce_template_single_meta           (core, untouched; markup
 *      re-shaped by woocommerce/single-product/meta.php)
 */
final class ProductPage {

	/**
	 * Hook every callback in this class into WordPress.
	 */
	public function register(): void {
		// Deferred to `wp`, never called at file scope: `is_product()` needs
		// the main query resolved, which has not happened yet when
		// `Woo::register()` runs (it runs off `after_setup_theme`, well
		// before `wp`). Support::register() gets away with unconditional
		// remove/add calls because those swaps apply to every Woo page
		// alike; these three do not.
		add_action( 'wp', [ $this, 'relocate_for_single_product' ] );

		add_action( 'woocommerce_single_product_summary', [ $this, 'sku_in_rating_row' ], 11 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'savings_badge' ], 13 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'trust_badges' ], 35 );

		// `global/quantity-input.php` renders on the cart page too (and
		// anywhere else Woo prints a quantity field), so the is_product()
		// guard lives INSIDE each callback, not around the registration.
		add_action( 'woocommerce_before_quantity_input_field', [ $this, 'quantity_step_down' ] );
		add_action( 'woocommerce_after_quantity_input_field', [ $this, 'quantity_step_up' ] );
	}

	/**
	 * Move three core hooks from where WooCommerce puts them by default into
	 * the buy box, matching the mockup's layout. Runs on every front-end
	 * request; the `is_product()` guard is what makes it a no-op everywhere
	 * else.
	 *
	 * B4's `sku_in_rating_row()` sits at priority 11, one above core's
	 * `woocommerce_template_single_rating`@10 — meant to land the SKU
	 * directly after the rating block. But core ALSO registers
	 * `woocommerce_template_single_price` at @10 on the very same hook,
	 * added straight after rating in `includes/wc-template-hooks.php`, so
	 * same-priority callbacks run in registration order: rating, then price,
	 * both before anything at @11. Left alone, the DOM order would be
	 * rating → price → sku → savings, not the mockup's rating → sku → price
	 * → savings. Moving price to @12 (after sku@11, before savings@13) is
	 * the only way to get that order without also moving rating, which core
	 * never separates from title@5 in any theme this codebase has seen.
	 */
	public function relocate_for_single_product(): void {
		if ( ! is_product() ) {
			return;
		}

		// B1 — breadcrumb into the buy box, above the title.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		add_action( 'woocommerce_single_product_summary', 'woocommerce_breadcrumb', 1, 0 );

		// B3 — sale flash into the buy box, after the breadcrumb, before the title.
		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
		add_action( 'woocommerce_single_product_summary', 'woocommerce_show_product_sale_flash', 2 );

		// Re-prioritised only to make room for sku_in_rating_row() at @11 — see
		// this method's docblock.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 12 );
	}

	/**
	 * B4 — print the SKU as a sibling of `.woocommerce-product-rating`
	 * (core's `single-product/rating.php`) so the two can be laid out on one
	 * line together. Prints nothing when SKUs are disabled store-wide or the
	 * product has none — this is a supplementary display, not the SKU's only
	 * appearance: `woocommerce/single-product/meta.php` (this theme's
	 * override) still prints it in the `<dl>` further down the page.
	 */
	public function sku_in_rating_row(): void {
		global $product;

		if ( ! $product instanceof \WC_Product || ! wc_product_sku_enabled() ) {
			return;
		}

		$sku = $product->get_sku();

		if ( '' === $sku ) {
			return;
		}

		printf(
			'<span class="wtb-product-sku"><span class="wtb-product-sku__sep" aria-hidden="true">·</span><span class="sku">%s</span></span>',
			esc_html( $sku )
		);
	}

	/**
	 * B5 — a "you saved N" badge under the price, shown only when the
	 * saving is an actual single number.
	 *
	 * A variable product's OWN `get_regular_price()` / `get_sale_price()`
	 * are the parent post's own price fields, not an aggregate of its
	 * variations' — normally empty strings, and even on the rare store where
	 * something has written a stray value into them, that value does not
	 * describe what any shopper actually pays (each variation prices and
	 * discounts independently). There is no single "the saving" for a price
	 * range, so a variable product is excluded outright rather than trusting
	 * whatever those two fields happen to hold.
	 */
	public function savings_badge(): void {
		global $product;

		if ( ! $product instanceof \WC_Product || ! $product->is_on_sale() ) {
			return;
		}

		if ( $product->is_type( ProductType::VARIABLE ) ) {
			return;
		}

		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();

		if ( $regular <= 0.0 || $sale <= 0.0 || $sale >= $regular ) {
			return;
		}

		$sentence = sprintf(
			/* translators: %s: formatted price saved, e.g. "1 100 ₽". */
			esc_html__( 'Save %s', 'woodev-base-theme' ),
			wc_price( $regular - $sale )
		);

		printf( '<p class="wtb-product-save"><span class="badge" data-variant="secondary">%s</span></p>', $sentence ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sentence is esc_html__()'s translated sentence with wc_price()'s own escaped markup substituted in; wc_price() returns WooCommerce-escaped HTML, not raw user input.
	}

	/**
	 * B9 — up to two admin-configured trust badges (delivery cutoff,
	 * warranty, …). Follows the front-page hero's trust-badge convention
	 * (Settings::front_hero_trust(), template-parts/front/hero.php): default
	 * empty, and the whole block is skipped rather than rendering an empty
	 * shell. Unlike the hero's repeater, these are two independently named
	 * settings — the mockup shows exactly two fixed-purpose badges, not an
	 * admin-sized list.
	 */
	public function trust_badges(): void {
		$badges = array_filter(
			[
				Settings::product_trust_badge_one(),
				Settings::product_trust_badge_two(),
			]
		);

		if ( [] === $badges ) {
			return;
		}

		echo '<div class="wtb-product-trust">';

		foreach ( $badges as $badge ) {
			echo '<span class="badge" data-variant="secondary">';
			woodev_base_icon( $badge['icon'], [ 'size' => 18 ] );
			echo esc_html( $badge['label'] );
			echo '</span>';
		}

		echo '</div>';
	}

	/**
	 * B8 — decrement button, printed on `woocommerce_before_quantity_input_field`.
	 *
	 * `templates/global/quantity-input.php` fires this same hook on the cart
	 * page (and anywhere else a quantity field renders), so the guard is
	 * `is_product()`, not "this hook fired". The button ships `hidden`: with
	 * JS disabled there is nothing that would ever un-hide it, and a dead
	 * button that looks clickable is a worse default than a plain number
	 * input the visitor can still type into directly (progressive
	 * enhancement — AGENTS.md).
	 */
	public function quantity_step_down(): void {
		if ( ! is_product() ) {
			return;
		}

		printf(
			'<button type="button" class="wtb-qty-step" data-step="down" aria-label="%s" hidden>',
			esc_attr__( 'Decrease quantity', 'woodev-base-theme' )
		);
		woodev_base_icon( 'minus', [ 'size' => 16 ] );
		echo '</button>';
	}

	/**
	 * B8 — increment button, printed on `woocommerce_after_quantity_input_field`.
	 * See quantity_step_down() for the is_product() guard and the `hidden`
	 * default.
	 */
	public function quantity_step_up(): void {
		if ( ! is_product() ) {
			return;
		}

		printf(
			'<button type="button" class="wtb-qty-step" data-step="up" aria-label="%s" hidden>',
			esc_attr__( 'Increase quantity', 'woodev-base-theme' )
		);
		woodev_base_icon( 'plus', [ 'size' => 16 ] );
		echo '</button>';
	}
}
