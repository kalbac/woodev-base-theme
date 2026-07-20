<?php
/**
 * Layout decisions: header/footer variants and sidebar visibility.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Templates;

/**
 * Single source of truth for which layout a view gets.
 *
 * Templates ask this class; they never read theme_mods directly. M1-04 adds the
 * Customizer controls that write the same settings, so the validation here is
 * what keeps a stale or hand-edited value from reaching get_template_part().
 */
final class Layout {

	public const HEADER_VARIANTS = [ 'inline', 'centered' ];
	public const FOOTER_VARIANTS = [ 'simple', 'columns' ];

	/**
	 * Which header part to load.
	 */
	public static function header_variant(): string {
		return self::validate( (string) get_theme_mod( 'header_variant', 'inline' ), self::HEADER_VARIANTS, 'inline' );
	}

	/**
	 * Which footer part to load.
	 */
	public static function footer_variant(): string {
		return self::validate( (string) get_theme_mod( 'footer_variant', 'simple' ), self::FOOTER_VARIANTS, 'simple' );
	}

	/**
	 * Whether the current view renders the sidebar column.
	 */
	public static function has_sidebar(): bool {
		if ( 'right' !== get_theme_mod( 'sidebar_position', 'none' ) ) {
			return false;
		}

		// An empty widget area would render a column of nothing and narrow the
		// content for no reason.
		if ( ! is_active_sidebar( 'sidebar-1' ) ) {
			return false;
		}

		// Spec §7: blog, archive and single contexts only. Static pages are
		// author-composed layouts and keep the full width.
		return ! is_page();
	}

	/**
	 * Fall back to a known-good value when the stored one is not on the allow list.
	 *
	 * @param string   $value    Stored value.
	 * @param string[] $allowed  Permitted values.
	 * @param string   $fallback Value to use when $value is not permitted.
	 */
	private static function validate( string $value, array $allowed, string $fallback ): string {
		return \in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
