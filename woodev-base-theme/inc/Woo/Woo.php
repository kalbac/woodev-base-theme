<?php
/**
 * Woo layer composition root.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

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
	}
}
