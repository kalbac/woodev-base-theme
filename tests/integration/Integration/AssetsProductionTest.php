<?php
/**
 * Production-mode asset integration tests.
 *
 * The mirror image of Integration/DevMode/AssetsDevModeTest.php. Neither file
 * means much alone: each would also pass if both PHPUnit configs booted the same
 * mode. Together they prove the two configs really do exercise the two branches
 * of Assets::enqueue().
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_UnitTestCase;
use Woodev\Theme\Base\Tests\Integration\Support\AssetMarkup;
use Woodev\Theme\Base\Tests\Integration\Support\ScriptModuleGuard;

final class AssetsProductionTest extends WP_UnitTestCase {

	/**
	 * Guards the harness itself: this suite must NOT define WOODEV_BASE_DEV,
	 * otherwise the assertions below would pass vacuously against a dev-mode
	 * WordPress instead of exercising the production branch.
	 */
	public function test_the_harness_is_not_in_dev_mode(): void {
		self::assertFalse(
			\defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV,
			'This suite must run WITHOUT the dev constant — is it collecting Integration/DevMode?'
		);
	}

	/**
	 * Guards the harness itself, the other half: every assertion below is
	 * meaningless if the bootstrap ever stopped activating our theme — the
	 * built-assets test below would then find no manifest under a foreign
	 * theme's directory and markTestSkipped() away, staying green while
	 * proving nothing. This assertion sits outside that skip path on
	 * purpose, so a broken harness fails loudly instead of skipping quietly.
	 */
	public function test_the_harness_has_our_theme_active(): void {
		self::assertSame( 'woodev-base-theme', get_stylesheet() );
		self::assertSame( 'woodev-base-theme', get_template() );
	}

	/**
	 * Render a full front-end request's head and footer, concatenated, and
	 * memoize the result for the lifetime of the process.
	 *
	 * See AssetsDevModeTest::render_front_end_assets() for why both halves are
	 * required (wp_enqueue_scripts fires from wp_head; script modules print in
	 * wp_footer for classic themes) and why the render is memoized in a
	 * function-static (WP_Script_Modules::$done silently skips already-printed
	 * module IDs within the same process).
	 *
	 * The production PHPUnit config collects the whole Integration/
	 * directory, not just this file, so — unlike the dev-mode suite, which
	 * only ever collects this one class — a second test class in that
	 * directory could in principle render wp_head/wp_footer first and land
	 * our script module handle in WP_Script_Modules::$done before we get
	 * here. ScriptModuleGuard turns that into a loud failure instead of a
	 * silently short markup capture. See ScriptModuleGuard's docblock.
	 */
	private static function render_front_end_assets(): string {
		static $html = null;

		if ( null === $html ) {
			ScriptModuleGuard::assert_none_already_done( [ 'woodev-base-app' ] );

			ob_start();
			do_action( 'wp_head' );
			do_action( 'wp_footer' );
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	public function test_no_dev_server_url_is_referenced(): void {
		self::assertStringNotContainsString( 'localhost:5173', self::render_front_end_assets() );
	}

	/**
	 * Two independent assertions rather than one broad
	 * `assertStringContainsString( 'assets/dist', ... )`: that single check
	 * proved only that SOME built asset was printed, so deleting the JS
	 * module enqueue in Assets::enqueue() stayed green because the stylesheet
	 * still matched, and vice versa.
	 *
	 * Each requires the element **id** and the **exact** URL. The id pins the
	 * element to one handle rather than to "something pointing into
	 * assets/dist" — Assets::enqueue() also enqueues the JS entry's imported
	 * CSS in a loop, so deleting the main stylesheet leaves other assets/dist
	 * links standing. WordPress derives the id from the handle
	 * (`woodev-base-style` → `woodev-base-style-css`, `woodev-base-app` →
	 * `woodev-base-app-js-module`), read off a real response.
	 *
	 * The exact URL is affordable here even though built files carry a content
	 * hash, because the manifest is what the resolver is being tested against:
	 * this decodes it independently (plain json_decode, not Assets' own
	 * reader) and computes the URL the theme should have produced. That makes
	 * the assertion prove the manifest lookup resolved the RIGHT file, not
	 * merely that some file from the build directory was printed.
	 */
	public function test_built_assets_are_enqueued_from_the_manifest(): void {
		$manifest_path = get_template_directory() . '/assets/dist/.vite/manifest.json';

		if ( ! is_file( $manifest_path ) ) {
			self::markTestSkipped( 'No Vite build present — run `npm run build` to cover this path.' );
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		self::assertIsArray( $manifest, 'The Vite manifest is present but did not decode to an array.' );

		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$html     = self::render_front_end_assets();

		// One bundle (ADR-008): no per-pack theme_mod resolution, so the manifest
		// key is a fixed string, not something this suite has to keep in sync
		// with a stored setting.
		AssetMarkup::assert_stylesheet_link(
			$html,
			'woodev-base-style-css',
			$dist_uri . '/' . $manifest['src/css/app.css']['file'],
			'Expected wp_enqueue_style( \'woodev-base-style\', … ) to print the exact file the manifest names for the CSS entry.'
		);

		AssetMarkup::assert_script_module(
			$html,
			'woodev-base-app-js-module',
			$dist_uri . '/' . $manifest['src/js/app.js']['file'],
			'Expected wp_enqueue_script_module( \'woodev-base-app\', … ) to print the exact file the manifest names for the JS entry.'
		);
	}

	/**
	 * Assets::preload_display_font() (T3): core's own wp_preload_resources()
	 * (hooked to wp_head, WP 6.1+) must have turned our filtered entry into a
	 * real `<link rel="preload">` element — this is the only level that
	 * proves core actually consumed the array shape our unit tests pin,
	 * rather than merely proving our own filter callback returns the right
	 * PHP array.
	 *
	 * The expected subset is computed the same way the production code
	 * computes it (Russian locale → cyrillic, else latin) rather than
	 * hardcoded, so this assertion is correct under whatever locale the
	 * integration harness actually runs with instead of assuming en_US.
	 */
	public function test_the_display_font_preload_link_is_printed_with_crossorigin(): void {
		$subset        = \str_starts_with( determine_locale(), 'ru' ) ? 'cyrillic' : 'latin';
		$expected_href = get_template_directory_uri() . '/assets/fonts/golos-text-500-800-' . $subset . '.woff2';

		self::assert_font_preload_link( self::render_front_end_assets(), $expected_href );
	}

	/**
	 * Re-critic finding (B1, 25.07.2026): integration-level proof that the
	 * `system` font Customizer setting actually suppresses the preload link
	 * a real WordPress request prints — not just that
	 * Assets::preload_display_font() returns the right bare PHP array in
	 * isolation, which the unit suite (AssetsPreloadTest) already covers.
	 *
	 * Runs in its own process because render_front_end_assets() memoizes its
	 * markup in a function-static for the lifetime of the PHPUnit process
	 * (see that method's docblock): every OTHER test in this file relies on
	 * that shared cache reflecting the harness's DEFAULT font setting (no
	 * theme_mod stored -> Settings::FONT_IDENTITY). Setting the `font`
	 * theme_mod to `system` and rendering in the SAME process would poison
	 * that cache — whichever test ran next would see this test's markup
	 * instead of its own render. Isolating this test avoids that entirely.
	 *
	 * DOC-COMMENT annotations, not PHP 8 attributes: this suite runs on
	 * PHPUnit ^9.6 (see tests/integration/composer.json and the note atop
	 * phpunit.xml.dist — the WordPress core test suite is PHPUnit-9-only),
	 * and `PHPUnit\Framework\Attributes\RunInSeparateProcess` does not exist
	 * before PHPUnit 10. Using the attribute here silently no-ops instead of
	 * erroring (this was caught by the mutation check: it let the test see
	 * an earlier test's memoized, non-system-mode markup and go green for
	 * the wrong reason) — the classic annotations below are what PHPUnit
	 * 9.6 actually understands.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_system_font_mode_prints_no_display_font_preload_link(): void {
		set_theme_mod( 'font', 'system' );

		self::assertStringNotContainsString(
			'golos-text-500-800',
			self::render_front_end_assets(),
			'A preload link for the display font was printed even though the "font" Customizer setting is "system" — Assets::preload_display_font() should have skipped it entirely.'
		);
	}

	/**
	 * Parses the captured markup with DOMDocument (see AssetMarkup's docblock
	 * for why: regex-parsing HTML attributes has already cost this codebase
	 * three review rounds) and asserts a `rel="preload"` link with this exact
	 * `href` carries `as="font"` and `crossorigin="anonymous"`.
	 *
	 * Not folded into AssetMarkup itself: that class is shared with other
	 * suites this task does not own, and duplicating the small DOMDocument
	 * fragment-parsing idiom here is cheaper than widening a shared file's
	 * surface for one caller.
	 *
	 * @param string $html Captured wp_head/wp_footer markup.
	 * @param string $href The exact preload `href` that must be present.
	 */
	private static function assert_font_preload_link( string $html, string $href ): void {
		if ( '' === $html ) {
			throw new \RuntimeException(
				'AssetsProductionTest: the captured wp_head/wp_footer markup is empty. Nothing was rendered at all.'
			);
		}

		$dom      = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );

		try {
			$loaded = $dom->loadHTML( '<body>' . $html . '</body>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD );
		} finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}

		self::assertTrue( $loaded, 'AssetsProductionTest: DOMDocument::loadHTML() reported failure on the captured wp_head/wp_footer markup.' );

		$found = false;

		foreach ( $dom->getElementsByTagName( 'link' ) as $link ) {
			if ( ! $link instanceof \DOMElement ) {
				continue;
			}

			if ( 'preload' !== \strtolower( $link->getAttribute( 'rel' ) ) || $href !== $link->getAttribute( 'href' ) ) {
				continue;
			}

			self::assertSame( 'font', $link->getAttribute( 'as' ), 'The preload link is missing as="font".' );
			self::assertSame( 'anonymous', $link->getAttribute( 'crossorigin' ), 'The preload link is missing crossorigin="anonymous".' );
			$found = true;
			break;
		}

		self::assertTrue( $found, "Expected a rel=\"preload\" link with href \"{$href}\" in the rendered markup, none found." );
	}
}
