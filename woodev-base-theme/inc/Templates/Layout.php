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
 * Templates ask this class; they never read theme_mods directly. The sanitizers
 * below are the SAME callbacks Customizer\Customizer registers for these
 * settings, so the value the admin can store and the value a template can
 * receive are validated by one piece of code — and a stale or hand-edited
 * theme_mod still cannot reach get_template_part().
 */
final class Layout {

	public const HEADER_VARIANTS   = [ 'inline', 'centered' ];
	public const FOOTER_VARIANTS   = [ 'simple', 'columns' ];
	public const SIDEBAR_POSITIONS = [ 'none', 'right' ];

	/**
	 * Which header part to load.
	 */
	public static function header_variant(): string {
		return self::sanitize_header_variant( get_theme_mod( 'header_variant', 'inline' ) );
	}

	/**
	 * Which footer part to load.
	 */
	public static function footer_variant(): string {
		return self::sanitize_footer_variant( get_theme_mod( 'footer_variant', 'simple' ) );
	}

	/**
	 * Where the sidebar column goes, when it is shown at all.
	 */
	public static function sidebar_position(): string {
		return self::sanitize_sidebar_position( get_theme_mod( 'sidebar_position', 'none' ) );
	}

	/**
	 * Customizer sanitize callback for `header_variant`.
	 *
	 * @param mixed $value Raw value from the Customizer or the database.
	 */
	public static function sanitize_header_variant( mixed $value ): string {
		return self::validate( $value, self::HEADER_VARIANTS, 'inline' );
	}

	/**
	 * Customizer sanitize callback for `footer_variant`.
	 *
	 * @param mixed $value Raw value from the Customizer or the database.
	 */
	public static function sanitize_footer_variant( mixed $value ): string {
		return self::validate( $value, self::FOOTER_VARIANTS, 'simple' );
	}

	/**
	 * Customizer sanitize callback for `sidebar_position`.
	 *
	 * @param mixed $value Raw value from the Customizer or the database.
	 */
	public static function sanitize_sidebar_position( mixed $value ): string {
		return self::validate( $value, self::SIDEBAR_POSITIONS, 'none' );
	}

	/**
	 * Whether the current view renders the sidebar column.
	 */
	public static function has_sidebar(): bool {
		if ( 'right' !== self::sidebar_position() ) {
			return false;
		}

		// An empty widget area would render a column of nothing and narrow the
		// content for no reason.
		if ( ! is_active_sidebar( 'sidebar-1' ) ) {
			return false;
		}

		// Spec §7: blog, archive, search results and single posts. A positive
		// allow-list, not `! is_page()`: the negative form also matched 404s,
		// attachments and every singular post type a plugin registers — layouts
		// nobody asked to put a sidebar on.
		//
		// is_singular( 'post' ), NOT is_single(): core sets is_single on
		// attachment queries too, and it is true for every public custom post
		// type, so is_single() would have let both back in through the very
		// allow-list meant to keep them out.
		return is_home() || is_archive() || is_search() || is_singular( 'post' );
	}

	/**
	 * Fall back to a known-good value when the stored one is not on the allow list.
	 *
	 * The parameter is `mixed`, not `string`, on purpose, and NOTHING here casts.
	 * get_theme_mod() returns mixed: the value lives in the database and can be
	 * reshaped by a `theme_mod_*` filter or a half-migrated option. A `(string)`
	 * cast anywhere on this path emits "Array to string conversion" for an array
	 * and throws Error for an object without __toString() — a fatal on every
	 * front-end request, since these resolvers run from header.php and
	 * footer.php. LayoutTest pins that: re-introducing the cast turns
	 * test_a_non_string_header_variant_falls_back_instead_of_fataling red.
	 *
	 * The strict in_array() is what makes the cast unnecessary — a non-string
	 * simply matches nothing and takes the fallback. An `is_string()` guard in
	 * front of it would be dead weight: PHPStan L8 narrows the return type
	 * without it, and no test can tell whether it is present.
	 *
	 * @param mixed    $value    Stored value, any type.
	 * @param string[] $allowed  Permitted values.
	 * @param string   $fallback Value to use when $value is not permitted.
	 */
	private static function validate( mixed $value, array $allowed, string $fallback ): string {
		return \in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
