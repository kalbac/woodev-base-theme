<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use Woodev\Theme\Base\StylePreset;

final class StylePresetTest extends TestCase {

	public function test_default_is_vega(): void {
		self::assertSame( StylePreset::Vega, StylePreset::default() );
	}

	public function test_from_theme_mod_defaults_to_vega_when_unset(): void {
		Functions\when( 'get_theme_mod' )->alias( static fn( string $key, $default = false ) => $default );

		self::assertSame( StylePreset::Vega, StylePreset::from_theme_mod() );
	}

	public function test_from_theme_mod_reads_a_valid_stored_pack(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'nova' );

		self::assertSame( StylePreset::Nova, StylePreset::from_theme_mod() );
	}

	/**
	 * A stale or hand-edited theme_mod holding a pack that was never built must
	 * not reach Assets — it would ask the manifest for a missing entry and the
	 * page would ship with no stylesheet at all. Fall back to the default pack.
	 */
	public function test_from_theme_mod_falls_back_for_an_unknown_pack(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'does-not-exist' );

		self::assertSame( StylePreset::Vega, StylePreset::from_theme_mod() );
	}

	/**
	 * Non-string theme_mods must fail closed to the default pack.
	 *
	 * The setting is `mixed`: get_theme_mod() returns whatever the database or a
	 * filter hands back, including a half-migrated option. A non-string must
	 * never reach a string
	 * cast: an array emits "Array to string conversion" and an object without
	 * __toString() throws Error — a fatal on every front-end request, since the
	 * resolver runs on wp_enqueue_scripts.
	 *
	 * @param mixed $stored Whatever the theme_mod hands back.
	 */
	#[DataProvider( 'non_string_theme_mods' )]
	public function test_from_theme_mod_falls_back_for_a_non_string_value( mixed $stored ): void {
		Functions\when( 'get_theme_mod' )->justReturn( $stored );

		self::assertSame( StylePreset::Vega, StylePreset::from_theme_mod() );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function non_string_theme_mods(): array {
		return [
			'array'  => [ [ 'vega' ] ],
			'object' => [ new \stdClass() ],
			'int'    => [ 42 ],
			'null'   => [ null ],
			'false'  => [ false ],
		];
	}

	public function test_css_entry_matches_the_vite_input_path(): void {
		self::assertSame( 'src/css/packs/vega.css', StylePreset::Vega->css_entry() );
		self::assertSame( 'src/css/packs/nova.css', StylePreset::Nova->css_entry() );
	}

	public function test_there_are_eight_packs_each_with_a_distinct_entry(): void {
		$entries = array_map( static fn( StylePreset $p ) => $p->css_entry(), StylePreset::cases() );

		self::assertCount( 8, StylePreset::cases() );
		self::assertSame( $entries, array_unique( $entries ) );
	}
}
