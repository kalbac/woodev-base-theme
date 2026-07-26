<?php
/**
 * Composition root.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/**
 * Theme composition root — wires up and boots the core services.
 */
final class Theme {

	/**
	 * Instantiate and register the theme's core services.
	 */
	public static function boot(): void {
		( new Setup() )->register();
		( new Assets() )->register();
		( new Customizer\Customizer() )->register();
		( new Customizer\InlineStyles() )->register();
		( new Scheme() )->register();

		if ( class_exists( 'WooCommerce' ) ) {
			( new Woo\Woo() )->register();
		}
	}
}
