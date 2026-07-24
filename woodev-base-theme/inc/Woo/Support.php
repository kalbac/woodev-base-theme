<?php
/**
 * Declared WooCommerce theme support and the page-shell wiring.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

/**
 * Declares the theme_support flags WooCommerce reads on boot, and routes
 * WooCommerce page output into the theme's own content shell.
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

		// v1 storefront is full-width by design (see open_wrapper()): drop Woo's
		// default sidebar so no widget column renders and `get_sidebar( 'shop' )`
		// is never called — the latter also silences the core "Theme without
		// sidebar.php is deprecated" notice a sidebar-less classic theme triggers.
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	}

	/**
	 * Declare WooCommerce theme supports.
	 */
	public function setup(): void {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}

	/**
	 * Open the theme's content shell around WooCommerce output.
	 *
	 * `header.php` has already opened `<main class="wtb-container">`, so this only
	 * emits the inner layout region. v1 storefront is full-width by design — no
	 * sidebar column; offering one would be a Layout change, not a tweak here.
	 */
	public function open_wrapper(): void {
		echo '<div class="wtb-layout"><div class="wtb-layout__content">';
	}

	/**
	 * Close the shell opened by open_wrapper().
	 */
	public function close_wrapper(): void {
		echo '</div></div>';
	}
}
