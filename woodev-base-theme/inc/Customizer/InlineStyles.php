<?php
/**
 * Compiles the Customizer settings into a single inline <style>.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Customizer;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/**
 * Emits the settings that are CSS custom properties (spec §6, extended by
 * ADR-008's five identity controls — T7 of
 * docs/plans/2026-07-25-visual-identity.md).
 *
 * Hooked to wp_head at priority 20 rather than attached with
 * wp_add_inline_style(): that function needs a registered STYLE handle, and in
 * dev mode the CSS is served by Vite as a script module, so the inline block
 * would silently disappear exactly where it is hardest to notice. wp_head runs
 * wp_enqueue_scripts at 1 and prints styles at 8, so 20 puts this after every
 * stylesheet WordPress itself printed.
 *
 * The selectors are a plain `:root` and `.dark` — specificity (0,1,0), the same
 * as Basecoat's and our own token defaults — so the cascade is decided by
 * source order alone. Where each thing lands, precisely:
 *
 * - Enqueued stylesheets, including a CHILD THEME's, print at wp_head 8, i.e.
 *   BEFORE this block. The admin's Customizer choice therefore beats them,
 *   which is the point of the setting existing.
 * - Additional CSS (Appearance -> Customize -> Additional CSS) prints at
 *   wp_head 101, AFTER this block, so a site owner's `:root { --primary: … }`
 *   still wins with no `!important` and no knowledge of our internals.
 *
 * That second line is why the selectors are not doubled. They briefly were
 * (`:root:root`), which made the settings unbeatable by anything short of
 * `!important` — including the one override path WordPress puts in the UI.
 * A child theme that wants to beat a Customizer setting has to be explicit
 * about it (higher specificity, or an enqueue after wp_head 20); that is the
 * correct outcome, since otherwise the setting could never do anything.
 *
 * CASCADE PROOF against the generated `prefers-color-scheme` fallback block
 * (docs/gotchas/not-selector-carries-its-arguments-specificity.md): that
 * block's selector is `:root:where(:not(.light):not(.dark))`, deliberately
 * wrapped in `:where()` so it too stays at specificity (0,1,0) rather than
 * the (0,3,0) two bare `:not()`s would carry. Two rules of EQUAL specificity
 * are decided by source order, and tokens.generated.css (enqueued, printed
 * at wp_head 8) always precedes this block (wp_head 20) — so every setting
 * below wins there precisely because this file never raises its own
 * selector's specificity to "solve" the same problem the token generator
 * already solved once, which is what InlineStylesTest and the integration
 * suite pin (a plain `:root{`, never `:root:root`, never `!important`).
 *
 * KNOWN LIMITATION, dev mode only: under WOODEV_BASE_DEV the CSS bundle is
 * served by Vite as a JS module that injects its <style> when the module
 * EXECUTES, i.e. after this block was parsed — so tokens.generated.css wins
 * on source order and Customizer overrides appear to do nothing in dev mode.
 * Production is unaffected: --radius is declared by both tokens.generated.css
 * and (when the radius theme_mod deviates from default) this block, so
 * moving this hook to a priority before wp_head prints stylesheets (8) would
 * make tokens.generated.css win instead and the radius assertion in
 * tests/e2e/theme-mods.spec.mjs would go red. Raising specificity would fix
 * dev at the cost of every real site's override path, which is the wrong
 * trade.
 */
final class InlineStyles {

	/**
	 * Hook the renderer into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_head', [ $this, 'print_styles' ], 20 );
	}

	/**
	 * Print the block, unless every setting is at its default.
	 *
	 * Every value is drawn from a closed set (Palettes::slugs(),
	 * Settings::FONTS), a clamped int, or ColorConverter's own numeric
	 * output, so there is nothing to escape; wp_strip_all_tags() is the belt
	 * to those braces. esc_html()/esc_attr() would be wrong here — they
	 * encode characters that are syntactically meaningful in CSS.
	 */
	public function print_styles(): void {
		$css = self::build_css();

		if ( '' === $css ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS, not HTML: closed-set values, tag-stripped; see the docblock.
		echo '<style id="woodev-base-inline">' . "\n" . wp_strip_all_tags( $css ) . '</style>' . "\n";
	}

	/**
	 * The CSS for the current settings; '' when nothing deviates from default.
	 */
	public static function build_css(): string {
		$root = [];

		$width = Settings::container_width();
		if ( Settings::CONTAINER_WIDTH_DEFAULT !== $width ) {
			$root['--wtb-container-max'] = "{$width}px";
		}

		$palette = Settings::palette();
		if ( Settings::PALETTE_DEFAULT !== $palette ) {
			$tuple = Palettes::get( $palette );

			$root['--n-h']      = $tuple['n-h'];
			$root['--accent-h'] = $tuple['accent-h'];
			$root['--accent-c'] = $tuple['accent-c'];
		}

		// Overrides the palette's accent-h/-c (or the default's, if the
		// palette is warm-clay), never --n-h: the accent picker replaces the
		// accent only, per ADR-008.
		$accent = Settings::accent();
		if ( Settings::ACCENT_DEFAULT !== $accent ) {
			$oklch = ColorConverter::to_oklch( $accent );

			if ( null !== $oklch ) {
				$root['--accent-h'] = $oklch['h'];
				$root['--accent-c'] = $oklch['c'];
			}
		}

		$radius = Settings::radius();
		if ( Settings::RADIUS_DEFAULT !== $radius ) {
			$root['--radius'] = "{$radius}px";
		}

		$font = Settings::font();
		if ( Settings::FONT_DEFAULT !== $font ) {
			// The only other member of Settings::FONTS is FONT_SYSTEM.
			foreach ( Settings::FONT_SYSTEM_STACK as $property => $value ) {
				$root[ $property ] = $value;
			}
		}

		$css = self::rule( ':root', $root );

		$font_size = Settings::base_font_size();
		if ( Settings::BASE_FONT_SIZE_DEFAULT !== $font_size ) {
			$css .= self::rule( 'html', [ 'font-size' => "{$font_size}px" ] );
		}

		return $css;
	}

	/**
	 * One CSS rule, or '' when it would be empty.
	 *
	 * @param string                $selector    CSS selector.
	 * @param array<string, string> $declarations Property => value.
	 */
	private static function rule( string $selector, array $declarations ): string {
		if ( [] === $declarations ) {
			return '';
		}

		$body = '';

		foreach ( $declarations as $property => $value ) {
			$body .= "{$property}:{$value};";
		}

		return $selector . '{' . rtrim( $body, ';' ) . "}\n";
	}
}
