<?php
/**
 * Colour-scheme resolution: the admin default, the visitor-toggle flag, and
 * the `<html>` class that follows from them (spec §6).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

/**
 * One validator per setting, used twice: as the Customizer sanitize_callback
 * and as the front-end resolver — the same pattern as Customizer\Settings.
 */
final class Scheme {

	/**
	 * The closed set of admin-selectable schemes. `system` is deliberate: it
	 * sets no class at all, which is what lets the generated
	 * `prefers-color-scheme` CSS block decide for a visitor with JS disabled.
	 */
	public const SCHEMES = [ 'system', 'light', 'dark' ];

	public const DEFAULT_SCHEME = 'system';

	/**
	 * Customizer sanitize callback for `color_scheme_default`.
	 *
	 * Fails closed to `system` for anything outside the closed set, including
	 * non-scalars — a non-string can never be a valid scheme.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_default( mixed $value ): string {
		return \is_string( $value ) && \in_array( $value, self::SCHEMES, true )
			? $value
			: self::DEFAULT_SCHEME;
	}

	/**
	 * The admin's chosen default scheme.
	 */
	public static function default(): string {
		return self::sanitize_default( get_theme_mod( 'color_scheme_default', self::DEFAULT_SCHEME ) );
	}

	/**
	 * Customizer sanitize callback for `color_scheme_toggle`.
	 *
	 * Mirrors what a WP checkbox actually submits: `true` and the string `'1'`
	 * are on, everything else is off. This is the same accept-set WP core
	 * uses for its own checkbox settings — e.g. `header_text` sanitizes with
	 * `absint()` (1/'1' become 1, everything else 0), and
	 * `WP_Customize_Control::render_content()` marks a checkbox checked via
	 * `checked( $this->value() )`, which core's `checked()` helper resolves by
	 * comparing `(string) $value === '1'`. A non-scalar (object, array) is
	 * never a legitimate checkbox value, so it fails closed to `false` rather
	 * than being coerced.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_toggle( mixed $value ): bool {
		if ( \is_bool( $value ) ) {
			return $value;
		}

		if ( \is_string( $value ) ) {
			return '1' === $value;
		}

		return false;
	}

	/**
	 * Whether the front-end switcher control should render at all.
	 */
	public static function toggle_enabled(): bool {
		return self::sanitize_toggle( get_theme_mod( 'color_scheme_toggle', true ) );
	}

	/**
	 * The class for `<html>` that reflects the admin's EXPLICIT choice.
	 *
	 * `system` returns '' on purpose: no class is what lets the generated
	 * `prefers-color-scheme` block in the token CSS decide for a JS-disabled
	 * visitor. An explicit `light`/`dark` choice IS a class, so it survives
	 * with JS off too.
	 */
	public static function html_class(): string {
		$scheme = self::default();

		return self::DEFAULT_SCHEME === $scheme ? '' : $scheme;
	}
}
