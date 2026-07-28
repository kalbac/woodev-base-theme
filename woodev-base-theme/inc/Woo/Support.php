<?php
/**
 * Declared WooCommerce theme support and the page-shell wiring.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/**
 * Declares the theme_support flags WooCommerce reads on boot, and routes
 * WooCommerce page output into the theme's own content shell.
 *
 * The shell is full-width by default, but becomes a two-column
 * `.wtb-shop-layout` grid — filter rail plus results — whenever
 * FilterRail::is_active() says the `sidebar-shop` widget area has something
 * to show on the current product archive. See open_wrapper() for exactly
 * where the rail is emitted and why.
 */
final class Support {

	/**
	 * Hook Woo support declaration and page-shell wiring into WordPress.
	 */
	public function register(): void {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );

		// Unlike the deferred setup() hook above, these mutate the live hook
		// registry the instant register() runs: WooCommerce has already added its
		// default wrappers during plugin load (before Theme::boot()), so we swap
		// them out here rather than on a later hook.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
		add_action( 'woocommerce_before_main_content', [ $this, 'open_wrapper' ], 10 );
		add_action( 'woocommerce_after_main_content', [ $this, 'close_wrapper' ], 10 );

		// This stays removed permanently, not just while the rail is inactive:
		// `templates/archive-product.php` fires `woocommerce_sidebar` AFTER
		// `woocommerce_after_main_content`, i.e. after close_wrapper() has already
		// closed both the grid div and the content div. Anything printed there
		// would be a sibling of `.wtb-shop-layout`, never a grid child of it, so
		// this hook cannot build the two-column layout regardless of whether the
		// rail has widgets. FilterRail renders through its own action instead
		// (see open_wrapper()). Removing this also keeps silencing the core
		// "Theme without sidebar.php is deprecated" notice a sidebar-less classic
		// theme would otherwise trigger.
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	}

	/**
	 * Declare WooCommerce theme supports.
	 *
	 * `product_grid` is what puts WooCommerce's own "Products per row" and
	 * "Rows per page" controls into Customizer → WooCommerce → Product Catalog,
	 * and its `default_*` values are what `wc_reset_product_grid_settings()`
	 * writes into `woocommerce_catalog_columns` / `woocommerce_catalog_rows`
	 * when this theme is activated. Those two options are the catalogue's real
	 * page size: `WC_Query::product_query()` multiplies them through
	 * `loop_shop_per_page`, and the product archive's main query reaches that
	 * line with no `posts_per_page` of its own, so they win over the site-wide
	 * Reading setting. Measured on the live store, not assumed — 2 rows x 3
	 * columns turned a single page of ten products into six plus a pager,
	 * while changing `posts_per_page` alone changed nothing at all.
	 *
	 * Three across, not WooCommerce's four: that is what `woo.css` lays out at
	 * the widest breakpoint and what `Woo\FilterRail` forces while the rail is
	 * active, so the number a store owner reads in the Customizer agrees with
	 * what the page renders.
	 */
	public function setup(): void {
		add_theme_support(
			'woocommerce',
			[
				'product_grid' => [
					'default_rows'    => 3,
					'min_rows'        => 1,
					'default_columns' => 3,
					'min_columns'     => 1,
					'max_columns'     => 6,
				],
			]
		);
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}

	/**
	 * Open the theme's content shell around WooCommerce output.
	 *
	 * `header.php` has already opened `<main class="wtb-container">`, so this
	 * only emits the inner layout region.
	 *
	 * Full-width is still the default: most WooCommerce views (single product,
	 * cart, checkout, account) get the plain `.wtb-layout` shell, unchanged from
	 * before the rail existed. The two-column `.wtb-shop-layout` grid only
	 * replaces it when FilterRail::is_active() is true — a product archive with
	 * at least one widget in `sidebar-shop` — and in that case the rail is
	 * fired here, between the grid's opening tag and the results column, via a
	 * dedicated action rather than a direct method call on FilterRail: that
	 * keeps this class from needing to know anything about how the rail
	 * renders itself, and gives a third-party plugin or child theme the same
	 * kind of documented, unhookable-elsewhere extension point
	 * `woocommerce_sidebar` would have been, had Woo's own hook order left it
	 * usable for this layout (it does not — see FilterRail's class docblock
	 * for why `woocommerce_sidebar` and `woocommerce_before_shop_loop` were
	 * both rejected).
	 *
	 * close_wrapper() does not need the same branch: both shells are exactly
	 * two nested divs deep (the rail, when present, opens and closes entirely
	 * within this method), so `</div></div>` closes either one.
	 */
	public function open_wrapper(): void {
		if ( FilterRail::is_active() ) {
			echo '<div class="wtb-shop-layout">';
			do_action( 'woodev_base_shop_rail' );
			echo '<div class="wtb-shop-layout__content">';
			return;
		}

		echo '<div class="wtb-layout"><div class="wtb-layout__content">';
	}

	/**
	 * Close the shell opened by open_wrapper().
	 */
	public function close_wrapper(): void {
		echo '</div></div>';
	}
}
