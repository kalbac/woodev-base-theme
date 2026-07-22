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
 * Source order alone is not enough, though, and the selectors below are doubled
 * (`:root:root`, `:root.dark`) for a reason. Two cases beat a plain `:root` that
 * merely comes later in the document:
 *
 * 1. Dev mode. Vite serves the pack CSS as a JS module that injects its <style>
 *    when the module EXECUTES — after this block was parsed. Its un-layered
 *    `:root` from tokens.generated.css would then win on source order and the
 *    Customizer would look broken in dev only, which is precisely the kind of
 *    dev-path silent failure this theme has shipped before.
 * 2. Any plugin printing un-layered `:root` CSS later in wp_head.
 *
 * Raising specificity (0,2,0 against Basecoat's and our own 0,1,0) makes the
 * admin's choice win on the cascade rather than on luck, in both modes.
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

		$css = self::rule( ':root:root', $root ) . self::rule( ':root.dark', $dark );

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
