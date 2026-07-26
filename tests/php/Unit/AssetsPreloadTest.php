<?php
/**
 * Assets::preload_display_font() — the wp_preload_resources filter (T3).
 *
 * @package Woodev\Theme\Base\Tests\Unit
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Woodev\Theme\Base\Assets;
use Woodev\Theme\Base\Customizer\Settings;

final class AssetsPreloadTest extends TestCase {

	private const THEME_URI = 'https://example.test/wp-content/themes/woodev-base-theme';

	/**
	 * `get_theme_mod( 'font', ... )` is stubbed to always return its own
	 * $default argument — i.e. "nothing stored", which Settings::font()
	 * resolves to FONT_IDENTITY (B1: Assets::preload_display_font() now
	 * reads the font mode through Settings::font(), the same resolver the
	 * front end uses, so every test that does not care about the font
	 * setting needs this stubbed or Settings::font() has nothing to call).
	 */
	private function stub_locale( string $locale ): void {
		Functions\when( 'determine_locale' )->justReturn( $locale );
		Functions\when( 'get_template_directory_uri' )->justReturn( self::THEME_URI );
		Functions\when( 'get_theme_mod' )->alias( static fn( string $key, $default = false ) => $default );
	}

	public function test_it_hooks_wp_preload_resources(): void {
		// Named rather than counted. This used to expect add_action() "once", which
		// pinned the NUMBER of actions instead of which ones — so adding the editor
		// stylesheet hook (ADR-010) failed a test that was never about counting.
		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_enqueue_scripts', \Mockery::type( 'array' ) );
		Functions\expect( 'add_action' )
			->once()
			->with( 'after_setup_theme', \Mockery::type( 'array' ) );
		Functions\expect( 'add_filter' )
			->once()
			->with( 'wp_preload_resources', \Mockery::type( 'array' ) );

		( new Assets() )->register();
	}

	public function test_a_russian_locale_gets_the_cyrillic_entry(): void {
		$this->stub_locale( 'ru_RU' );

		self::assertSame(
			[
				[
					'href'        => self::THEME_URI . '/assets/fonts/golos-text-500-800-cyrillic.woff2',
					'as'          => 'font',
					'type'        => 'font/woff2',
					'crossorigin' => 'anonymous',
				],
			],
			( new Assets() )->preload_display_font( [] )
		);
	}

	public function test_a_non_russian_locale_gets_the_latin_entry(): void {
		$this->stub_locale( 'en_US' );

		self::assertSame(
			[
				[
					'href'        => self::THEME_URI . '/assets/fonts/golos-text-500-800-latin.woff2',
					'as'          => 'font',
					'type'        => 'font/woff2',
					'crossorigin' => 'anonymous',
				],
			],
			( new Assets() )->preload_display_font( [] )
		);
	}

	/**
	 * A pre-existing entry (from another `wp_preload_resources` filter, e.g.
	 * core's own or another plugin's) must survive — the filter merges, it
	 * never replaces.
	 */
	public function test_a_pre_existing_entry_is_preserved(): void {
		$this->stub_locale( 'en_US' );

		$other = [
			'href' => 'https://example.test/some-other-resource.js',
			'as'   => 'script',
		];

		$result = ( new Assets() )->preload_display_font( [ $other ] );

		self::assertCount( 2, $result );
		self::assertSame( $other, $result[0] );
		self::assertSame(
			self::THEME_URI . '/assets/fonts/golos-text-500-800-latin.woff2',
			$result[1]['href']
		);
	}

	/**
	 * Re-critic finding (B1, 25.07.2026): the preload used to ignore the
	 * `font` Customizer setting entirely and force-download Golos Text on
	 * every page, even for an admin who picked `system` (no webfont at
	 * all). This must read the SAME resolver the front end uses
	 * (Settings::font()) rather than a raw theme_mod, so it never drifts
	 * from what the front end actually renders.
	 *
	 * Asserts the filter is a true no-op in system mode, not just "no font
	 * href present": neither determine_locale() nor
	 * get_template_directory_uri() may even be called, and any pre-existing
	 * entry from another `wp_preload_resources` filter must survive
	 * untouched — mirrors the dev-mode test below.
	 */
	public function test_the_system_font_mode_skips_the_preload_entirely(): void {
		Functions\when( 'get_theme_mod' )->justReturn( Settings::FONT_SYSTEM );

		Functions\expect( 'determine_locale' )->never();
		Functions\expect( 'get_template_directory_uri' )->never();

		$incoming = [
			[
				'href' => 'https://example.test/keep-me.js',
				'as'   => 'script',
			],
		];

		self::assertSame( $incoming, ( new Assets() )->preload_display_font( $incoming ) );
	}

	/**
	 * The identity mode (the default) must still preload — guards against a
	 * fix for B1 that accidentally skips the preload for every font value,
	 * not just `system`.
	 */
	public function test_the_identity_font_mode_still_preloads(): void {
		$this->stub_locale( 'en_US' );
		Functions\when( 'get_theme_mod' )->justReturn( Settings::FONT_IDENTITY );

		$result = ( new Assets() )->preload_display_font( [] );

		self::assertCount( 1, $result );
		self::assertSame(
			self::THEME_URI . '/assets/fonts/golos-text-500-800-latin.woff2',
			$result[0]['href']
		);
	}

	/**
	 * ADR-007 / dev-mode-css-injection-breaks-relative-urls.md: in dev mode
	 * Vite injects the CSS from a JS module, so the relative url()s in
	 * fonts.css already 404. Preloading in dev would just add a second
	 * failing request for the same file, so the filter must return the
	 * incoming array completely unchanged — not even locale/URI functions
	 * get called.
	 *
	 * WOODEV_BASE_DEV can never become undefined once set, so this branch is
	 * only safely testable in a process of its own.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_dev_mode_returns_the_incoming_array_unchanged(): void {
		\define( 'WOODEV_BASE_DEV', true );

		Functions\expect( 'determine_locale' )->never();
		Functions\expect( 'get_template_directory_uri' )->never();

		$incoming = [
			[
				'href' => 'https://example.test/keep-me.js',
				'as'   => 'script',
			],
		];

		self::assertSame( $incoming, ( new Assets() )->preload_display_font( $incoming ) );
	}

	/**
	 * Pins the FILENAME against reality, not just against the production
	 * code's own string-building. If a rename lands in the font build
	 * without a matching update here, the preload would point at a 404 on a
	 * live site — this test must go red first. The href is produced by the
	 * real method under test (only the theme URI is stubbed, to a value
	 * distinct from a real filesystem path), then its basename is checked
	 * against the actual committed fonts directory.
	 *
	 * Covers BOTH subsets the preload can emit (re-critic finding, B2,
	 * 25.07.2026): the old version only ever stubbed 'en_US' and so only
	 * ever checked the latin file, which stayed green even if a font build
	 * renamed or dropped only the cyrillic file. `en_US` -> latin and
	 * `ru_RU` -> cyrillic are checked in the same test, both against the
	 * real production locale->subset mapping, not a re-derivation of it.
	 *
	 * @return iterable<string, array{string, string}>
	 */
	public static function locale_and_subset_provider(): iterable {
		yield 'a non-Russian locale gets the latin file' => [ 'en_US', 'latin' ];
		yield 'a Russian locale gets the cyrillic file' => [ 'ru_RU', 'cyrillic' ];
	}

	/**
	 * @dataProvider locale_and_subset_provider
	 */
	public function test_the_preloaded_filename_exists_on_disk( string $locale, string $expected_subset ): void {
		$this->stub_locale( $locale );

		$entries = ( new Assets() )->preload_display_font( [] );
		$href    = $entries[0]['href'];

		self::assertStringStartsWith( self::THEME_URI . '/assets/fonts/', $href );
		$filename = \substr( $href, \strlen( self::THEME_URI . '/assets/fonts/' ) );

		self::assertSame( "golos-text-500-800-{$expected_subset}.woff2", $filename );

		$fonts_dir = \dirname( __DIR__, 3 ) . '/woodev-base-theme/assets/fonts';

		self::assertFileExists(
			$fonts_dir . '/' . $filename,
			"Assets::preload_display_font() points at \"{$filename}\", which does not exist under {$fonts_dir} — the font build renamed or dropped it."
		);
	}
}
