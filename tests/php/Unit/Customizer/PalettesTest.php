<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Customizer;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Customizer\Palettes;
use Woodev\Theme\Base\Tests\Unit\TestCase;

/**
 * Palettes::$cache is memoised PER RESOLVED PATH (see its docblock), so
 * pointing get_template_directory() at a distinct fixture directory per test
 * — rather than resetting the cache — is what keeps these tests from
 * observing each other's stale result. Same technique IconsTest uses for
 * Icons::$cache.
 */
final class PalettesTest extends TestCase {

	private function stub_theme_root( string $fixture ): void {
		Functions\when( 'get_template_directory' )
			->justReturn( \dirname( __DIR__, 2 ) . '/fixtures/' . $fixture );
	}

	/**
	 * The real, shipped generator output — proves the happy path end to end,
	 * not just the degrade paths below.
	 */
	public function test_the_real_generated_file_yields_all_seven_shipped_palettes(): void {
		Functions\when( 'get_template_directory' )
			->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );

		self::assertSame(
			[ 'warm-clay', 'cold-petrol', 'graphite', 'forest', 'sand', 'wine', 'night-indigo' ],
			Palettes::slugs()
		);
		self::assertSame(
			[
				'n-h'      => '68',
				'accent-h' => '40',
				'accent-c' => '0.088',
			],
			Palettes::get( 'warm-clay' )
		);
	}

	/**
	 * A tampered generated map yields only its sound entries, plus the
	 * synthesised warm-clay default the fixture deliberately omits — the
	 * injection attempt, the CSS-function value, and the half-written entry
	 * must never reach the returned map at all, not merely be "sanitized".
	 */
	public function test_a_tampered_generated_map_yields_only_its_sound_entries_plus_the_default(): void {
		$this->stub_theme_root( 'malformed-theme' );

		self::assertSame( [ 'sound', 'warm-clay' ], Palettes::slugs() );
		self::assertSame(
			[
				'n-h'      => '264',
				'accent-h' => '214',
				'accent-c' => '0.105',
			],
			Palettes::get( 'sound' )
		);
		self::assertSame(
			[
				'n-h'      => '68',
				'accent-h' => '40',
				'accent-c' => '0.088',
			],
			Palettes::get( 'warm-clay' )
		);
	}

	public function test_the_injected_entry_is_rejected_outright(): void {
		$this->stub_theme_root( 'malformed-theme' );

		self::assertArrayNotHasKey( 'injected', Palettes::all() );
	}

	/**
	 * A file that parses but returns something other than an array (a
	 * different failure shape than per-entry tampering) still degrades to
	 * just the synthesised default, not a fatal TypeError from array_keys()
	 * or a foreach over a string.
	 */
	public function test_a_file_that_returns_a_non_array_degrades_to_the_default_only(): void {
		$this->stub_theme_root( 'non-array-theme' );

		self::assertSame( [ 'warm-clay' ], Palettes::slugs() );
	}

	/**
	 * A genuine PHP syntax error (ParseError, thrown when the `include`
	 * statement itself runs) must be caught, not merely a well-formed file
	 * with wrong content.
	 */
	public function test_a_syntax_error_in_the_generated_file_does_not_fatal(): void {
		$this->stub_theme_root( 'broken-syntax-theme' );

		self::assertSame( [ 'warm-clay' ], Palettes::slugs() );
		self::assertSame(
			[
				'n-h'      => '68',
				'accent-h' => '40',
				'accent-c' => '0.088',
			],
			Palettes::get( 'warm-clay' )
		);
	}

	/**
	 * No generated file at all (a fresh checkout before `npm run tokens` has
	 * ever run, or a theme root that simply does not have one) is the same
	 * degrade path as a malformed one — is_file() false, not a warning-laden
	 * include attempt.
	 */
	public function test_a_missing_generated_file_degrades_to_the_default_only(): void {
		$this->stub_theme_root( 'this-fixture-directory-does-not-exist' );

		self::assertSame( [ 'warm-clay' ], Palettes::slugs() );
	}

	/**
	 * Get() for a slug that is not in the map at all (not merely rejected —
	 * never mentioned) falls back to the same warm-clay values rather than
	 * an undefined-index warning or null.
	 */
	public function test_get_of_an_unknown_slug_falls_back_to_warm_clay(): void {
		$this->stub_theme_root( 'malformed-theme' );

		self::assertSame( Palettes::get( 'warm-clay' ), Palettes::get( 'totally-unknown-slug' ) );
	}
}
