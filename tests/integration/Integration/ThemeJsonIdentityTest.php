<?php
/**
 * ADR-010: the identity reaches WordPress's own blocks through theme.json.
 *
 * These assert what WordPress ACTUALLY emits, not what theme.json contains —
 * `WP_Theme_JSON` merges core's defaults underneath ours, and the whole point
 * of #26 was that an absent key silently inherits core's dark-grey button. A
 * test that read the file would have passed throughout the defect.
 *
 * `wp_get_global_stylesheet()` is cached per request and across the resolver,
 * so every test here clears both caches first; without that the first test to
 * run decides what the others see.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_Block_Editor_Context;
use WP_Theme_JSON_Resolver;
use WP_UnitTestCase;

final class ThemeJsonIdentityTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();
	}

	private function global_stylesheet(): string {
		return wp_get_global_stylesheet();
	}

	/**
	 * Both editor-asset tests resolve a hashed filename through the Vite manifest, so
	 * they need a build. Same skip AssetsProductionTest uses, for the same reason and
	 * with the same caveat: a skip is a coverage hole, not a pass. CI's php-integration
	 * job now runs `npm run build` precisely so these do not skip there — it was that
	 * missing build, not the code, that made this file red in CI and green locally.
	 *
	 * The theme.json tests above deliberately do NOT skip: they read what WordPress
	 * emits, which needs no build at all.
	 */
	private function skip_without_a_build(): void {
		if ( ! is_file( get_template_directory() . '/assets/dist/.vite/manifest.json' ) ) {
			self::markTestSkipped( 'No Vite build present — run `npm run build` to cover this path.' );
		}
	}

	/**
	 * #26: with `styles.elements.button` absent, WP_Theme_JSON contributes core's own
	 * default — background #32373c on every .wp-element-button, site-wide, in both
	 * schemes. Declaring ours REPLACES that rule rather than adding to it (measured,
	 * ADR-010), so the literal must be gone entirely, not merely overridden later.
	 */
	public function test_core_default_button_colour_is_displaced(): void {
		$css = $this->global_stylesheet();

		self::assertStringNotContainsString(
			'#32373c',
			$css,
			"WordPress core's default button background is still being emitted."
		);
	}

	public function test_button_element_follows_the_primary_token(): void {
		$css = $this->global_stylesheet();

		self::assertMatchesRegularExpression(
			'/\.wp-element-button[^{]*\{[^}]*background-color:\s*var\(\s*--primary\s*\)/',
			$css,
			'The button element does not resolve its background from --primary.'
		);
		self::assertMatchesRegularExpression(
			'/\.wp-element-button[^{]*\{[^}]*color:\s*var\(\s*--primary-foreground\s*\)/',
			$css,
			'The button element does not resolve its text colour from --primary-foreground.'
		);
	}

	/**
	 * #25: presets were literal light-scheme values, so they followed neither the
	 * Customizer palette nor .dark. As var() references there is only one value in
	 * existence, which is what makes the desync impossible rather than merely unlikely.
	 */
	public function test_colour_presets_are_token_references_not_frozen_literals(): void {
		$css = $this->global_stylesheet();

		foreach ( [ 'primary', 'background', 'foreground', 'destructive' ] as $slug ) {
			self::assertMatchesRegularExpression(
				'/--wp--preset--color--' . $slug . ':\s*var\(\s*--' . $slug . '\s*\)/',
				$css,
				"Preset --wp--preset--color--{$slug} is not a reference to its live token."
			);
		}
	}

	/**
	 * #25 asked whether core's own 12 colours should keep sitting beside our 15.
	 *
	 * `defaultPalette: false` answers it, but NOT in the way the name suggests, and the
	 * distinction is the whole point of this test. Read from core
	 * (`class-wp-theme-json.php`, the `prevent_override` entry for
	 * `array( 'color', 'defaultPalette' )`): the flag governs whether theme presets may
	 * OVERRIDE the defaults, and whether the picker offers them. It does **not** stop the
	 * default presets being emitted as CSS custom properties — measured, all 27
	 * `--wp--preset--color--*` are still in the stylesheet with the flag off, and no
	 * theme.json setting removes them.
	 *
	 * So this asserts the thing WordPress actually offers: the editor's colour picker
	 * contains our palette and none of core's.
	 */
	public function test_the_editor_offers_our_palette_and_not_cores(): void {
		self::assertFalse(
			wp_get_global_settings( [ 'color', 'defaultPalette' ] ),
			'defaultPalette is not disabled, so the picker will also offer core\'s 12 colours.'
		);

		// get_block_editor_settings() copies $wp_styles/$wp_scripts->registered into a
		// throwaway pair (block-editor.php:316-317) and does not create the globals it
		// reads. Under WP_UnitTestCase they may be unset — see
		// docs/gotchas/wp-unittestcase-does-not-reset-wp-styles.md — and core then fatals
		// on null. Instantiating them is priming the harness, not faking the assertion.
		wp_styles();
		wp_scripts();

		$settings = get_block_editor_settings(
			[],
			new WP_Block_Editor_Context( [ 'name' => 'core/edit-post' ] )
		);
		$slugs    = array_column( $settings['colors'] ?? [], 'slug' );

		self::assertContains( 'primary', $slugs );
		self::assertContains( 'sale', $slugs );

		foreach ( [ 'vivid-red', 'pale-pink', 'luminous-vivid-orange', 'cyan-bluish-gray' ] as $core_slug ) {
			self::assertNotContains(
				$core_slug,
				$slugs,
				"The editor still offers core's '{$core_slug}'."
			);
		}
	}

	/**
	 * The var() references above resolve to nothing inside the block editor unless the
	 * theme's own stylesheet reaches the editor canvas. Measured (ADR-010): without
	 * this, --primary is empty inside the iframe; with it, it resolves.
	 */
	public function test_the_editor_is_given_the_stylesheet_that_defines_those_tokens(): void {
		$this->skip_without_a_build();

		self::assertTrue(
			current_theme_supports( 'editor-styles' ),
			'editor-styles support is not registered, so add_editor_style() is inert.'
		);

		$stylesheets = get_editor_stylesheets();
		self::assertNotEmpty( $stylesheets, 'No editor stylesheet is registered.' );

		$matched = array_filter(
			$stylesheets,
			static fn( string $url ): bool => str_contains( $url, '/assets/dist/' )
		);
		self::assertNotEmpty(
			$matched,
			'The editor stylesheet is not the built bundle: ' . implode( ', ', $stylesheets )
		);
	}

	/**
	 * The canvas iframe and the wp-admin sidebar are different documents, and only the
	 * first is reached by add_editor_style(). Gutenberg paints each palette swatch from
	 * the raw preset value — now `var(--primary)` — so without tokens in the ADMIN
	 * document every swatch computed to rgba(0, 0, 0, 0). Measured in a browser; this
	 * pins the wiring that fixes it.
	 *
	 * Asserted as "tokens only, never the full bundle": enqueuing app.css here would
	 * restyle wp-admin itself.
	 */
	public function test_the_admin_document_gets_the_tokens_the_colour_picker_needs(): void {
		$this->skip_without_a_build();

		do_action( 'enqueue_block_editor_assets' );

		self::assertTrue(
			wp_style_is( 'woodev-base-editor-tokens', 'enqueued' ),
			'The editor token stylesheet is not enqueued, so palette swatches render transparent.'
		);

		$src = wp_styles()->registered['woodev-base-editor-tokens']->src ?? '';
		self::assertStringContainsString( 'editorTokens', $src, "Enqueued the wrong file: {$src}" );
	}
}
