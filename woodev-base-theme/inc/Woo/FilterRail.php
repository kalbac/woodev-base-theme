<?php
/**
 * The catalogue filter rail: chrome around WooCommerce's own filter widgets.
 *
 * @package Woodev\Theme\Base\Woo
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/**
 * Registers the `sidebar-shop` widget area's chrome and the small set of hooks
 * that make it behave like a filter rail, without building any filtering logic
 * of our own. WooCommerce already ships the widgets/blocks that filter the
 * catalogue (Product Categories, Filter by Price, Filter by Attribute, Filter
 * by Stock, Rating Filter, Active Filters) — this class only renders the
 * `<aside>` shell around whatever the admin drops into that widget area, and
 * reacts to the same query vars Woo itself reacts to.
 *
 * Where the rail is emitted, and why it is not one of the two obvious hooks:
 *
 * - `woocommerce_sidebar` (`@hooked woocommerce_get_sidebar — 10`) is the hook
 *   a theme is "supposed" to restore for a sidebar. Support::register() already
 *   removes it, and this class does not bring it back, because
 *   `templates/archive-product.php` (Woo 8.6.0, confirmed in the installed
 *   10.9.4 copy) fires it AFTER `woocommerce_after_main_content` — i.e. after
 *   Support::close_wrapper() has already closed both the grid div and the
 *   content div. Anything printed there is a sibling of `.wtb-shop-layout`,
 *   not a grid child of it, so the two-column layout the mockup calls for
 *   cannot be built from that hook. It also fires unconditionally on
 *   `single-product.php`, which is out of scope here entirely.
 * - `woocommerce_before_shop_loop` sits at the right nesting depth (inside the
 *   content area) but only runs inside `if ( woocommerce_product_loop() )` —
 *   it is skipped entirely when a filter narrows the result set to zero
 *   products, which is exactly the moment a visitor most needs the rail (and
 *   its Reset link) still on screen.
 *
 * Instead, `Support::open_wrapper()` fires a new action, `woodev_base_shop_rail`,
 * at the one position that is both inside the grid and unconditional: between
 * the grid's opening tag and the results column. This class is the only
 * listener WordPress ships, but the action is a normal, documented extension
 * point — a plugin (or a child theme) can hook it to add something above or
 * below the rail, or `remove_action( 'woodev_base_shop_rail', [ $filter_rail,
 * 'render' ] )` to replace the rail outright, the same way it could unhook
 * `woocommerce_get_sidebar` from `woocommerce_sidebar` on a classic theme. It
 * fires at most once per request, only on `is_shop() || is_product_taxonomy()`,
 * and only once Support has already decided the rail has something to show.
 */
final class FilterRail {

	/**
	 * The widget area the rail renders.
	 */
	public const SIDEBAR_ID = 'sidebar-shop';

	/**
	 * Query vars WooCommerce itself treats as filter state, mirrored from
	 * `WC_Query::is_query_var_valid_on_front_page()` (installed WooCommerce
	 * 10.9.4, `includes/class-wc-query.php`). That method is Woo's own
	 * authoritative list of "is this request var one of the filter widgets'":
	 * `min_price` / `max_price` (Filter by Price), `rating_filter` (Rating
	 * Filter), any `filter_*` (Filter by Attribute per-taxonomy, and the
	 * Filter by Stock block's `filter_stock_status`), and any `query_type_*`
	 * (the attribute filter's AND/OR toggle). Nothing here is invented: every
	 * name is read off that method, not guessed.
	 */
	private const SCALAR_FILTER_VARS = [ 'min_price', 'max_price', 'rating_filter' ];

	/**
	 * Query var prefixes that also mean "a filter is active" — see
	 * SCALAR_FILTER_VARS.
	 */
	private const PREFIX_FILTER_VARS = [ 'filter_', 'query_type_' ];

	/**
	 * Hook the rail's rendering and the column count into WordPress.
	 */
	public function register(): void {
		add_action( 'woodev_base_shop_rail', [ $this, 'render' ] );
		add_filter( 'loop_shop_columns', [ $this, 'columns' ] );
	}

	/**
	 * Whether the rail has anything to show on the current request.
	 *
	 * True only on a product archive (the shop page or a product taxonomy
	 * term) with at least one widget in `sidebar-shop` — an empty widget area
	 * would render an `<aside>` with a head and nothing under it. Support.php
	 * reads this to decide which wrapper markup to emit; render() and
	 * columns() read the same method so all three stay in agreement.
	 */
	public static function is_active(): bool {
		return ( is_shop() || is_product_taxonomy() ) && is_active_sidebar( self::SIDEBAR_ID );
	}

	/**
	 * Render the `<aside class="wtb-filter-rail">` shell.
	 *
	 * Hooked to `woodev_base_shop_rail`, fired by Support::open_wrapper() only
	 * when is_active() already returned true, so this does not re-check it —
	 * a plugin calling do_action( 'woodev_base_shop_rail' ) directly outside
	 * that context is responsible for its own guard.
	 */
	public function render(): void {
		get_template_part( 'template-parts/woo/filter-rail' );
	}

	/**
	 * Filter callback for `loop_shop_columns`: 3 columns while the rail is
	 * active, whatever was passed in everywhere else.
	 *
	 * `mixed` rather than `int`, for the reason CardActionsWrapper::wrap()
	 * documents at length. `loop_shop_columns` is third-party filterable, and
	 * `apply_filters()` is called from WordPress's own `plugin.php`, which does
	 * not `declare(strict_types=1)` — so the call into this method is COERCIVE,
	 * and a plugin ahead of us returning `null`, an array, or a non-numeric
	 * string turns a typed `int $columns` into a TypeError on a front-end
	 * request rather than a wrong column count. Failing closed on foreign input
	 * is the house rule after the s5 `(string) get_theme_mod()` fatal, and it
	 * costs nothing here: when the rail is inactive the value is handed back
	 * untouched, whatever it is, and Woo's own `absint()` downstream decides
	 * what to make of it.
	 *
	 * @param mixed $columns Woo's requested column count.
	 */
	public function columns( mixed $columns ): mixed {
		return self::is_active() ? 3 : $columns;
	}

	/**
	 * The current archive URL with every active Woo filter query var removed,
	 * or '' when no filter is active.
	 *
	 * Read-only: this inspects the request to decide whether to print a Reset
	 * link and, if so, where it points — nothing here is processed or written,
	 * so there is no nonce to verify.
	 */
	public static function reset_url(): string {
		$active_keys = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: detecting which Woo filter query vars are present to build a Reset link, nothing is processed or written.
		foreach ( array_keys( $_GET ) as $raw_key ) {
			$raw_key = (string) $raw_key;

			if ( self::is_filter_query_var( sanitize_key( $raw_key ) ) ) {
				$active_keys[] = $raw_key;
			}
		}

		if ( [] === $active_keys ) {
			return '';
		}

		return (string) remove_query_arg( $active_keys );
	}

	/**
	 * Whether a query var name is one WooCommerce treats as filter state. See
	 * the SCALAR_FILTER_VARS docblock for where this list comes from.
	 *
	 * @param string $key Sanitized query var name.
	 */
	private static function is_filter_query_var( string $key ): bool {
		if ( \in_array( $key, self::SCALAR_FILTER_VARS, true ) ) {
			return true;
		}

		foreach ( self::PREFIX_FILTER_VARS as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}
}
