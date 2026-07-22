<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Customizer;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Customizer\Settings;
use Woodev\Theme\Base\Tests\Unit\TestCase;

final class SettingsTest extends TestCase {

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

	public function test_radius_scale_is_a_closed_set(): void {
		self::assertSame( 'md', Settings::sanitize_radius_scale( 'md' ) );
		self::assertSame( 'none', Settings::sanitize_radius_scale( 'none' ) );
		self::assertSame( 'md', Settings::sanitize_radius_scale( '9999px' ) );
		self::assertSame( 'md', Settings::sanitize_radius_scale( [] ) );
	}

	public function test_radius_value_maps_to_a_css_length(): void {
		self::assertSame( '0rem', Settings::radius_value( 'none' ) );
		self::assertSame( '0.625rem', Settings::radius_value( 'md' ) );
	}

	public function test_resolvers_read_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( '1100' );
		self::assertSame( 1100, Settings::container_width() );

		$this->stub_theme_mod( new \stdClass() );
		self::assertSame( 1440, Settings::container_width() );
	}

	public function test_primary_preset_defaults_to_inheriting_the_pack(): void {
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );

		self::assertSame( 'default', Settings::sanitize_primary_preset( 'default' ) );
		self::assertSame( 'blue', Settings::sanitize_primary_preset( 'blue' ) );
		self::assertSame( 'default', Settings::sanitize_primary_preset( 'chartreuse' ) );
		self::assertSame( 'default', Settings::sanitize_primary_preset( new \stdClass() ) );
	}

	public function test_presets_are_read_from_the_generated_map(): void {
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );

		$presets = Settings::presets();

		self::assertArrayHasKey( 'blue', $presets );
		self::assertArrayNotHasKey( 'default', $presets, 'default means "no override" and must not be a map entry' );
		self::assertSame(
			[ '--primary', '--primary-foreground', '--ring' ],
			array_keys( $presets['blue']['light'] )
		);
	}

	/**
	 * A missing or half-written generated file must degrade to "no presets
	 * offered", never to a fatal or to a CSS var built from garbage.
	 */
	public function test_a_missing_generated_map_yields_no_presets(): void {
		Functions\when( 'get_template_directory' )->justReturn( '/nonexistent/theme' );

		self::assertSame( [], Settings::presets() );
	}
}
