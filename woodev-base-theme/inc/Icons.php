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
	 * @param string               $name Icon slug, e.g. 'chevron-down'.
	 * @param array<string, mixed> $args Optional arguments.
	 *
	 *     @type string $class CSS class for the root element. Default ''.
	 *     @type int    $size  Rendered width/height in px. Default 24.
	 * @return string Markup, or '' when the icon does not exist.
	 */
	public static function get( string $name, array $args = [] ): string {
		if ( 1 !== \preg_match( self::NAME_PATTERN, $name ) ) {
			return '';
		}

		$inner = self::inner_markup( $name );

		if ( '' === $inner ) {
			return '';
		}

		$class = isset( $args['class'] ) ? (string) $args['class'] : '';
		$size  = isset( $args['size'] ) ? (int) $args['size'] : 24;

		$attributes = [
			'xmlns'           => 'http://www.w3.org/2000/svg',
			'width'           => (string) $size,
			'height'          => (string) $size,
			'viewBox'         => '0 0 24 24',
			'fill'            => 'none',
			'stroke'          => 'currentColor',
			'stroke-width'    => '2',
			'stroke-linecap'  => 'round',
			'stroke-linejoin' => 'round',
		];

		if ( '' !== $class ) {
			$attributes['class'] = $class;
		}

		$rendered = '';
		foreach ( $attributes as $attribute => $value ) {
			$rendered .= \sprintf( ' %s="%s"', $attribute, esc_attr( $value ) );
		}

		return '<svg' . $rendered . '>' . $inner . '</svg>';
	}

	/**
	 * Everything between the upstream <svg> tags, with the tags themselves
	 * discarded.
	 *
	 * @param string $name Icon slug, already validated against NAME_PATTERN.
	 * @return string Inner markup, or '' when the file is missing or malformed.
	 *
	 * Re-emitting our own wrapper rather than rewriting theirs means an upstream
	 * change to the opening tag cannot leak attributes into our markup.
	 */
	private static function inner_markup( string $name ): string {
		if ( isset( self::$cache[ $name ] ) ) {
			return self::$cache[ $name ];
		}

		$path = self::directory() . '/' . $name . '.svg';

		if ( ! \is_file( $path ) || ! \is_readable( $path ) ) {
			return '';
		}

		$file = (string) \file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a vendored theme asset off local disk, not a remote request.

		// Anchor to the <svg> tag, not to the first '>' in the file: every
		// lucide-static file opens with `<!-- @license lucide-static v1.25.0 - ISC -->`,
		// so searching from position 0 finds the comment's '>' and drags the whole
		// opening <svg> tag into the "inner" markup. Verified against v1.25.0.
		$start = \strpos( $file, '<svg' );

		if ( false === $start ) {
			return '';
		}

		$open  = \strpos( $file, '>', $start );
		$close = \strrpos( $file, '</svg>' );

		if ( false === $open || false === $close || $close <= $open ) {
			return '';
		}

		self::$cache[ $name ] = \trim( \substr( $file, $open + 1, $close - $open - 1 ) );

		return self::$cache[ $name ];
	}

	/**
	 * Absolute path of the vendored icon directory.
	 */
	private static function directory(): string {
		return get_template_directory() . '/assets/static/icons';
	}
}
