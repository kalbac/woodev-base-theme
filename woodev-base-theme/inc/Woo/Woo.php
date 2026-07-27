<?php
/**
 * Woo layer composition root.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/**
 * Wires up and boots the WooCommerce layer's services.
 */
final class Woo {

	/**
	 * Instantiate and register the Woo layer's services.
	 */
	public function register(): void {
		( new Support() )->register();
		( new Assets() )->register();
		( new BlockAssets() )->register();
		( new CardActionsWrapper() )->register();
		( new CtaAttribute() )->register();
		( new ProductPlaceholder() )->register();
	}
}
