<?php
/**
 * Validated access to the appearance settings that compile to CSS.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Customizer;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

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
	 * ADR-008: `warm-clay` equals the :root defaults, so selecting it is the
	 * one palette that emits no override at all (InlineStyles::build_css()).
	 * Guaranteed resolvable even if inc/generated/palettes.php is missing or
	 * tampered with — see Palettes, which always synthesises this slug.
	 */
	public const PALETTE_DEFAULT = 'warm-clay';

	/**
	 * '' means "no override": the palette's own accent wins. A picked colour
	 * is stored as a normalized 6-digit hex (`#rrggbb`, lowercase) and
	 * converted to --accent-h/--accent-c by ColorConverter at render time —
	 * the theme's palette architecture (ADR-008) has no admin-facing
	 * lightness control, only hue and chroma.
	 */
	public const ACCENT_DEFAULT = '';

	/**
	 * `--radius` is a px BASE, not a step in a rem lookup table — see the
	 * docblock on sanitize_radius() for why this replaced radius_scale
	 * outright instead of reusing its theme_mod key.
	 */
	public const RADIUS_MIN     = 0;
	public const RADIUS_MAX     = 16;
	public const RADIUS_DEFAULT = 10;

	public const FONT_IDENTITY = 'identity';
	public const FONT_SYSTEM   = 'system';
	public const FONT_DEFAULT  = self::FONT_IDENTITY;

	public const FONTS = [ self::FONT_IDENTITY, self::FONT_SYSTEM ];

	/**
	 * The system fallback stack already baked into --font-display/-body/-mono
	 * as the tail of every value in src/tokens/tokens.mjs (ADR-007): picking
	 * `system` here reproduces that exact fallback, so the theme degrades to
	 * its own documented v1 look rather than to something new. Duplicated by
	 * hand rather than parsed from the generated CSS — Settings has no build
	 * dependency — so keep the two in sync if the fallback tail ever changes.
	 *
	 * @var array<string, string>
	 */
	public const FONT_SYSTEM_STACK = [
		'--font-display' => 'system-ui, "Segoe UI", Roboto, sans-serif',
		'--font-body'    => 'system-ui, "Segoe UI", Roboto, sans-serif',
		'--font-mono'    => 'ui-monospace, "SF Mono", Menlo, monospace',
	];

	public const CTA_REVEAL_HOVER   = 'hover';
	public const CTA_REVEAL_ALWAYS  = 'always';
	public const CTA_REVEAL_DEFAULT = self::CTA_REVEAL_HOVER;

	public const CTA_REVEALS = [ self::CTA_REVEAL_HOVER, self::CTA_REVEAL_ALWAYS ];

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
	 * Customizer sanitize callback for `palette`.
	 *
	 * The closed set is Palettes::slugs(), not a hardcoded list of seven: a
	 * tampered or absent inc/generated/palettes.php still resolves (Palettes
	 * always synthesises PALETTE_DEFAULT), so a stored slug that the current
	 * file no longer supports falls back here exactly like any other invalid
	 * value, rather than the request fataling.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_palette( mixed $value ): string {
		return self::closed_set( $value, Palettes::slugs(), self::PALETTE_DEFAULT );
	}

	/**
	 * The admin's chosen palette slug.
	 */
	public static function palette(): string {
		return self::sanitize_palette( get_theme_mod( 'palette', self::PALETTE_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `accent`.
	 *
	 * Accepts a 3- or 6-digit hex colour, with or without a leading '#',
	 * case-insensitively, and normalizes it to lowercase `#rrggbb`. Anything
	 * else — including a non-string, an out-of-shape string, or a value that
	 * merely LOOKS like CSS ('red', 'rgb(0,0,0)', a `sanitize_hex_color()`-
	 * style breakout attempt) — falls back to '' (ACCENT_DEFAULT), which
	 * InlineStyles reads as "no override, the palette's accent wins".
	 *
	 * A dedicated pattern rather than WordPress core's own
	 * sanitize_hex_color(): core returns null on rejection (not the empty
	 * string this class's fail-closed convention needs everywhere else) and
	 * accepts only a leading '#', where a colour picker's raw POST value is
	 * worth normalizing either way.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_accent( mixed $value ): string {
		if ( ! \is_string( $value ) ) {
			return self::ACCENT_DEFAULT;
		}

		$value = \trim( $value );

		if ( 1 !== \preg_match( '/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value, $matches ) ) {
			return self::ACCENT_DEFAULT;
		}

		$hex = \strtolower( $matches[1] );

		if ( 3 === \strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return '#' . $hex;
	}

	/**
	 * The admin's accent override, or '' when the palette's own accent applies.
	 */
	public static function accent(): string {
		return self::sanitize_accent( get_theme_mod( 'accent', self::ACCENT_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `radius`.
	 *
	 * Replaces the retired `radius_scale` theme_mod under a NEW key rather
	 * than reusing it. radius_scale stored one of four STRING steps
	 * ('none'…'lg') mapped to rem lengths; `radius` stores a PX INTEGER
	 * 0–16 directly, so reusing the old key would feed a site's stored
	 * string ('lg') into clamp()'s is_numeric() check, silently fail it, and
	 * collapse to the new default (10px) — reinterpreting a real admin
	 * choice as if it had never been made, with nothing in the UI or the log
	 * to say so. A new key makes the same outcome (old choice not carried
	 * forward) visible instead: `radius_scale` is simply orphaned, and
	 * `radius` starts fresh at its own documented default — which, because
	 * 10px is what `radius_scale = md` used to resolve to (0.625rem at the
	 * 16px root), is visually a no-op for the common case anyway. There is
	 * no shipped release of the pre-identity Customizer to migrate away
	 * from (this theme has not reached v1), so the honest reset costs
	 * nothing today that reuse-and-corrupt would not have cost silently
	 * later.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_radius( mixed $value ): int {
		return self::clamp( $value, self::RADIUS_MIN, self::RADIUS_MAX, self::RADIUS_DEFAULT );
	}

	/**
	 * The chosen radius base, in pixels.
	 */
	public static function radius(): int {
		return self::sanitize_radius( get_theme_mod( 'radius', self::RADIUS_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `font`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_font( mixed $value ): string {
		return self::closed_set( $value, self::FONTS, self::FONT_DEFAULT );
	}

	/**
	 * The admin's chosen font mode: FONT_IDENTITY (Golos Text / IBM Plex, the
	 * default) or FONT_SYSTEM (the OS stack, zero webfont bytes fetched).
	 */
	public static function font(): string {
		return self::sanitize_font( get_theme_mod( 'font', self::FONT_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `cta_reveal`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_cta_reveal( mixed $value ): string {
		return self::closed_set( $value, self::CTA_REVEALS, self::CTA_REVEAL_DEFAULT );
	}

	/**
	 * The admin's chosen add-to-cart reveal mode: CTA_REVEAL_HOVER (default)
	 * or CTA_REVEAL_ALWAYS.
	 *
	 * Consumer: inc/Woo/CtaAttribute.php renders `data-cta="…"` on `<html>`
	 * via the `language_attributes` filter, on every front-end request
	 * except wp-admin and `/wp-login.php` (see that file for the precise
	 * guard and why `is_admin()` alone does not cover the login screen; a
	 * product loop can render on any front-end page through a shortcode or
	 * block, which is why the guard stops at those two exclusions rather
	 * than narrowing to WooCommerce-specific contexts), and calls this
	 * method for the value — so a tampered theme_mod is sanitised before it
	 * can reach markup. Nothing here touches CSS output:
	 * `[data-cta="always"]` is the escape-hatch selector the shipped
	 * stylesheet already keys off.
	 *
	 * This setting governs POINTER devices only. woo.css forces the static,
	 * always-visible treatment under `@media (hover: none)` regardless of what
	 * is stored here, because a touchscreen cannot fire :hover and the default
	 * would otherwise ship an unreachable button to every phone visitor.
	 */
	public static function cta_reveal(): string {
		return self::sanitize_cta_reveal( get_theme_mod( 'cta_reveal', self::CTA_REVEAL_DEFAULT ) );
	}

	/**
	 * Shared shape for a setting whose valid values are a closed set of
	 * strings: a non-string, or a string outside the set, both fall back.
	 *
	 * @param mixed         $value    Raw value.
	 * @param array<string> $set      Valid values.
	 * @param string        $fallback Value for anything outside the set.
	 */
	private static function closed_set( mixed $value, array $set, string $fallback ): string {
		return \is_string( $value ) && \in_array( $value, $set, true )
			? $value
			: $fallback;
	}

	/**
	 * Numeric setting reduced to an int inside [min, max].
	 *
	 * Non-numeric input (array, object, "wide") falls back rather than casting:
	 * (int) on an object throws, and (int) 'wide' is a silent 0 that would
	 * collapse the layout.
	 *
	 * is_numeric() is necessary but NOT sufficient: it accepts overflowing
	 * literals like '1e309', which become INF as a float. Casting a float
	 * outside the integer range is undefined in PHP and yields 0 in practice,
	 * so an absurdly LARGE value would clamp to the MINIMUM. is_finite() is what
	 * turns that into the documented fallback; NAN takes the same path.
	 *
	 * Version note, because it decides how visible the bug is: PHP 8.5 warns
	 * ("The float 1.0E+100 is not representable as an int, cast occurred"), but
	 * PHP 8.1 — this theme's declared floor, and what the test containers run —
	 * is SILENT. On the floor it is a wrong layout with nothing in the log.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $min      Lower bound.
	 * @param int   $max      Upper bound.
	 * @param int   $fallback Value for non-numeric or non-finite input.
	 */
	private static function clamp( mixed $value, int $min, int $max, int $fallback ): int {
		if ( ! \is_numeric( $value ) ) {
			return $fallback;
		}

		$number = (float) $value;

		if ( ! \is_finite( $number ) ) {
			return $fallback;
		}

		// Clamp as a FLOAT, then cast. The other order looks equivalent and is
		// not: casting a float outside the integer range is undefined in PHP —
		// '1e100' passes is_numeric() and is_finite(), then (int) emits "The
		// float 1.0E+100 is not representable as an int" and yields 0, so the
		// largest possible input would clamp to the MINIMUM. Bounding first
		// means the cast only ever sees a value inside [min, max].
		return (int) round( max( (float) $min, min( (float) $max, $number ) ) );
	}
}
