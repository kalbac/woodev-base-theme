<?php
/**
 * Asset loading via the Vite manifest.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

use Woodev\Theme\Base\Customizer\Settings;

/**
 * Enqueues theme assets resolved through the Vite build manifest.
 */
final class Assets {

	private const DEV_SERVER          = 'http://localhost:5173';
	private const JS_ENTRY            = 'src/js/app.js';
	private const CSS_ENTRY           = 'src/css/app.css';
	private const EDITOR_TOKENS_ENTRY = 'src/css/editor-tokens.css';

	/**
	 * Hook asset enqueuing into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'wp_preload_resources', [ $this, 'preload_display_font' ] );
		add_action( 'after_setup_theme', [ $this, 'register_editor_style' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_tokens' ] );
	}

	/**
	 * Enqueue the built (or dev-server) theme assets.
	 */
	public function enqueue(): void {
		if ( \defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV ) {
			$this->enqueue_dev();
			return;
		}

		$dist     = get_template_directory() . '/assets/dist';
		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$manifest = self::read_manifest( $dist . '/.vite/manifest.json' );

		$css = self::entry_file( $manifest, self::CSS_ENTRY );
		if ( null !== $css ) {
			wp_enqueue_style( 'woodev-base-style', "{$dist_uri}/{$css}", [], null );
		}

		$js = self::entry_file( $manifest, self::JS_ENTRY );
		if ( null !== $js ) {
			wp_enqueue_script_module( 'woodev-base-app', "{$dist_uri}/{$js}", [], null );
		}

		foreach ( self::entry_css( $manifest, self::JS_ENTRY ) as $index => $imported ) {
			wp_enqueue_style( "woodev-base-app-{$index}", "{$dist_uri}/{$imported}", [], null );
		}
	}

	/**
	 * Give the block editor the stylesheet that defines the theme's tokens.
	 *
	 * ADR-010 makes every theme.json colour a `var()` reference to a live token, and
	 * the editor consumes those values in TWO different documents:
	 *
	 * - the CANVAS, an iframe that loads only core's own stylesheets. Measured:
	 *   `--primary` reads empty inside it, and resolves once add_editor_style() puts
	 *   the built bundle there. That is this method.
	 * - the SIDEBAR colour picker, which lives in the wp-admin document and is handled
	 *   by enqueue_editor_tokens() below — a different hook, a different file, and the
	 *   full bundle must never go there.
	 *
	 * The filename is hashed by Vite, so it is resolved through the manifest, never
	 * written out. If the manifest or the entry is missing the editor simply keeps
	 * core's styling, which is the same failure mode the front end already has.
	 */
	public function register_editor_style(): void {
		if ( \defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV ) {
			return;
		}

		$css = $this->dist_entry( self::CSS_ENTRY );

		if ( null === $css ) {
			return;
		}

		add_theme_support( 'editor-styles' );
		add_editor_style( "assets/dist/{$css}" );
	}

	/**
	 * Give the wp-admin document the token declarations the colour picker needs.
	 *
	 * Gutenberg paints each palette swatch from the RAW preset value, which is now
	 * `var(--primary)`. The admin document carries none of the theme's CSS, so every
	 * swatch computed to `rgba(0, 0, 0, 0)` — measured in a browser, not inferred.
	 *
	 * This enqueues the tokens-only entry, never the full bundle: that one carries
	 * Basecoat's base layer and the site chrome and would restyle wp-admin itself.
	 */
	public function enqueue_editor_tokens(): void {
		if ( \defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV ) {
			return;
		}

		$css = $this->dist_entry( self::EDITOR_TOKENS_ENTRY );

		if ( null === $css ) {
			return;
		}

		wp_enqueue_style(
			'woodev-base-editor-tokens',
			get_template_directory_uri() . "/assets/dist/{$css}",
			[],
			null
		);
	}

	/**
	 * Resolve one manifest entry to its built file name, or null if unavailable.
	 *
	 * @param string $entry Manifest entry key, e.g. `src/css/app.css`.
	 */
	private function dist_entry( string $entry ): ?string {
		$manifest = self::read_manifest( get_template_directory() . '/assets/dist/.vite/manifest.json' );

		return self::entry_file( $manifest, $entry );
	}

