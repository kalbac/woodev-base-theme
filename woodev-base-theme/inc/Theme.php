<?php
/**
 * Composition root.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

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
	}
}
