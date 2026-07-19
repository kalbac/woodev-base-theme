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

		/*
		 * Parsed, not string-sliced. Two earlier attempts located the tag
		 * boundaries with strpos()/strrpos() and both were wrong in ways that
		 * produced plausible-looking output: the last '</svg>' in the file can
		 * belong to a trailing comment, and the first one can sit inside an
		 * attribute value (`<path d="M0 </svg>">`). Each fix invited the next
		 * special case, because the real problem was parsing XML without a
		 * parser. libxml rejects both shapes outright.
		 *
		 * No LIBXML_NOENT: entity substitution is what turns a hostile document
		 * into an XXE read of the filesystem. LIBXML_NONET blocks network
		 * fetches for the same reason.
		 */
		$dom      = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		$loaded   = $dom->loadXML( $file, LIBXML_NONET );

		\libxml_clear_errors();
		\libxml_use_internal_errors( $previous );

		if ( ! $loaded || ! $dom->documentElement instanceof \DOMElement || 'svg' !== $dom->documentElement->nodeName ) {
			return '';
		}

		$inner = '';

		foreach ( $dom->documentElement->childNodes as $child ) {
			// The upstream license comment lives outside the root, but drop any
			// comment anyway — a comment inlined into a page is dead weight at
			// best and an unterminated `<!--` at worst.
			if ( $child instanceof \DOMComment ) {
				continue;
			}

			$inner .= (string) $dom->saveXML( $child );
		}

		self::$cache[ $path ] = \trim( $inner );

		return self::$cache[ $path ];
	}

	/**
	 * Absolute path of the vendored icon directory.
	 */
	private static function directory(): string {
		return get_template_directory() . '/assets/static/icons';
	}
}
