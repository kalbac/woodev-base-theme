<?php
/**
 * Theme class autoloader. PSR-4-style file names under inc/ (WPCS filename sniff disabled by design).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

const NS_PREFIX = __NAMESPACE__ . '\\';

/**
 * Map a fully-qualified class name in our namespace to its file path.
 */
function class_path( string $class ): ?string {
	if ( ! \str_starts_with( $class, NS_PREFIX ) ) {
		return null;
	}

	$relative = \substr( $class, \strlen( NS_PREFIX ) );

	return __DIR__ . '/' . \str_replace( '\\', '/', $relative ) . '.php';
}

\spl_autoload_register(
	static function ( string $class ): void {
		$path = class_path( $class );

		if ( null !== $path && \is_file( $path ) ) {
			require $path;
		}
	}
);
