<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_Customize_Manager;
use WP_UnitTestCase;

/**
 * Colour-scheme resolution against a real WordPress (M1-05, spec §6):
 * the two Customizer settings, WordPress's own sanitize pipeline, the
 * `language_attributes` class, and the `wp_head` resolver script.
 */
final class SchemeTest extends WP_UnitTestCase {

	private WP_Customize_Manager $manager;

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';

		$this->manager = new WP_Customize_Manager();
		do_action( 'customize_register', $this->manager );
	}

	public function tear_down(): void {
		remove_theme_mod( 'color_scheme_default' );
		remove_theme_mod( 'color_scheme_toggle' );

		parent::tear_down();
	}

	public function test_both_settings_are_registered_with_their_documented_defaults(): void {
		$default_setting = $this->manager->get_setting( 'color_scheme_default' );
		$toggle_setting  = $this->manager->get_setting( 'color_scheme_toggle' );

		self::assertNotNull( $default_setting, 'color_scheme_default is not registered with WordPress' );
		self::assertSame( 'system', $default_setting->default );
		self::assertIsCallable( $default_setting->sanitize_callback );

		self::assertNotNull( $toggle_setting, 'color_scheme_toggle is not registered with WordPress' );
		self::assertTrue( $toggle_setting->default );
		self::assertIsCallable( $toggle_setting->sanitize_callback );
	}

	/**
	 * Runs the value through WordPress's own `WP_Customize_Setting::sanitize()`,
	 * not our validator directly — this is what proves the callback is wired
	 * into WordPress, not merely present and callable (CustomizerTest carries
	 * the same distinction for the rest of the settings).
	 */
	public function test_wordpress_own_sanitize_pipeline_rejects_a_bogus_scheme(): void {
		self::assertSame(
			'system',
			$this->manager->get_setting( 'color_scheme_default' )->sanitize( 'sepia' )
		);
	}

	public function test_an_explicit_default_reaches_language_attributes_as_a_class(): void {
		set_theme_mod( 'color_scheme_default', 'dark' );

		self::assertStringContainsString( 'class="dark"', get_language_attributes() );
	}

	/**
	 * `system` sets no class at all — that is what lets the generated
	 * `prefers-color-scheme` block in the token CSS decide for a visitor with
	 * JS disabled. Asserting the absence of BOTH class names (not merely
	 * `class=`) is what would catch a stray leftover from a previous scheme.
	 */
	public function test_system_carries_neither_light_nor_dark_in_language_attributes(): void {
		set_theme_mod( 'color_scheme_default', 'system' );

		$attributes = get_language_attributes();

		self::assertStringNotContainsString( 'dark', $attributes );
		self::assertStringNotContainsString( 'light', $attributes );
	}

	public function test_wp_head_prints_the_resolver_script(): void {
		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();

		self::assertStringContainsString( '<script id="woodev-base-scheme">', $head );
	}

	/**
	 * With the toggle off there is no stored visitor choice to honour, so the
	 * script printed on the real page must not even mention `localStorage` —
	 * not merely guard the read behind a runtime condition that happens to be
	 * false (SchemeHeadTest pins the same contract at the unit level).
	 */
	public function test_toggle_off_omits_any_localstorage_read_from_wp_head(): void {
		set_theme_mod( 'color_scheme_toggle', false );

		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();

		self::assertStringContainsString( '<script id="woodev-base-scheme">', $head );
		self::assertStringNotContainsString( 'localStorage', $head );
	}
}
