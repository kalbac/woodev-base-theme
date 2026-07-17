<?php
/**
 * Composition root.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

final class Theme {

	public static function boot(): void {
		( new Setup() )->register();
		( new Assets() )->register();
	}
}
