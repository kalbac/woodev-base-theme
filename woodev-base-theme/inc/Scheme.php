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

	/**
	 * Hook the scheme resolution into WordPress:
	 *
	 * - `language_attributes` gets the server-rendered class, so a no-JS
	 *   visitor with an explicit admin default still gets it.
	 * - `wp_head` priority 1 prints the resolver script — before anything
	 *   else in <head> has a chance to paint. Anything deferred, or printed
	 *   later, paints first, and that flash is exactly what this exists to
	 *   prevent.
	 */
	public function register(): void {
		add_filter( 'language_attributes', [ $this, 'add_html_class' ] );
		add_action( 'wp_head', [ $this, 'print_head_script' ], 1 );
	}

	/**
	 * Filter callback for `language_attributes`: appends the resolved class
	 * to the lang/dir attributes WordPress already built. `system` leaves the
	 * output untouched — html_class() returns '' for it on purpose.
	 *
	 * @param string $output The attribute string WordPress already built.
	 */
	public function add_html_class( string $output ): string {
		// language_attributes() is not ours alone. Core's oEmbed header template
		// (wp-includes/theme-compat/header-embed.php:19) opens the html element
		// with language_attributes() followed by a literal class="no-js"
		// attribute — appending our own class there produces TWO class
		// attributes on one element: invalid
		// markup, a wp.org Theme Review exposure, and browsers keep the first,
		// so core's own no-js marker is the one that gets dropped. The embed
		// iframe loads none of this theme's CSS either, so the class would do
		// nothing even if it were valid. Verified against the shipped core file,
		// not assumed. Same reasoning for admin screens.
		if ( is_embed() || is_admin() ) {
			return $output;
		}

		$class = self::html_class();

		if ( '' === $class ) {
			return $output;
		}

		return $output . ' class="' . esc_attr( $class ) . '"';
	}

	/**
	 * Print the resolver script, wrapped in its <script> tag.
	 *
	 * Must run at wp_head priority 1: synchronous and as early as possible,
	 * because a script painted after the browser's first paint cannot prevent
	 * the flash it exists to avoid.
	 */
	public function print_head_script(): void {
		// The body is assembled by build_head_script() from wp_json_encode()
		// output only (a closed-set string, see SCHEMES) — never a raw
		// concatenated value. esc_html() would be wrong here: it would
		// entity-encode characters (`&&`, `<`) that are syntactically
		// meaningful in JS. See phpcs.xml.dist for the scoped deviation this
		// file carries, matching Customizer\InlineStyles::print_styles().
		echo '<script id="woodev-base-scheme">' . "\n" . self::build_head_script() . "\n" . '</script>' . "\n";
	}

	/**
	 * The resolver script body (no wrapping <script> tags).
	 *
	 * `localStorage` access THROWS — it does not return null — in Safari
	 * private mode and whenever cookies/storage are blocked. An uncaught
	 * exception there would abort the whole script and leave `<html>`
	 * without its class, so the read is wrapped in try/catch.
	 *
	 * That read is only emitted at all when the toggle is on: with the
	 * toggle off there is no stored visitor choice to honour, and the string
	 * "localStorage" must not appear in the output — not merely be guarded
	 * behind a runtime condition that happens to be false.
	 */
	public static function build_head_script(): string {
		$default = wp_json_encode( self::default() );

		$lines = [ '(function () {', "\tvar root = document.documentElement;" ];

		if ( self::toggle_enabled() ) {
			$lines[] = "\tvar stored = null;";
			$lines[] = "\ttry {";
			$lines[] = "\t\tstored = localStorage.getItem( 'wtb-scheme' );";
			$lines[] = "\t} catch ( e ) {}";
			$lines[] = "\tvar scheme = stored === 'light' || stored === 'dark' ? stored : {$default};";
		} else {
			$lines[] = "\tvar scheme = {$default};";
		}

		$lines[] = "\troot.classList.remove( 'light', 'dark' );";
		$lines[] = "\tif ( scheme !== 'system' ) {";
		$lines[] = "\t\troot.classList.add( scheme );";
		$lines[] = "\t}";
		$lines[] = '})();';

		return implode( "\n", $lines );
	}
}
