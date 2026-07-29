<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Customizer;

use Brain\Monkey\Functions;
use Mockery;
use Woodev\Theme\Base\Customizer\Customizer;
use Woodev\Theme\Base\Customizer\Palettes;
use Woodev\Theme\Base\Tests\Unit\TestCase;

final class CustomizerTest extends TestCase {

	/**
	 * Settings that must exist, with the default the theme documents.
	 *
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function expected_settings(): array {
		return [
			'color_scheme_default'    => [ 'color_scheme_default', 'system' ],
			'color_scheme_toggle'     => [ 'color_scheme_toggle', true ],
			'palette'                 => [ 'palette', 'warm-clay' ],
			'accent'                  => [ 'accent', '' ],
			'base_font_size'          => [ 'base_font_size', 16 ],
			'font'                    => [ 'font', 'identity' ],
			'container_width'         => [ 'container_width', 1440 ],
			'radius'                  => [ 'radius', 10 ],
			'sidebar_position'        => [ 'sidebar_position', 'none' ],
			'header_variant'          => [ 'header_variant', 'inline' ],
			'footer_variant'          => [ 'footer_variant', 'simple' ],
			'cta_reveal'              => [ 'cta_reveal', 'hover' ],
			'product_trust_badge_one' => [ 'product_trust_badge_one', '' ],
			'product_trust_badge_two' => [ 'product_trust_badge_two', '' ],
			'cart_secure_note'        => [ 'cart_secure_note', '' ],
			'checkout_secure_note'    => [ 'checkout_secure_note', '' ],
			'front_hero_eyebrow'      => [ 'front_hero_eyebrow', '' ],
			'front_hero_lede'         => [ 'front_hero_lede', '' ],
			'front_hero_trust'        => [ 'front_hero_trust', '' ],
			'front_hero_art'          => [ 'front_hero_art', 'auto' ],
			'front_value_items'       => [ 'front_value_items', '' ],
			'front_promo_title'       => [ 'front_promo_title', '' ],
			'front_promo_text'        => [ 'front_promo_text', '' ],
			'front_promo_cta_label'   => [ 'front_promo_cta_label', '' ],
			'front_promo_cta_url'     => [ 'front_promo_cta_url', '' ],
			'front_promo_image'       => [ 'front_promo_image', 0 ],
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

		// Front page (F2) sanitize callbacks call these real WP functions
		// directly; test_the_sanitize_callbacks_reject_junk() below exercises
		// every registered callback, including on its own fallback value, so
		// each one needs a stub here even though this test never asserts on
		// their behaviour directly (SettingsTest is where that is pinned).
		Functions\when( 'sanitize_text_field' )->alias( static fn ( $value ): string => \trim( (string) $value ) );
		Functions\when( 'sanitize_textarea_field' )->alias( static fn ( $value ): string => \trim( (string) $value ) );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( $value ): int => \abs( (int) $value ) );

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
		// The accent control's add_color() falls back to a plain (id, args)
		// add_control() call here: \WP_Customize_Color_Control does not exist
		// in this suite (no WP code is loaded at all), so class_exists()
		// picks the same two-argument shape every other control already
		// uses. See Customizer::add_color()'s docblock.
		$manager->shouldReceive( 'add_control' )->andReturnUsing(
			static function ( string $id, array $args ) use ( &$recorded ) {
				$recorded['controls'][]          = $id;
				$recorded['control_args'][ $id ] = $args;
			}
		);

		( new Customizer() )->configure( $manager );

		return $recorded;
	}

	public function test_it_registers_the_seven_sections(): void {
		self::assertSame(
			[
				'woodev_base_colors',
				'woodev_base_typography',
				'woodev_base_layout',
				'woodev_base_header',
				'woodev_base_footer',
				'woodev_base_shop',
				'woodev_base_front',
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
	 * The control TYPE each front-page setting renders as.
	 *
	 * Pinned because the sanitizer cannot tell the difference and so cannot
	 * catch a regression here: sanitize_text_field() collapses newlines, so a
	 * one-line value stored through a `<textarea>` round-trips identically to
	 * one stored through an `<input>`. The damage is to the admin — a
	 * multi-line box for a button label or a URL invites a paragraph, and the
	 * Enter key inserts a newline the sanitizer then silently eats. The first
	 * pass registered all six single-line settings as textareas for exactly
	 * that invisible-in-tests reason.
	 *
	 * @var array<string, string>
	 */
	private const FRONT_CONTROL_TYPES = [
		'front_hero_eyebrow'    => 'text',
		'front_hero_lede'       => 'textarea',
		'front_hero_trust'      => 'textarea',
		'front_hero_art'        => 'select',
		'front_value_items'     => 'textarea',
		'front_promo_title'     => 'text',
		'front_promo_text'      => 'textarea',
		'front_promo_cta_label' => 'text',
		'front_promo_cta_url'   => 'url',
	];

