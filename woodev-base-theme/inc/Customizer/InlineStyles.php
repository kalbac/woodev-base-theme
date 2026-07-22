<?php
/**
 * Compiles the Customizer settings into a single inline <style>.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Customizer;

/**
 * Emits the settings that are CSS custom properties (spec §6).
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
 * KNOWN LIMITATION, dev mode only: under WOODEV_BASE_DEV the pack CSS is served
 * by Vite as a JS module that injects its <style> when the module EXECUTES,
 * i.e. after this block was parsed — so tokens.generated.css wins on source
 * order and Customizer overrides appear to do nothing. Production is
 * unaffected (an e2e mutation pins it: moving this to priority 5 turns the
 * accent-preset assertion red). Raising specificity would fix dev at the cost
 * of every real site's override path, which is the wrong trade.
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
	 */
	public function print_styles(): void {
		$css = self::build_css();

		if ( '' === $css ) {
			return;
		}

		// Every value is drawn from a closed set (Settings::RADIUS_SCALE, the
		// clamped ints, or the oklch-pinned preset map), so there is nothing to
		// escape; wp_strip_all_tags is the belt to that braces.
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

		$radius = Settings::radius_scale();
		if ( Settings::RADIUS_DEFAULT !== $radius ) {
			$root['--radius'] = Settings::radius_value( $radius );
		}

		$preset = Settings::primary_preset();
		$dark   = [];

		// The map really is read twice — primary_preset() validates the slug
		// against its own read, and this is a second one — so the key check is
		// what makes that safe, not the read count. Indexing blind would turn a
		// file that changed between the two reads (a build running against a live
		// site) into an undefined index, a null $tuple and a TypeError: a
		// front-end fatal. Two cheap reads of an opcached file beat a cache whose
		// staleness would then need its own invalidation story.
		$tuple = Settings::presets()[ $preset ] ?? null;

		if ( Settings::PRIMARY_PRESET_DEFAULT !== $preset && null !== $tuple ) {
			$root = array_merge( $root, $tuple['light'] );
			$dark = $tuple['dark'];
		}

		$css = self::rule( ':root', $root ) . self::rule( '.dark', $dark );

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
