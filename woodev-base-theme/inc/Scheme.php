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
	 * Hook the two front-end surfaces into WordPress.
	 *
	 * The class goes on `<html>` server-side so a JS-disabled visitor still
	 * gets the admin's choice; the head script refines it from the visitor's
	 * stored choice before first paint.
	 */
	public function register(): void {
		add_filter( 'language_attributes', [ $this, 'add_html_class' ] );
		add_action( 'wp_head', [ $this, 'print_head_script' ], 1 );
	}

	/**
	 * Filter callback for `language_attributes`: puts the resolved class on the
	 * attribute string WordPress already built. `system` leaves the output
	 * untouched — html_class() returns '' for it on purpose.
	 *
	 * @param string $output The attribute string WordPress already built.
	 */
	public function add_html_class( string $output ): string {
		// Core's oEmbed header template opens the html element with
		// language_attributes() followed by a literal class="no-js" attribute
		// (wp-includes/theme-compat/header-embed.php:19, checked in the shipped
		// file rather than assumed). That iframe loads none of this theme's CSS,
		// so the class would do nothing there even if it were valid. Same for
		// admin screens.
		if ( is_embed() || is_admin() ) {
			return $output;
		}

		$class = self::html_class();

		if ( '' === $class ) {
			return $output;
		}

		/*
		 * If anything already mentions a class here, LEAVE IT ALONE.
		 *
		 * The obvious alternative — merge our class into the existing attribute
		 * — was implemented and then dismantled across three review rounds, each
		 * finding a new way for the regex to be wrong: a word boundary matched
		 * `data-class=`; `class=no-js`, `class = "x"` and `CLASS=` were all
		 * missed; str_replace() rewrote identical text inside other attributes;
		 * `(^|\s)` cannot prove a match is outside quotes, so ` class=bar` in
		 * `data-note="foo class=bar"` won; a quoted value containing a newline
		 * fell through to the unquoted branch and matched empty. Parsing HTML
		 * attributes with a regex loses; the only question is where.
		 *
		 * So do not parse. The cost of skipping is small and bounded: this
		 * attribute exists ONLY for the visitor with JavaScript disabled — with
		 * JS, the head script sets the class on documentElement directly, where
		 * there is a real DOM and no string to misparse. The cost of getting the
		 * merge wrong is unbounded: a corrupted attribute belonging to someone
		 * else's plugin.
		 *
		 * `stripos` rather than a pattern, deliberately: it over-matches (a
		 * `data-class` or the word inside a value trips it too) and
		 * over-matching is the safe direction here.
		 */
		if ( false !== stripos( $output, 'class' ) ) {
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
	 *
	 * The body is assembled by build_head_script() from wp_json_encode() output
	 * only (a closed-set string, see SCHEMES) — never a raw concatenated value.
	 * esc_html() would be wrong here: it would entity-encode characters that are
	 * syntactically meaningful in JS.
	 */
	public function print_head_script(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS, not HTML: the only interpolated value is wp_json_encode() of a SCHEMES member; see the docblock.
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
