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
	 *     @type string $label Accessible name. Empty (default) marks the icon
	 *                         decorative and hides it from assistive tech.
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
		$label = isset( $args['label'] ) ? \trim( (string) $args['label'] ) : '';

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

		if ( '' === $label ) {
			$attributes['aria-hidden'] = 'true';
			$attributes['focusable']   = 'false';
		} else {
			$attributes['role']       = 'img';
			$attributes['aria-label'] = $label;
		}

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
		$path = self::directory() . '/' . $name . '.svg';

		// Keyed by full path, not by name: get_template_directory() is part of
		// what identifies the file, and a long-running worker (FrankenPHP,
		// Swoole, a multisite process switching themes) outlives the request
		// this static property was first populated in.
		if ( isset( self::$cache[ $path ] ) ) {
			return self::$cache[ $path ];
		}

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

		$open = \strpos( $file, '>', $start );

		// The FIRST closing tag after the root opens, not the last one in the
		// file. strrpos() would be taken in by a trailing comment containing a
		// literal `</svg>`: the extracted "inner" markup would then carry the
		// real closing tag plus an unterminated `<!--`, and our own `</svg>`
		// would land inside that comment — silently commenting out whatever
		// followed the icon on the page. Taking the first close is safe because
		// these files have exactly one root element, which IconAssetsTest pins.
		$close = false === $open ? false : \strpos( $file, '</svg>', $open );

		if ( false === $open || false === $close ) {
			return '';
		}

		self::$cache[ $path ] = \trim( \substr( $file, $open + 1, $close - $open - 1 ) );

		return self::$cache[ $path ];
	}

	/**
	 * Absolute path of the vendored icon directory.
	 */
	private static function directory(): string {
		return get_template_directory() . '/assets/static/icons';
	}
}
