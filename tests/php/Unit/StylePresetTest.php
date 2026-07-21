<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\StylePreset;

final class StylePresetTest extends TestCase {

	public function test_default_is_vega(): void {
		self::assertSame( StylePreset::Vega, StylePreset::default() );
	}

	public function test_from_theme_mod_defaults_to_vega_when_unset(): void {
		Functions\when( 'get_theme_mod' )->alias( static fn( string $key, $default = false ) => $default );

		self::assertSame( StylePreset::Vega, StylePreset::fromThemeMod() );
	}

	public function test_from_theme_mod_reads_a_valid_stored_pack(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'nova' );

		self::assertSame( StylePreset::Nova, StylePreset::fromThemeMod() );
	}

	/**
	 * A stale or hand-edited theme_mod holding a pack that was never built must
	 * not reach Assets — it would ask the manifest for a missing entry and the
	 * page would ship with no stylesheet at all. Fall back to the default pack.
	 */
	public function test_from_theme_mod_falls_back_for_an_unknown_pack(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'does-not-exist' );

		self::assertSame( StylePreset::Vega, StylePreset::fromThemeMod() );
	}

	public function test_css_entry_matches_the_vite_input_path(): void {
		self::assertSame( 'src/css/packs/vega.css', StylePreset::Vega->cssEntry() );
		self::assertSame( 'src/css/packs/nova.css', StylePreset::Nova->cssEntry() );
	}

	public function test_there_are_eight_packs_each_with_a_distinct_entry(): void {
		$entries = array_map( static fn( StylePreset $p ) => $p->cssEntry(), StylePreset::cases() );

		self::assertCount( 8, StylePreset::cases() );
		self::assertSame( $entries, array_unique( $entries ) );
	}
}