	/**
	 * Preload the above-the-fold display face (Golos Text) via WP core's own
	 * `wp_preload_resources` filter (WP 6.1+; the theme requires 6.8) so the
	 * browser starts fetching it immediately instead of discovering the URL
	 * only after fetching and parsing the stylesheet — the late discovery
	 * that causes the swap-in reflow `font-display: swap` does not prevent.
	 * docs/plans/2026-07-25-visual-identity.md T3: preload exactly this one
	 * file, nothing else — not the body/mono faces, not the stylesheet, not
	 * the script module.
	 *
	 * Skipped entirely in dev mode: the CSS is injected by a Vite JS module
	 * there, so the relative `url()`s in fonts.css already 404
	 * (docs/gotchas/dev-mode-css-injection-breaks-relative-urls.md) and a
	 * preload would only add a second failing request for the same file.
	 *
	 * Skipped when the admin picked the `system` font mode (re-critic
	 * finding, 25.07.2026 — B1): `Settings::font()` is the same resolver the
	 * front end reads through, so this never duplicates its sanitising or
	 * reads the raw `theme_mod` directly. Golos Text is never referenced by
	 * the compiled CSS when `font` is `system` — src/css/fonts.css itself is
	 * not even enqueued in that mode's stylesheet chain — so preloading it
	 * would force a webfont download for a face nothing on the page uses.
	 *
	 * Locale → subset: a Russian locale needs the cyrillic subset above the
	 * fold; every other locale gets latin. `str_starts_with( …, 'ru' )` on
	 * `determine_locale()` is deliberately the whole rule — the theme ships
	 * English source + ru_RU only (ADR-006). This is a HEURISTIC, not a
	 * guarantee, and deliberately so in two distinct ways (re-critic
	 * finding, 25.07.2026): it is not exhaustive across locales (another
	 * Cyrillic-script locale, e.g. uk, bg_BG, still gets the latin preload
	 * here and loads its own subset from fonts.css a moment later — a missed
	 * optimisation, not a bug); and even on a matching `ru_RU` request it can
	 * preload a subset nothing above the fold actually needs — a `ru_RU`
	 * page whose above-the-fold text happens to be pure Latin (a brand name,
	 * a model number) wastes the cyrillic preload. That second case is
	 * accepted, not fixed: this hook runs before WordPress has rendered
	 * anything, so there is no way in PHP to know the fold's real glyph
	 * coverage, and a Russian site's chrome (nav, headings, footer) is
	 * overwhelmingly Cyrillic — the heuristic is right far more often than
	 * it is wrong, and the plan mandates exactly one preloaded file.
	 *
	 * `crossorigin` is not optional even though the font is same-origin:
	 * fonts are always fetched in CORS mode, and a preload link without a
	 * matching `crossorigin` attribute is treated as a different cache entry
	 * — the browser discards the preload and fetches the font a second time.
	 *
	 * Existing entries (from other filters) are preserved, never replaced.
	 *
	 * @param array<int, array<string, string>> $preload_resources Preload entries already collected from other filters.
	 * @return array<int, array<string, string>>
	 */
	public function preload_display_font( array $preload_resources ): array {
		if ( \defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV ) {
			return $preload_resources;
		}

		if ( Settings::FONT_SYSTEM === Settings::font() ) {
			return $preload_resources;
		}

		$subset = \str_starts_with( determine_locale(), 'ru' ) ? 'cyrillic' : 'latin';
		$file   = "golos-text-500-800-{$subset}.woff2";

		$preload_resources[] = [
			'href'        => get_template_directory_uri() . '/assets/fonts/' . $file,
			'as'          => 'font',
			'type'        => 'font/woff2',
			'crossorigin' => 'anonymous',
		];

		return $preload_resources;
	}

	/**
	 * Enqueue assets straight from the Vite dev server (HMR, no manifest).
	 *
	 * The CSS entry is a separate Rollup input from the JS entry (ADR-008: one
	 * bundle, no per-pack resolution), so app.js never imports it and the dev
	 * server must be asked for it explicitly — otherwise the page renders with
	 * no Tailwind, Basecoat or tokens. Vite serves it as a JS module that
	 * injects the style and carries HMR, hence a script module, not a
	 * stylesheet.
	 */
	private function enqueue_dev(): void {
		wp_enqueue_script_module( 'woodev-base-vite-client', self::DEV_SERVER . '/@vite/client', [], null );
		wp_enqueue_script_module( 'woodev-base-style', self::DEV_SERVER . '/' . self::CSS_ENTRY, [], null );
		wp_enqueue_script_module( 'woodev-base-app', self::DEV_SERVER . '/' . self::JS_ENTRY, [], null );
	}

	/**
	 * Read and decode a Vite manifest; empty array when absent/invalid.
	 *
	 * An absent manifest is the normal state of a fresh checkout (assets/dist is
	 * gitignored) and means "enqueue nothing" — never a fatal, never a PHP
	 * diagnostic. The guard is what keeps it silent, and it needs both halves:
	 * wp_json_file_decode() emits wp_trigger_error() for a path realpath() cannot
	 * resolve, then hands whatever survives to file_get_contents() with no
	 * readability check of its own, so an existing-but-unreadable file warns too.
	 *
	 * A file replaced or removed between this check and the decode still warns.
	 * That race is not worth closing here: the manifest is our own build artifact
	 * under the theme directory, not attacker-controlled input.
	 *
	 * @param string $path Absolute path to the manifest.json file.
	 * @return array<string, array{file: string, css?: list<string>}>
	 */
	public static function read_manifest( string $path ): array {
		if ( ! \is_file( $path ) || ! \is_readable( $path ) ) {
			return [];
		}

		$decoded = wp_json_file_decode( $path, [ 'associative' => true ] );

		return \is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Resolve the built file name for a manifest entry.
	 *
	 * @param array<string, array{file: string, css?: list<string>}> $manifest Decoded manifest.
	 * @param string                                                 $entry    Manifest entry key.
	 */
	public static function entry_file( array $manifest, string $entry ): ?string {
		return $manifest[ $entry ]['file'] ?? null;
	}

	/**
	 * Resolve the imported CSS file names for a manifest entry.
	 *
	 * @param array<string, array{file: string, css?: list<string>}> $manifest Decoded manifest.
	 * @param string                                                 $entry    Manifest entry key.
	 * @return list<string>
	 */
	public static function entry_css( array $manifest, string $entry ): array {
		return $manifest[ $entry ]['css'] ?? [];
	}
}
