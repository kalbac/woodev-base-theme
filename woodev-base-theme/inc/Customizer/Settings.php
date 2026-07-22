<?php
/**
 * Validated access to the appearance settings that compile to CSS.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Customizer;

/**
 * One validator per setting, used twice: as the Customizer sanitize_callback and
 * as the front-end resolver. A value that never passed through here never
 * reaches a CSS custom property.
 */
final class Settings {

	public const CONTAINER_WIDTH_MIN     = 960;
	public const CONTAINER_WIDTH_MAX     = 1920;
	public const CONTAINER_WIDTH_DEFAULT = 1440;

	public const BASE_FONT_SIZE_MIN     = 14;
	public const BASE_FONT_SIZE_MAX     = 20;
	public const BASE_FONT_SIZE_DEFAULT = 16;

	/**
	 * The radius scale, as CSS lengths for `--radius`. Basecoat derives
	 * --radius-md/-lg/-xl from it with calc(), so one value reshapes every
	 * component.
	 */
	public const RADIUS_SCALE = [
		'none' => '0rem',
		'sm'   => '0.375rem',
		'md'   => '0.625rem',
		'lg'   => '1rem',
	];

	public const RADIUS_DEFAULT = 'md';

	/**
	 * "Inherit the active style pack's own primary" — emits no override.
	 */
	public const PRIMARY_PRESET_DEFAULT = 'default';

	/**
	 * The generated primary presets, validated entry by entry.
	 *
	 * Deliberately NOT memoised in a static: the file is a handful of lines that
	 * opcache already holds, this runs a few times per request at most, and a
	 * static cache would silently couple the unit tests to each other — the
	 * first test's get_template_directory() stub would decide the answer for
	 * every later one.
	 *
	 * @return array<string, array{light: array<string, string>, dark: array<string, string>}>
	 */
	public static function presets(): array {
		$path = get_template_directory() . '/inc/generated/primary-presets.php';

		if ( ! \is_file( $path ) || ! \is_readable( $path ) ) {
			return [];
		}

		$raw = require $path;

		return \is_array( $raw ) ? self::normalize( $raw ) : [];
	}

	/**
	 * Keep only well-formed entries.
	 *
	 * The map is our own build artifact, but it is also the single point where
	 * strings enter the inline <style>. Enforcing the shape here means no
	 * consumer has to escape or re-check anything downstream.
	 *
	 * @param array<mixed> $raw Decoded map.
	 * @return array<string, array{light: array<string, string>, dark: array<string, string>}>
	 */
	private static function normalize( array $raw ): array {
		$clean = [];

		foreach ( $raw as $slug => $schemes ) {
			if ( ! \is_string( $slug ) || ! \is_array( $schemes ) ) {
				continue;
			}

			$light = self::normalize_scheme( $schemes['light'] ?? null );
			$dark  = self::normalize_scheme( $schemes['dark'] ?? null );

			if ( [] === $light || [] === $dark ) {
				continue;
			}

			$clean[ $slug ] = [
				'light' => $light,
				'dark'  => $dark,
			];
		}

		return $clean;
	}

	/**
	 * The three custom properties of one scheme, or [] if anything is off.
	 *
	 * @param mixed $scheme Candidate scheme.
	 * @return array<string, string>
	 */
	private static function normalize_scheme( mixed $scheme ): array {
		if ( ! \is_array( $scheme ) ) {
			return [];
		}

		$clean = [];

		foreach ( [ '--primary', '--primary-foreground', '--ring' ] as $property ) {
			$value = $scheme[ $property ] ?? null;

			// Pinned to the generator's own output shape: digits, dots, spaces
			// and percent signs inside oklch(). Anything else cannot have come
			// from `npm run tokens` and must not reach a <style> block.
			if ( ! \is_string( $value ) || 1 !== preg_match( '/^oklch\([\d.% ]+\)$/', $value ) ) {
				return [];
			}

			$clean[ $property ] = $value;
		}

		return $clean;
	}

	/**
	 * Customizer sanitize callback for `primary_preset`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_primary_preset( mixed $value ): string {
		if ( ! \is_string( $value ) ) {
			return self::PRIMARY_PRESET_DEFAULT;
		}

		return isset( self::presets()[ $value ] ) ? $value : self::PRIMARY_PRESET_DEFAULT;
	}

	/**
	 * The chosen accent preset slug, or `default` to inherit the pack.
	 */
	public static function primary_preset(): string {
		return self::sanitize_primary_preset( get_theme_mod( 'primary_preset', self::PRIMARY_PRESET_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `container_width`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_container_width( mixed $value ): int {
		return self::clamp( $value, self::CONTAINER_WIDTH_MIN, self::CONTAINER_WIDTH_MAX, self::CONTAINER_WIDTH_DEFAULT );
	}

	/**
	 * Content container cap, in pixels.
	 */
	public static function container_width(): int {
		return self::sanitize_container_width( get_theme_mod( 'container_width', self::CONTAINER_WIDTH_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `base_font_size`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_base_font_size( mixed $value ): int {
		return self::clamp( $value, self::BASE_FONT_SIZE_MIN, self::BASE_FONT_SIZE_MAX, self::BASE_FONT_SIZE_DEFAULT );
	}

	/**
	 * Root font size, in pixels.
	 */
	public static function base_font_size(): int {
		return self::sanitize_base_font_size( get_theme_mod( 'base_font_size', self::BASE_FONT_SIZE_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `radius_scale`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_radius_scale( mixed $value ): string {
		return \is_string( $value ) && isset( self::RADIUS_SCALE[ $value ] )
			? $value
			: self::RADIUS_DEFAULT;
	}

	/**
	 * The chosen radius step.
	 */
	public static function radius_scale(): string {
		return self::sanitize_radius_scale( get_theme_mod( 'radius_scale', self::RADIUS_DEFAULT ) );
	}

	/**
	 * The CSS length for a radius step.
	 *
	 * @param string $step Candidate radius step; sanitized before lookup.
	 */
	public static function radius_value( string $step ): string {
		return self::RADIUS_SCALE[ self::sanitize_radius_scale( $step ) ];
	}

	/**
	 * Numeric setting reduced to an int inside [min, max].
	 *
	 * Non-numeric input (array, object, "wide") falls back rather than casting:
	 * (int) on an object throws, and (int) 'wide' is a silent 0 that would
	 * collapse the layout.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $min      Lower bound.
	 * @param int   $max      Upper bound.
	 * @param int   $fallback Value for non-numeric input.
	 */
	private static function clamp( mixed $value, int $min, int $max, int $fallback ): int {
		if ( ! \is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $min, min( $max, (int) round( (float) $value ) ) );
	}
}