	public function test_the_front_page_controls_use_the_right_input_types(): void {
		$args = $this->capture()['control_args'];

		foreach ( self::FRONT_CONTROL_TYPES as $id => $type ) {
			self::assertArrayHasKey( $id, $args, "{$id} has no control" );
			self::assertSame( $type, $args[ $id ]['type'] ?? '', "{$id} renders as the wrong control type" );
		}
	}

	/**
	 * Settings whose fail-closed value is deliberately NOT their default, with
	 * the reason. Everything else must agree, and the assertion below keeps it
	 * that way.
	 *
	 * @var array<string, mixed>
	 */
	private const JUNK_FALLBACKS = [
		// Spec §6 ships the switcher ON. But if the stored value is unreadable,
		// the safe outcome is to NOT render a control we cannot reason about —
		// a missing switcher is a smaller failure than one whose state is
		// unknown. So "default" and "fail closed" legitimately differ here, and
		// this is the only setting where they do.
		'color_scheme_toggle' => false,
	];

	/**
	 * Each sanitize callback must be the SAME validator the front end resolves
	 * with — otherwise the Customizer can store a value the renderer rejects.
	 */
	public function test_the_sanitize_callbacks_reject_junk(): void {
		foreach ( $this->capture()['settings'] as $id => $args ) {
			$sanitized = \call_user_func( $args['sanitize_callback'], new \stdClass() );
			$expected  = self::JUNK_FALLBACKS[ $id ] ?? $args['default'];

			self::assertSame(
				$expected,
				$sanitized,
				"{$id} did not fall back to the value this test documents for a non-scalar"
			);

			// Whatever it fell back to must itself be stable: sanitising the
			// fallback again cannot move it. Without this, an exception in the
			// map above could hide a callback that never settles.
			self::assertSame(
				$sanitized,
				\call_user_func( $args['sanitize_callback'], $sanitized ),
				"{$id}'s fallback is not a fixed point of its own sanitizer"
			);
		}
	}

	/**
	 * The palette control's choices are exactly Palettes::slugs() — an admin
	 * must never be offered a slug the renderer has no data for.
	 */
	public function test_the_palette_control_offers_exactly_the_seven_shipped_slugs(): void {
		$choices = $this->capture()['control_args']['palette']['choices'];

		self::assertSame(
			[ 'warm-clay', 'cold-petrol', 'graphite', 'forest', 'sand', 'wine', 'night-indigo' ],
			array_keys( $choices )
		);
	}

	/**
	 * Every shipped palette must have an EXPLICIT, translatable label.
	 *
	 * `palette_choices()` falls back to `ucwords( str_replace( '-', ' ', $slug ) )`
	 * for a slug its label map has never heard of. That fallback is a reasonable
	 * runtime safety net and a terrible early-warning system: adding an eighth
	 * palette to `src/tokens/tokens.mjs` without adding a label here breaks
	 * nothing, throws nothing and logs nothing — a Russian-locale admin simply
	 * sees one English word among seven translated ones, which is the kind of
	 * defect that ships.
	 *
	 * So the contract is asserted where it can fail loudly instead: the moment a
	 * palette exists without a label, this test goes red.
	 */
	public function test_every_shipped_palette_has_an_explicit_translatable_label(): void {
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );

		// Compared against the label MAP, not against the rendered choices: the
		// labels are deliberately the title-cased slugs, so `Warm Clay` from the
		// map and `Warm Clay` from the fallback are the same string. Only the
		// keys can tell them apart.
		self::assertSame(
			Palettes::slugs(),
			array_keys( Customizer::palette_labels() ),
			'A palette exists without an explicit label, so palette_choices() will derive an ' .
			'untranslatable one. Add it to Customizer::palette_labels().'
		);
	}

	public function test_register_hooks_customize_register(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'customize_register', \Mockery::type( 'array' ) );

		( new Customizer() )->register();
	}
}
