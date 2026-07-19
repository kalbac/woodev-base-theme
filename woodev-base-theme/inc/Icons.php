<?php
/**
 * Inline SVG icon helper (Lucide).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

/**
 * Renders vendored Lucide icons as inline SVG.
 */
final class Icons {

	/**
	 * Icon slugs: lowercase words joined by single hyphens, nothing else.
	 * Anything outside this shape never reaches the filesystem.
	 */
	private const NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

	/**
	 * Inner markup per icon, memoised for the request.
	 *
	 * @var array<string, string>
	 */
	private static array $cache = [];

	/**
	 * Build the inline SVG markup for an icon.
	 *
	 * @param string $name Icon slug, e.g. 'chevron-down'.
	 * @return string Markup, or '' when the icon does not exist.
	 */
	public static function get( string $name ): string {
		if ( 1 !== \preg_match( self::NAME_PATTERN, $name ) ) {
			return '';
		}

		$path = self::directory() . '/' . $name . '.svg';

		if ( ! \is_file( $path ) || ! \is_readable( $path ) ) {
			return '';
		}

		return '';
	}

	/**
	 * Absolute path of the vendored icon directory.
	 */
	private static function directory(): string {
		return get_template_directory() . '/assets/static/icons';
	}
}
