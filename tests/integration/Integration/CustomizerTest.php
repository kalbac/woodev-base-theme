<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use Woodev\Theme\Base\Customizer\InlineStyles;
use Woodev\Theme\Base\Customizer\Settings;
use WP_Customize_Manager;
use WP_UnitTestCase;

final class CustomizerTest extends WP_UnitTestCase {

	private WP_Customize_Manager $manager;

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';

		$this->manager = new WP_Customize_Manager();
		do_action( 'customize_register', $this->manager );
	}

	public function tear_down(): void {
		foreach ( array_keys( self::SETTINGS ) as $id ) {
			remove_theme_mod( $id );
		}

		parent::tear_down();
	}

	private const SETTINGS = [
		'style_preset'     => 'vega',
		'primary_preset'   => 'default',
		'base_font_size'   => 16,
		'container_width'  => 1440,
		'radius_scale'     => 'md',
		'sidebar_position' => 'none',
		'header_variant'   => 'inline',
		'footer_variant'   => 'simple',
	];

	public function test_wordpress_accepts_every_setting_with_its_default(): void {
		foreach ( self::SETTINGS as $id => $default ) {
			$setting = $this->manager->get_setting( $id );

			self::assertNotNull( $setting, "{$id} is not registered with WordPress" );
			self::assertSame( $default, $setting->default, "{$id} has the wrong default" );
			self::assertNotEmpty( $setting->sanitize_callback, "{$id} has no sanitize_callback" );
		}
	}

	public function test_wordpress_registers_a_control_for_every_setting(): void {
		foreach ( array_keys( self::SETTINGS ) as $id ) {
			self::assertNotNull( $this->manager->get_control( $id ), "{$id} has no control" );
		}
	}

	/**
	 * The value goes through WordPress's own sanitize pipeline, not ours
	 * directly — this is what proves the callbacks are wired, not merely
	 * present.
	 */
	public function test_wordpress_sanitizes_an_out_of_range_container_width(): void {
		self::assertSame( 1920, $this->manager->get_setting( 'container_width' )->sanitize( '999999' ) );
		self::assertSame( 'vega', $this->manager->get_setting( 'style_preset' )->sanitize( 'not-a-pack' ) );
	}

	public function test_a_stored_setting_reaches_the_inline_stylesheet(): void {
		set_theme_mod( 'container_width', 1100 );
		set_theme_mod( 'primary_preset', 'blue' );

		$css = InlineStyles::build_css();

		self::assertStringContainsString( '--wtb-container-max:1100px', $css );
		self::assertStringContainsString( '--primary:oklch(54.6%', $css );
		self::assertStringContainsString( '.dark{', $css );
	}

	public function test_an_untouched_site_prints_no_inline_block(): void {
		ob_start();
		( new InlineStyles() )->print_styles();
		$output = (string) ob_get_clean();

		self::assertSame( '', $output );
	}

	public function test_the_generated_preset_map_is_present_in_the_shipped_theme(): void {
		$presets = Settings::presets();

		self::assertCount( 8, $presets, 'inc/generated/primary-presets.php is missing or stale — run npm run tokens' );
	}

	/**
	 * Prove the block reaches the page, not merely that a callback is hooked.
	 *
	 * Calling has_action() with a fresh InlineStyles instance would never match — the
	 * theme registered a DIFFERENT object and WordPress compares identity — so
	 * this renders wp_head and reads the result. The "after the stylesheets"
	 * half of the contract is pinned in the browser (Task 8), where a wrong
	 * priority actually changes the computed colour.
	 */
	public function test_a_non_default_setting_reaches_wp_head(): void {
		set_theme_mod( 'base_font_size', 18 );

		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();

		self::assertStringContainsString( '<style id="woodev-base-inline">', $head );
		self::assertStringContainsString( 'html{font-size:18px}', $head );
	}
}
