<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Customizer;

use Brain\Monkey\Functions;
use Mockery;
use Woodev\Theme\Base\Customizer\Customizer;
use Woodev\Theme\Base\Customizer\Settings;
use Woodev\Theme\Base\Tests\Unit\TestCase;

final class CustomizerTest extends TestCase {

	/**
	 * Settings that must exist, with the default the theme documents.
	 *
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function expected_settings(): array {
		return [
			'style_preset'         => [ 'style_preset', 'vega' ],
			'primary_preset'       => [ 'primary_preset', 'default' ],
			'color_scheme_default' => [ 'color_scheme_default', 'system' ],
			'color_scheme_toggle'  => [ 'color_scheme_toggle', false ],
			'base_font_size'       => [ 'base_font_size', 16 ],
			'container_width'      => [ 'container_width', 1440 ],
			'radius_scale'         => [ 'radius_scale', 'md' ],
			'sidebar_position'     => [ 'sidebar_position', 'none' ],
			'header_variant'       => [ 'header_variant', 'inline' ],
			'footer_variant'       => [ 'footer_variant', 'simple' ],
		];
	}

	/**
	 * Run configure() against a recording double and return what it registered.
	 *
	 * @return array{sections: list<string>, settings: array<string, array<string, mixed>>, controls: list<string>}
	 */
	private function capture(): array {
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );

		$recorded = [
			'sections'     => [],
			'settings'     => [],
			'controls'     => [],
			'control_args' => [],
		];

		// Mockery generates the class when WordPress is not loaded, so the real
		// \WP_Customize_Manager type hint can stay on configure().
		$manager = Mockery::mock( 'WP_Customize_Manager' );

		$manager->shouldReceive( 'add_section' )->andReturnUsing(
			static function ( string $id ) use ( &$recorded ) {
				$recorded['sections'][] = $id;
			}
		);
		$manager->shouldReceive( 'add_setting' )->andReturnUsing(
			static function ( string $id, array $args ) use ( &$recorded ) {
				$recorded['settings'][ $id ] = $args;
			}
		);
		$manager->shouldReceive( 'add_control' )->andReturnUsing(
			static function ( string $id, array $args ) use ( &$recorded ) {
				$recorded['controls'][]          = $id;
				$recorded['control_args'][ $id ] = $args;
			}
		);

		( new Customizer() )->configure( $manager );

		return $recorded;
	}

	public function test_it_registers_the_five_v1_sections(): void {
		self::assertSame(
			[
				'woodev_base_colors',
				'woodev_base_typography',
				'woodev_base_layout',
				'woodev_base_header',
				'woodev_base_footer',
			],
			$this->capture()['sections']
		);
	}

	/**
	 * A wp.org Theme Review requirement and the reason this class exists: a
	 * setting without a sanitize callback writes whatever the request carried.
	 */
	public function test_every_setting_has_a_callable_sanitize_callback(): void {
		foreach ( $this->capture()['settings'] as $id => $args ) {
			self::assertArrayHasKey( 'sanitize_callback', $args, "{$id} has no sanitize_callback" );
			self::assertIsCallable( $args['sanitize_callback'], "{$id}'s sanitize_callback is not callable" );
		}
	}

	public function test_it_registers_every_documented_setting_with_its_default(): void {
		$settings = $this->capture()['settings'];

		foreach ( self::expected_settings() as [ $id, $default ] ) {
			self::assertArrayHasKey( $id, $settings, "{$id} was never registered" );
			self::assertSame( $default, $settings[ $id ]['default'], "{$id} has the wrong default" );
		}
	}

	public function test_every_setting_has_a_control(): void {
		$recorded = $this->capture();

		self::assertSame(
			array_keys( $recorded['settings'] ),
			$recorded['controls'],
			'A setting with no control is invisible to the admin'
		);
	}

	/**
	 * Codex P2 on the M1-04 diff. The accent slugs are generated from the token
	 * source while their labels are hand-written literals (a .pot scanner needs
	 * literals). Add a preset to src/tokens/tokens.mjs without adding a label
	 * and the control silently drops it — so this compares the control's choices
	 * against the REAL generated map, not a copy of the list.
	 */
	public function test_every_generated_preset_is_offered_with_a_translated_label(): void {
		$recorded = $this->capture();
		$choices  = $recorded['control_args']['primary_preset']['choices'];

		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );

		foreach ( array_keys( Settings::presets() ) as $slug ) {
			self::assertArrayHasKey(
				$slug,
				$choices,
				"The generated preset '{$slug}' has no label in Customizer::primary_preset_choices()"
			);
		}

		self::assertArrayHasKey( 'default', $choices );
		self::assertCount( \count( Settings::presets() ) + 1, $choices );
	}

	/**
	 * Each sanitize callback must be the SAME validator the front end resolves
	 * with — otherwise the Customizer can store a value the renderer rejects.
	 */
	public function test_the_sanitize_callbacks_reject_junk(): void {
		foreach ( $this->capture()['settings'] as $id => $args ) {
			$sanitized = \call_user_func( $args['sanitize_callback'], new \stdClass() );

			self::assertSame(
				$args['default'],
				$sanitized,
				"{$id} did not fall back to its default for a non-scalar value"
			);
		}
	}

	public function test_register_hooks_customize_register(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'customize_register', \Mockery::type( 'array' ) );

		( new Customizer() )->register();
	}
}
