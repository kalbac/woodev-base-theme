<?php
/**
 * Declared WooCommerce theme support.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

/**
 * Declares the theme_support flags WooCommerce reads on boot.
 */
final class Support {

	/**
	 * Hook Woo support declaration into WordPress.
	 */
	public function register(): void {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );
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
}
