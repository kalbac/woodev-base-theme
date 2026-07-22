<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Customizer;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Customizer\InlineStyles;
use Woodev\Theme\Base\Tests\Unit\TestCase;

final class InlineStylesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );
	}

	/**
	 * @param array<string, mixed> $mods Stored theme_mods.
	 */
	private function stub_mods( array $mods ): void {
		Functions\when( 'get_theme_mod' )->alias(
			static fn( string $key, $default = false ) => $mods[ $key ] ?? $default
		);
	}

	/**
	 * A site that has touched nothing must ship no inline <style> at all — the
	 * defaults already live in the stylesheet, and an empty block is noise on
	 * every page of every untouched install.
	 */
	public function test_untouched_defaults_emit_nothing(): void {
		$this->stub_mods( [] );

		self::assertSame( '', InlineStyles::build_css() );
	}

	public function test_a_non_default_container_width_emits_one_custom_property(): void {
		$this->stub_mods( [ 'container_width' => 1200 ] );

		self::assertSame( ":root{--wtb-container-max:1200px}\n", InlineStyles::build_css() );
	}

	public function test_a_non_default_radius_overrides_the_token(): void {
		$this->stub_mods( [ 'radius_scale' => 'lg' ] );

		self::assertSame( ":root{--radius:1rem}\n", InlineStyles::build_css() );
	}

	public function test_a_non_default_font_size_sets_the_root_size(): void {
		$this->stub_mods( [ 'base_font_size' => 18 ] );

		self::assertSame( "html{font-size:18px}\n", InlineStyles::build_css() );
	}

	/**
	 * A preset is a coherent tuple: both schemes are emitted, so switching to
	 * .dark keeps the accent readable (spec §6).
	 */
	public function test_a_primary_preset_emits_both_schemes(): void {
		$this->stub_mods( [ 'primary_preset' => 'blue' ] );

		$css = InlineStyles::build_css();

		self::assertStringContainsString( '--primary:oklch(54.6% 0.245 262.881)', $css );
		self::assertStringContainsString( '--ring:oklch(54.6% 0.245 262.881)', $css );
		self::assertStringContainsString( '.dark{', $css );
		self::assertStringContainsString( '--primary:oklch(70.7% 0.165 254.624)', $css );
	}

	public function test_the_default_preset_emits_no_colour_override(): void {
		$this->stub_mods( [ 'primary_preset' => 'default' ] );

		self::assertStringNotContainsString( '--primary', InlineStyles::build_css() );
	}

	public function test_everything_lands_in_one_root_block(): void {
		$this->stub_mods(
			[
				'container_width' => 1000,
				'radius_scale'    => 'none',
				'primary_preset'  => 'green',
			]
		);

		self::assertSame( 1, substr_count( InlineStyles::build_css(), ':root{' ) );
	}

	public function test_it_prints_after_the_stylesheets(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_head', \Mockery::type( 'array' ), 20 );

		( new InlineStyles() )->register();
	}
}
