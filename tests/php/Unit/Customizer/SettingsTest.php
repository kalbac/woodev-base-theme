<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Customizer;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Customizer\Settings;
use Woodev\Theme\Base\Tests\Unit\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// palette's sanitizer reads Palettes::slugs(), which reads a
		// generated file through get_template_directory() — point it at the
		// real shipped file so the closed set is the actual seven palettes
		// unless a test below overrides this for the corrupted-file path.
		Functions\when( 'get_template_directory' )
			->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );
	}

	private function stub_theme_mod( mixed $value ): void {
		Functions\when( 'get_theme_mod' )->justReturn( $value );
	}

	public function test_container_width_defaults_and_clamps(): void {
		self::assertSame( 1440, Settings::sanitize_container_width( '' ) );
		self::assertSame( 1200, Settings::sanitize_container_width( '1200' ) );
		self::assertSame( 960, Settings::sanitize_container_width( 100 ) );
		self::assertSame( 1920, Settings::sanitize_container_width( 99999 ) );
		self::assertSame( 1440, Settings::sanitize_container_width( [ 1200 ] ) );
		self::assertSame( 1440, Settings::sanitize_container_width( new \stdClass() ) );
	}

	public function test_base_font_size_defaults_and_clamps(): void {
		self::assertSame( 16, Settings::sanitize_base_font_size( null ) );
		self::assertSame( 18, Settings::sanitize_base_font_size( '18' ) );
		self::assertSame( 14, Settings::sanitize_base_font_size( 2 ) );
		self::assertSame( 20, Settings::sanitize_base_font_size( 400 ) );
	}

	/**
	 * Overflowing literals slip past is_numeric(): (float) '1e309' is INF, and
	 * casting INF to int yields 0 — so the LARGEST possible input would clamp
	 * to the MINIMUM. Codex P2 on the M1-04 diff; reproduced before it was
	 * fixed. Silent on PHP 8.1 (the declared floor), a warning on 8.5.
	 */
	public function test_an_overflowing_numeric_string_takes_the_documented_fallback(): void {
		self::assertSame( 1440, Settings::sanitize_container_width( '1e309' ) );
		self::assertSame( 1440, Settings::sanitize_container_width( -INF ) );
		self::assertSame( 16, Settings::sanitize_base_font_size( '1e309' ) );
		self::assertSame( 16, Settings::sanitize_base_font_size( NAN ) );
	}

	/**
	 * Re-critic finding on my own is_finite() fix: it closed INF but not a
	 * FINITE value outside the integer range. '1e100' passes both guards, and
	 * casting it is undefined behaviour that yields 0, clamping the largest
	 * possible input to the MINIMUM. Clamping before the cast is what fixes it.
	 * Verified on both PHP 8.1.34 (the floor: silent) and 8.5.1 (warns).
	 */
	public function test_a_finite_value_beyond_the_integer_range_clamps_to_the_maximum(): void {
		self::assertSame( 1920, Settings::sanitize_container_width( '1e100' ) );
		self::assertSame( 960, Settings::sanitize_container_width( '-1e100' ) );
		self::assertSame( 20, Settings::sanitize_base_font_size( 1e100 ) );
	}

	public function test_resolvers_read_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( '1100' );
		self::assertSame( 1100, Settings::container_width() );

		$this->stub_theme_mod( new \stdClass() );
		self::assertSame( 1440, Settings::container_width() );
	}

	// --- palette (T7, ADR-008) ---------------------------------------

	public function test_palette_is_a_closed_set_of_the_seven_shipped_slugs(): void {
		self::assertSame( 'cold-petrol', Settings::sanitize_palette( 'cold-petrol' ) );
		self::assertSame( 'night-indigo', Settings::sanitize_palette( 'night-indigo' ) );
		self::assertSame( 'warm-clay', Settings::sanitize_palette( 'not-a-real-palette' ) );
		self::assertSame( 'warm-clay', Settings::sanitize_palette( [] ) );
		self::assertSame( 'warm-clay', Settings::sanitize_palette( new \stdClass() ) );
		self::assertSame( 'warm-clay', Settings::sanitize_palette( null ) );
	}

	public function test_palette_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( 'forest' );
		self::assertSame( 'forest', Settings::palette() );

		$this->stub_theme_mod( 'does-not-exist' );
		self::assertSame( 'warm-clay', Settings::palette() );
	}

	/**
	 * The palette closed set is Palettes::slugs(), not a hardcoded list —
	 * when the generated file degrades to just the default (a corrupted
	 * palettes.php, see PalettesTest), a previously-valid slug must fall
	 * back too, rather than the request fataling on an array the renderer
	 * no longer has data for.
	 */
	public function test_a_stored_slug_the_corrupted_generated_file_no_longer_supports_falls_back(): void {
		Functions\when( 'get_template_directory' )
			->justReturn( \dirname( __DIR__, 2 ) . '/fixtures/non-array-theme' );

		self::assertSame( 'warm-clay', Settings::sanitize_palette( 'cold-petrol' ) );
	}

	// --- accent (T7, ADR-008) ------------------------------------------

	public function test_accent_accepts_and_normalizes_hex_colours(): void {
		self::assertSame( '#3366cc', Settings::sanitize_accent( '#3366CC' ) );
		self::assertSame( '#3366cc', Settings::sanitize_accent( '3366cc' ) );
		self::assertSame( '#aabbcc', Settings::sanitize_accent( '#abc' ) );
		self::assertSame( '#aabbcc', Settings::sanitize_accent( 'ABC' ) );
	}

	public function test_accent_defaults_to_empty_meaning_no_override(): void {
		self::assertSame( '', Settings::sanitize_accent( '' ) );
		self::assertSame( '', Settings::sanitize_accent( null ) );
		self::assertSame( '', Settings::sanitize_accent( false ) );
	}

	public function test_accent_rejects_hostile_and_malformed_input(): void {
		self::assertSame( '', Settings::sanitize_accent( 'red' ) );
		self::assertSame( '', Settings::sanitize_accent( '#12345' ) );
		self::assertSame( '', Settings::sanitize_accent( '#fff;}body{display:none}' ) );
		self::assertSame( '', Settings::sanitize_accent( [ '#fff' ] ) );
		self::assertSame( '', Settings::sanitize_accent( new \stdClass() ) );
		self::assertSame( '', Settings::sanitize_accent( 42 ) );
	}

	public function test_accent_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( '#112233' );
		self::assertSame( '#112233', Settings::accent() );

		$this->stub_theme_mod( 'not-a-colour' );
		self::assertSame( '', Settings::accent() );
	}

	// --- radius (T7; replaces radius_scale, see sanitize_radius() docblock) --

	public function test_radius_clamps_to_the_zero_to_sixteen_px_range(): void {
		self::assertSame( 10, Settings::sanitize_radius( '' ) );
		self::assertSame( 0, Settings::sanitize_radius( -5 ) );
		self::assertSame( 16, Settings::sanitize_radius( 999 ) );
		self::assertSame( 6, Settings::sanitize_radius( '6' ) );
		self::assertSame( 10, Settings::sanitize_radius( new \stdClass() ) );
	}

	/**
	 * The retired radius_scale's string steps ('none', 'lg', …) are not
	 * numeric, so clamp()'s is_numeric() guard rejects them exactly like any
	 * other non-numeric input — a site that had radius_scale stored has that
	 * value simply ignored under the new key, never reinterpreted.
	 */
	public function test_a_retired_radius_scale_string_step_is_not_numeric_and_falls_back(): void {
		self::assertSame( 10, Settings::sanitize_radius( 'lg' ) );
		self::assertSame( 10, Settings::sanitize_radius( 'none' ) );
	}

	public function test_radius_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( '2' );
		self::assertSame( 2, Settings::radius() );
	}

	// --- font (T7, ADR-007) ---------------------------------------------

	public function test_font_is_a_closed_set(): void {
		self::assertSame( 'identity', Settings::sanitize_font( 'identity' ) );
		self::assertSame( 'system', Settings::sanitize_font( 'system' ) );
		self::assertSame( 'identity', Settings::sanitize_font( 'comic-sans' ) );
		self::assertSame( 'identity', Settings::sanitize_font( [] ) );
	}

	public function test_font_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( 'system' );
		self::assertSame( 'system', Settings::font() );
	}

	// --- cta_reveal (T7, ADR-008) ----------------------------------------

	public function test_cta_reveal_is_a_closed_set(): void {
		self::assertSame( 'hover', Settings::sanitize_cta_reveal( 'hover' ) );
		self::assertSame( 'always', Settings::sanitize_cta_reveal( 'always' ) );
		self::assertSame( 'hover', Settings::sanitize_cta_reveal( 'sometimes' ) );
		self::assertSame( 'hover', Settings::sanitize_cta_reveal( null ) );
	}

	public function test_cta_reveal_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( 'always' );
		self::assertSame( 'always', Settings::cta_reveal() );
	}
}
