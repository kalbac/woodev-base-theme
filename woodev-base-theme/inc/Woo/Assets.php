<?php
/**
 * Woo storefront asset loading via the Vite manifest.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Assets as BaseAssets;

/**
 * Enqueues the storefront CSS bundle unconditionally on the front end (this
 * class only ever loads when WooCommerce is active — see `enqueue()` for why
 * that alone is guard enough).
 */
final class Assets {

	/** Vite manifest key for the storefront bundle (the Rollup input's source path). */
	private const WOO_ENTRY = 'src/css/woo.css';

	/** Vite manifest key for the quantity-stepper script (B8). */
	private const WOO_JS_ENTRY = 'src/js/woo.js';

	/**
	 * Hook the storefront enqueue into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_storefront_behaviour' ] );

		// The CART is enqueued from inside the cart markup, not from
		// `wp_enqueue_scripts` — see enqueue_cart_behaviour() for why a page
		// conditional cannot answer this one.
		add_action( 'woocommerce_before_cart', [ $this, 'enqueue_cart_behaviour' ], 1 );
	}

	/**
	 * Enqueue the built storefront bundle unconditionally on the front end.
	 *
	 * There is no Woo-context guard here on purpose. `[products]`,
	 * `[product_category]`, `[featured_products]` and the Woo product blocks
	 * render `woocommerce/content-product.php` (our override, with all the
	 * `wtb-*` classes) on ANY page — a shortcode/block loop is not itself a
	 * "Woo context" (`is_woocommerce()` is `is_shop() || is_product_taxonomy()
	 * || is_product()`, false there), so a conditional enqueue used to ship
	 * that markup with no stylesheet. Unconditional is safe because every rule
	 * in `src/css/woo.css` is nested inside a top-level `.woocommerce { … }`
	 * block (plus two `.woocommerce`-prefixed rules) — the bundle is
	 * completely inert on a page with no Woo markup, since Woo's own shortcode
	 * wrapper (`<div class="woocommerce columns-N">`) and its `woocommerce`
	 * body class are what supply that hook. WooCommerce itself enqueues its
	 * front-end styles on every page for the same reason; being conditional
	 * here only created an asymmetry where Woo markup could appear WITH Woo's
	 * CSS but WITHOUT ours. And "unconditional" is narrower than it sounds:
	 * this class only loads inside `class_exists( 'WooCommerce' )`
	 * (inc/Theme.php), and `wp_enqueue_scripts` only fires on the front end —
	 * so in practice this means "every front-end request of a site that runs
	 * WooCommerce".
	 */
	public function enqueue(): void {
		$dist     = get_template_directory() . '/assets/dist';
		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$manifest = BaseAssets::read_manifest( $dist . '/.vite/manifest.json' );
		$css      = BaseAssets::entry_file( $manifest, self::WOO_ENTRY );

		if ( null !== $css ) {
			wp_enqueue_style( 'woodev-base-woo', "{$dist_uri}/{$css}", [], null );
		}
	}


	/**
	 * Enqueue the storefront behaviour module on the pages that have something
	 * for it to enhance.
	 *
	 * Two surfaces, and the condition below is the union of them rather than
	 * `is_product()` alone — which is what it was when the module only drove the
	 * quantity stepper, and which silently made the second surface a no-op:
	 *
	 * - the single product page's quantity stepper (B8);
	 * - the catalogue's filter rail, which the module collapses on narrow
	 *   viewports (A14). That rail only ever renders on a product ARCHIVE, so
	 *   an `is_product()` gate excluded exactly the pages it runs on: the rail
	 *   stayed expanded above the grid on mobile, looking like a CSS bug.
	 *
	 * The CART is a third surface as of s19 (#42 row C4 puts the stepper on the
	 * cart's quantity fields too) and is deliberately NOT in the list above —
	 * enqueue_cart_behaviour() handles it, for the reason written there.
	 *
	 * Not unconditional, unlike the CSS bundle next to it: that one is inert on
	 * a page without Woo markup because every rule is scoped, whereas a script
	 * is a network request whether or not it finds anything to do.
	 */
	public function enqueue_storefront_behaviour(): void {
		if ( ! is_product() && ! is_shop() && ! is_product_taxonomy() ) {
			return;
		}

		$this->enqueue_behaviour_module();
	}

	/**
	 * Enqueue the behaviour module for the CART's quantity stepper (#42, C4),
	 * from a hook inside the cart markup rather than from
	 * `wp_enqueue_scripts`.
	 *
	 * WHY NOT JUST ADD `is_cart()` TO THE GATE ABOVE. `wp_enqueue_scripts` runs
	 * before `the_content`, i.e. before the `[woocommerce_cart]` shortcode has
	 * executed — and it is that shortcode which defines `WOOCOMMERCE_CART`, the
	 * constant `is_cart()` looks at first (`wc-conditional-functions.php`). At
	 * enqueue time `is_cart()` therefore falls back to comparing the queried
	 * object against `woocommerce_cart_page_id`, which is true on the store's
	 * designated Cart page and FALSE for the same shortcode on any other page.
	 * A store can legitimately put `[woocommerce_cart]` elsewhere, and this
	 * theme's own e2e fixture does exactly that (the Cart page option keeps
	 * pointing at the block Cart page — `tests/e2e-woo/global-setup.mjs`,
	 * `CLASSIC_CART_PAGE_SLUG`). A page conditional cannot answer "will the
	 * cart render on this request"; the cart's own hook can, because it only
	 * fires when it already has.
	 *
	 * Enqueuing a script module mid-body is fine: `WP_Script_Modules` prints
	 * enqueued modules on `wp_footer` as well as `wp_head`, and
	 * `woocommerce_before_cart` fires during `the_content`, long before either
	 * footer hook.
	 *
	 * The stepper buttons ship with the `hidden` attribute and this module is
	 * what removes it (`src/js/woo.js`), so without this the cart carries two
	 * permanently invisible buttons — which is exactly what it did when C4 first
	 * landed, and is the same "a new surface silently gets no script" failure
	 * the docblock above already records once.
	 */
	public function enqueue_cart_behaviour(): void {
		$this->enqueue_behaviour_module();
	}

	/**
	 * Resolve the behaviour module out of the Vite manifest and enqueue it.
	 *
	 * Idempotent: `wp_enqueue_script_module()` keys on the handle, so the two
	 * callers above cannot double-print it.
	 */
	private function enqueue_behaviour_module(): void {
		$dist     = get_template_directory() . '/assets/dist';
		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$manifest = BaseAssets::read_manifest( $dist . '/.vite/manifest.json' );
		$js       = BaseAssets::entry_file( $manifest, self::WOO_JS_ENTRY );

		if ( null !== $js ) {
			wp_enqueue_script_module( 'woodev-base-woo-js', "{$dist_uri}/{$js}", [], null );
		}
	}
}
