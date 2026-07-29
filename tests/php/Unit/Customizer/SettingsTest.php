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

		// Front page (F2) sanitizers call these real WP functions directly.
		// No WordPress code loads under Brain\Monkey, so every one of them
		// needs an explicit alias — a close-enough approximation of core's
		// behaviour, not a re-implementation of it.
		Functions\when( 'sanitize_text_field' )->alias(
			static fn ( $value ): string => \trim( (string) \preg_replace( '/[\r\n\t ]+/', ' ', \strip_tags( (string) $value ) ) )
		);
		Functions\when( 'sanitize_textarea_field' )->alias(
			static fn ( $value ): string => \trim( \strip_tags( (string) $value ) )
		);
		Functions\when( 'esc_url_raw' )->alias(
			static function ( $value ): string {
				if ( ! \is_string( $value ) ) {
					return '';
				}

				$value = \trim( $value );

				if ( '' === $value ) {
					return '';
				}

				$scheme = \strtolower( (string) \parse_url( $value, PHP_URL_SCHEME ) );

				$allowed_schemes = [ 'http', 'https', 'mailto', 'ftp', 'ftps', 'tel' ];

				if ( '' !== $scheme && ! \in_array( $scheme, $allowed_schemes, true ) ) {
					return '';
				}

				return $value;
			}
		);
		Functions\when( 'absint' )->alias( static fn ( $value ): int => \abs( (int) $value ) );
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

	// --- front page (F2, docs/plans/2026-07-28-front-page-completion.md) ---

	public function test_front_hero_eyebrow_sanitizes_a_valid_value(): void {
		self::assertSame( 'New arrivals', Settings::sanitize_front_hero_eyebrow( '  New arrivals  ' ) );
	}

	public function test_front_hero_eyebrow_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_front_hero_eyebrow( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_front_hero_eyebrow( new \stdClass() ) );
		self::assertSame( '', Settings::sanitize_front_hero_eyebrow( 42 ) );
	}

	public function test_front_hero_eyebrow_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( '  Hello  ' );
		self::assertSame( 'Hello', Settings::front_hero_eyebrow() );
	}

	public function test_front_hero_lede_sanitizes_a_valid_value(): void {
		self::assertSame( 'Quality that lasts.', Settings::sanitize_front_hero_lede( 'Quality that lasts.' ) );
	}

	public function test_front_hero_lede_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_front_hero_lede( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_front_hero_lede( new \stdClass() ) );
		self::assertSame( '', Settings::sanitize_front_hero_lede( false ) );
	}

	public function test_front_hero_lede_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( 'Tagline override' );
		self::assertSame( 'Tagline override', Settings::front_hero_lede() );
	}

	public function test_front_hero_trust_resolver_parses_valid_lines_into_structured_items(): void {
		$this->stub_theme_mod( "Free shipping | truck\nSecure checkout | shield-check" );

		self::assertSame(
			[
				[
					'label' => 'Free shipping',
					'icon'  => 'truck',
				],
				[
					'label' => 'Secure checkout',
					'icon'  => 'shield-check',
				],
			],
			Settings::front_hero_trust()
		);
	}

	public function test_front_hero_trust_sanitizer_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_front_hero_trust( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_front_hero_trust( new \stdClass() ) );
		self::assertSame( '', Settings::sanitize_front_hero_trust( 42 ) );
	}

	/**
	 * A nine-line textarea must yield exactly FRONT_HERO_TRUST_MAX_ITEMS (3)
	 * items — the cap applies to the raw line count, not to how many the
	 * admin intended.
	 */
	public function test_front_hero_trust_caps_at_three_items(): void {
		$this->stub_theme_mod(
			\implode(
				"\n",
				[
					'One | truck',
					'Two | leaf',
					'Three | package',
					'Four | check',
					'Five | check',
					'Six | check',
					'Seven | check',
					'Eight | check',
					'Nine | check',
				]
			)
		);

		$items = Settings::front_hero_trust();

		self::assertCount( 3, $items );
		self::assertSame( 'One', $items[0]['label'] );
		self::assertSame( 'Three', $items[2]['label'] );
	}

	public function test_front_hero_trust_drops_a_line_with_an_empty_label(): void {
		$this->stub_theme_mod( " | truck\nReal badge | leaf" );

		self::assertSame(
			[
				[
					'label' => 'Real badge',
					'icon'  => 'leaf',
				],
			],
			Settings::front_hero_trust()
		);
	}

	public function test_front_hero_trust_defaults_the_icon_when_missing_or_unrecognised(): void {
		$this->stub_theme_mod( "No icon badge |\nUnknown icon badge | not-a-real-icon" );

		self::assertSame(
			[
				[
					'label' => 'No icon badge',
					'icon'  => 'check',
				],
				[
					'label' => 'Unknown icon badge',
					'icon'  => 'check',
				],
			],
			Settings::front_hero_trust()
		);
	}

	public function test_front_hero_art_is_a_closed_set(): void {
		self::assertSame( 'auto', Settings::sanitize_front_hero_art( 'auto' ) );
		self::assertSame( 'off', Settings::sanitize_front_hero_art( 'off' ) );
		self::assertSame( 'auto', Settings::sanitize_front_hero_art( 'sometimes' ) );
		self::assertSame( 'auto', Settings::sanitize_front_hero_art( [] ) );
		self::assertSame( 'auto', Settings::sanitize_front_hero_art( new \stdClass() ) );
	}

	public function test_front_hero_art_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( 'off' );
		self::assertSame( 'off', Settings::front_hero_art() );
	}

	// ------------------------------------------------------- product trust badges

	public function test_product_trust_badge_one_resolver_parses_a_valid_line(): void {
		$this->stub_theme_mod( 'Завтра, если заказать до 18:00 | truck' );

		self::assertSame(
			[
				'label' => 'Завтра, если заказать до 18:00',
				'icon'  => 'truck',
			],
			Settings::product_trust_badge_one()
		);
	}

	public function test_product_trust_badge_one_resolver_returns_null_when_unset(): void {
		$this->stub_theme_mod( '' );
		self::assertNull( Settings::product_trust_badge_one() );
	}

	public function test_product_trust_badge_one_resolver_returns_null_for_a_line_with_an_empty_label(): void {
		$this->stub_theme_mod( ' | truck' );
		self::assertNull( Settings::product_trust_badge_one() );
	}

	public function test_product_trust_badge_one_sanitizer_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_product_trust_badge_one( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_product_trust_badge_one( new \stdClass() ) );
		self::assertSame( '', Settings::sanitize_product_trust_badge_one( 42 ) );
	}

	public function test_product_trust_badge_one_defaults_the_icon_when_missing_or_unrecognised(): void {
		$this->stub_theme_mod( 'No icon badge |' );
		self::assertSame(
			[
				'label' => 'No icon badge',
				'icon'  => 'check',
			],
			Settings::product_trust_badge_one()
		);

		$this->stub_theme_mod( 'Unknown icon badge | not-a-real-icon' );
		self::assertSame(
			[
				'label' => 'Unknown icon badge',
				'icon'  => 'check',
			],
			Settings::product_trust_badge_one()
		);
	}

	/**
	 * This is a plain text control, not a textarea — a stray second line
	 * (however it got there) is not a second badge, so only the first is
	 * ever read.
	 */
	public function test_product_trust_badge_one_ignores_a_second_line(): void {
		$this->stub_theme_mod( "First badge | truck\nSecond line | leaf" );
		self::assertSame(
			[
				'label' => 'First badge',
				'icon'  => 'truck',
			],
			Settings::product_trust_badge_one()
		);
	}

	public function test_product_trust_badge_two_resolver_parses_a_valid_line(): void {
		$this->stub_theme_mod( 'Гарантия 2 года | shield-check' );

		self::assertSame(
			[
				'label' => 'Гарантия 2 года',
				'icon'  => 'shield-check',
			],
			Settings::product_trust_badge_two()
		);
	}

	public function test_product_trust_badge_two_resolver_returns_null_when_unset(): void {
		$this->stub_theme_mod( '' );
		self::assertNull( Settings::product_trust_badge_two() );
	}

	public function test_product_trust_badge_two_sanitizer_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_product_trust_badge_two( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_product_trust_badge_two( new \stdClass() ) );
	}

	/**
	 * The two secure-note settings (#42, plan rows C10/K9) share the badge-line
	 * sanitizer with the product badges above, and differ in exactly one place:
	 * an icon-less line falls back to `lock`, not `check`.
	 */
	public function test_secure_note_resolvers_parse_a_valid_line(): void {
		$this->stub_theme_mod( 'Payment happens on the bank page | shield-check' );

		self::assertSame(
			[
				'label' => 'Payment happens on the bank page',
				'icon'  => 'shield-check',
			],
			Settings::cart_secure_note()
		);
		self::assertSame(
			[
				'label' => 'Payment happens on the bank page',
				'icon'  => 'shield-check',
			],
			Settings::checkout_secure_note()
		);
	}

	public function test_secure_note_resolvers_return_null_when_unset(): void {
		$this->stub_theme_mod( '' );
		self::assertNull( Settings::cart_secure_note() );
		self::assertNull( Settings::checkout_secure_note() );
	}

	public function test_secure_note_resolvers_default_the_icon_to_lock_not_check(): void {
		$this->stub_theme_mod( 'No icon note |' );
		self::assertSame(
			[
				'label' => 'No icon note',
				'icon'  => 'lock',
			],
			Settings::cart_secure_note()
		);

		$this->stub_theme_mod( 'Unknown icon note | not-a-real-icon' );
		self::assertSame(
			[
				'label' => 'Unknown icon note',
				'icon'  => 'lock',
			],
			Settings::checkout_secure_note()
		);
	}

	/**
	 * The regression this pins is one the first implementation actually had:
	 * substituting `lock` for `check` AFTER parsing cannot tell "no icon given"
	 * from "`check` given on purpose", so an admin who typed `… | check` got a
	 * padlock. The default is threaded down to the icon validator instead, and
	 * this is the assertion that fails if anyone reverts to the shortcut.
	 */
	public function test_secure_note_keeps_an_explicitly_chosen_check_icon(): void {
		$this->stub_theme_mod( 'Order confirmed by email | check' );

		self::assertSame(
			[
				'label' => 'Order confirmed by email',
				'icon'  => 'check',
			],
			Settings::cart_secure_note()
		);
	}

	/**
	 * The sanitizer WRITES the resolved icon back into the stored value, so the
	 * stored string has to carry the same `lock` the resolver reports — a
	 * mismatch here is how a setting starts disagreeing with the page.
	 */
	public function test_secure_note_sanitizer_canonicalises_with_the_lock_default(): void {
		self::assertSame( 'Secure payment | lock', Settings::sanitize_cart_secure_note( 'Secure payment |' ) );
		self::assertSame( 'Secure payment | lock', Settings::sanitize_checkout_secure_note( 'Secure payment | nope' ) );
		self::assertSame( 'Secure payment | truck', Settings::sanitize_cart_secure_note( 'Secure payment | truck' ) );
	}

	public function test_secure_note_sanitizers_reject_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_cart_secure_note( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_cart_secure_note( new \stdClass() ) );
		self::assertSame( '', Settings::sanitize_checkout_secure_note( 42 ) );
	}

	/**
	 * `lock` was added to the whitelist for these two settings, and the badge
	 * settings share that whitelist — so it has to be reachable from them too,
	 * rather than being a slug the icon validator accepts in one caller and
	 * rejects in another.
	 */
	public function test_lock_is_selectable_from_the_shared_badge_whitelist(): void {
		self::assertContains( 'lock', Settings::FRONT_ICONS );

		$this->stub_theme_mod( 'Secure checkout | lock' );
		self::assertSame(
			[
				'label' => 'Secure checkout',
				'icon'  => 'lock',
			],
			Settings::product_trust_badge_one()
		);
	}

	public function test_front_value_items_resolver_parses_valid_lines_into_structured_items(): void {
		$this->stub_theme_mod( "Fast delivery | Same-day dispatch | truck\nEco packaging | Recycled materials | leaf" );

		self::assertSame(
			[
				[
					'title' => 'Fast delivery',
					'text'  => 'Same-day dispatch',
					'icon'  => 'truck',
				],
				[
					'title' => 'Eco packaging',
					'text'  => 'Recycled materials',
					'icon'  => 'leaf',
				],
			],
			Settings::front_value_items()
		);
	}

	public function test_front_value_items_sanitizer_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_front_value_items( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_front_value_items( new \stdClass() ) );
		self::assertSame( '', Settings::sanitize_front_value_items( false ) );
	}

	/**
	 * A nine-line textarea must yield exactly FRONT_VALUE_ITEMS_MAX_ITEMS (4)
	 * items.
	 */
	public function test_front_value_items_caps_at_four_items(): void {
		$this->stub_theme_mod(
			\implode(
				"\n",
				[
					'One | a | truck',
					'Two | b | leaf',
					'Three | c | package',
					'Four | d | check',
					'Five | e | check',
					'Six | f | check',
					'Seven | g | check',
					'Eight | h | check',
					'Nine | i | check',
				]
			)
		);

		$items = Settings::front_value_items();

		self::assertCount( 4, $items );
		self::assertSame( 'Four', $items[3]['title'] );
	}

	public function test_front_value_items_drops_a_line_with_an_empty_title(): void {
		$this->stub_theme_mod( " | Text only | truck\nReal item | Text | leaf" );

		self::assertSame(
			[
				[
					'title' => 'Real item',
					'text'  => 'Text',
					'icon'  => 'leaf',
				],
			],
			Settings::front_value_items()
		);
	}

	public function test_front_value_items_allows_an_empty_text_field_and_defaults_the_icon(): void {
		$this->stub_theme_mod( "Title only |  | \nUnknown icon | Text | not-a-real-icon" );

		self::assertSame(
			[
				[
					'title' => 'Title only',
					'text'  => '',
					'icon'  => 'check',
				],
				[
					'title' => 'Unknown icon',
					'text'  => 'Text',
					'icon'  => 'check',
				],
			],
			Settings::front_value_items()
		);
	}

	public function test_front_promo_title_sanitizes_a_valid_value(): void {
		self::assertSame( 'Summer sale', Settings::sanitize_front_promo_title( '  Summer sale  ' ) );
	}

	public function test_front_promo_title_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_front_promo_title( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_front_promo_title( new \stdClass() ) );
	}

	public function test_front_promo_title_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( 'Summer sale' );
		self::assertSame( 'Summer sale', Settings::front_promo_title() );
	}

	public function test_front_promo_text_sanitizes_a_valid_value_and_keeps_line_breaks(): void {
		self::assertSame( "Line one\nLine two", Settings::sanitize_front_promo_text( "Line one\nLine two " ) );
	}

	public function test_front_promo_text_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_front_promo_text( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_front_promo_text( new \stdClass() ) );
	}

	public function test_front_promo_text_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( 'Body copy' );
		self::assertSame( 'Body copy', Settings::front_promo_text() );
	}

	public function test_front_promo_cta_label_sanitizes_a_valid_value(): void {
		self::assertSame( 'Shop now', Settings::sanitize_front_promo_cta_label( '  Shop now  ' ) );
	}

	public function test_front_promo_cta_label_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_front_promo_cta_label( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_front_promo_cta_label( new \stdClass() ) );
	}

	public function test_front_promo_cta_label_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( 'Shop now' );
		self::assertSame( 'Shop now', Settings::front_promo_cta_label() );
	}

	public function test_front_promo_cta_url_accepts_a_valid_url(): void {
		self::assertSame( 'https://example.com/sale', Settings::sanitize_front_promo_cta_url( 'https://example.com/sale' ) );
	}

	/**
	 * The one setting in this class that lands directly in an href — pinned
	 * with the exact hostile payload named in the task, not just "some
	 * disallowed scheme".
	 */
	public function test_front_promo_cta_url_rejects_a_javascript_scheme(): void {
		self::assertSame( '', Settings::sanitize_front_promo_cta_url( 'javascript:alert(1)' ) );
	}

	public function test_front_promo_cta_url_rejects_non_string_input(): void {
		self::assertSame( '', Settings::sanitize_front_promo_cta_url( [ 'x' ] ) );
		self::assertSame( '', Settings::sanitize_front_promo_cta_url( new \stdClass() ) );
	}

	public function test_front_promo_cta_url_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( 'https://example.com' );
		self::assertSame( 'https://example.com', Settings::front_promo_cta_url() );
	}

	public function test_front_promo_image_accepts_a_numeric_attachment_id(): void {
		self::assertSame( 42, Settings::sanitize_front_promo_image( '42' ) );
		self::assertSame( 42, Settings::sanitize_front_promo_image( 42 ) );
		self::assertSame( 42, Settings::sanitize_front_promo_image( 42.9 ) );
	}

	public function test_front_promo_image_rejects_non_numeric_input(): void {
		self::assertSame( 0, Settings::sanitize_front_promo_image( [ 'x' ] ) );
		self::assertSame( 0, Settings::sanitize_front_promo_image( new \stdClass() ) );
		self::assertSame( 0, Settings::sanitize_front_promo_image( 'not-a-number' ) );
		self::assertSame( 0, Settings::sanitize_front_promo_image( false ) );
	}

	public function test_front_promo_image_resolver_reads_the_theme_mod_through_the_sanitizer(): void {
		$this->stub_theme_mod( '7' );
		self::assertSame( 7, Settings::front_promo_image() );
	}
}
