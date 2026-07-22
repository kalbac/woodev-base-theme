<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Scheme;

/**
 * The no-FOUC head script (spec §6, M1-05 Task 3): a synchronous, inline
 * script printed at wp_head priority 1 that refines the server-rendered
 * class from a stored visitor choice before first paint.
 */
final class SchemeHeadTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_json_encode' )->alias( static fn( $value ) => \json_encode( $value ) );
		Functions\when( 'esc_attr' )->alias( static fn( $value ) => \htmlspecialchars( (string) $value, ENT_QUOTES ) );
	}

	/**
	 * @param array<string, mixed> $mods Stored theme_mods.
	 */
	private function stub_mods( array $mods ): void {
		Functions\when( 'get_theme_mod' )->alias(
			static fn( string $key, $default = false ) => $mods[ $key ] ?? $default
		);
	}

	public function test_it_hooks_language_attributes_and_wp_head_at_priority_one(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'language_attributes', \Mockery::type( 'array' ) );

		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_head', \Mockery::type( 'array' ), 1 );

		( new Scheme() )->register();
	}

	public function test_the_default_scheme_comes_through_wp_json_encode_quoted(): void {
		$this->stub_mods(
			[
				'color_scheme_default' => 'dark',
				'color_scheme_toggle'  => true,
			]
		);

		self::assertStringContainsString( '"dark"', Scheme::build_head_script() );
	}

	public function test_toggle_on_reads_local_storage(): void {
		$this->stub_mods(
			[
				'color_scheme_default' => 'system',
				'color_scheme_toggle'  => true,
			]
		);

		self::assertStringContainsString( 'localStorage', Scheme::build_head_script() );
	}

	/**
	 * With the toggle off there is no stored visitor choice to honour at all,
	 * so the script must not even mention localStorage — not merely guard the
	 * read behind a false runtime condition.
	 */
	public function test_toggle_off_omits_any_localstorage_read(): void {
		$this->stub_mods(
			[
				'color_scheme_default' => 'light',
				'color_scheme_toggle'  => false,
			]
		);

		self::assertStringNotContainsString( 'localStorage', Scheme::build_head_script() );
	}

	public function test_the_printed_markup_is_wrapped_in_a_script_tag(): void {
		$this->stub_mods(
			[
				'color_scheme_default' => 'system',
				'color_scheme_toggle'  => true,
			]
		);

		\ob_start();
		( new Scheme() )->print_head_script();
		$markup = \ob_get_clean();

		self::assertStringContainsString( '<script', $markup );
		self::assertStringContainsString( '</script>', $markup );
	}

	public function test_an_explicit_scheme_appends_a_class_to_language_attributes(): void {
		Functions\when( 'is_embed' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( false );
		$this->stub_mods( [ 'color_scheme_default' => 'dark' ] );

		self::assertSame( 'lang="en-US" class="dark"', ( new Scheme() )->add_html_class( 'lang="en-US"' ) );
	}

	public function test_system_leaves_language_attributes_untouched(): void {
		Functions\when( 'is_embed' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( false );
		$this->stub_mods( [ 'color_scheme_default' => 'system' ] );

		self::assertSame( 'lang="en-US"', ( new Scheme() )->add_html_class( 'lang="en-US"' ) );
	}

	/**
	 * Adversarial-review P2. language_attributes() is not ours alone: core's
	 * oEmbed header template opens the html element with language_attributes()
	 * followed by a literal class="no-js", so appending a class yields TWO
	 * class attributes on
	 * one element — invalid markup, and browsers keep the first, dropping core's
	 * own marker. Verified against wp-includes/theme-compat/header-embed.php.
	 */
	public function test_the_class_is_not_appended_in_an_embed_or_in_admin(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'dark' );
		Functions\when( 'esc_attr' )->returnArg();

		Functions\when( 'is_embed' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( false );
		self::assertSame( 'lang="en-US"', ( new Scheme() )->add_html_class( 'lang="en-US"' ) );

		Functions\when( 'is_embed' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( true );
		self::assertSame( 'lang="en-US"', ( new Scheme() )->add_html_class( 'lang="en-US"' ) );

		Functions\when( 'is_embed' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( false );
		self::assertSame(
			'lang="en-US" class="dark"',
			( new Scheme() )->add_html_class( 'lang="en-US"' )
		);
	}

	/**
	 * Codex P2. The embed guard above handles core's own template, but we are
	 * not the only filter on this hook — a plugin that already added a class
	 * would leave two class attributes on one element, and browsers keep the
	 * FIRST, so ours would be the one silently dropped.
	 */
	public function test_an_existing_class_attribute_is_merged_not_duplicated(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'dark' );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'is_embed' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( false );

		$output = ( new Scheme() )->add_html_class( 'lang="en-US" class="no-js"' );

		self::assertSame( 'lang="en-US" class="no-js dark"', $output );
		self::assertSame( 1, substr_count( $output, 'class=' ), 'two class attributes is invalid HTML' );

		// Single quotes are equally legal in the attribute WordPress hands us.
		self::assertSame(
			"lang='en-US' class=\"no-js dark\"",
			( new Scheme() )->add_html_class( "lang='en-US' class='no-js'" )
		);
	}

	/**
	 * Codex P3. Asserting that the string "dark" appears does not prove the
	 * value went through wp_json_encode() — a hand-rolled '"' . $x . '"' emits
	 * byte-identical output for every value sanitize_default() can return, so
	 * the closed set hides the difference.
	 *
	 * Pin the ORIGIN of the string instead: make wp_json_encode() return a
	 * sentinel and require it in the script. Only a value that actually came
	 * from the encoder can carry it, so hand-rolling the quotes turns this red
	 * even though the real output would look the same.
	 */
	public function test_the_default_reaches_the_script_through_wp_json_encode(): void {
		$this->stub_mods( [ 'color_scheme_default' => 'dark' ] );
		Functions\when( 'wp_json_encode' )->justReturn( '"__ENCODED__"' );

		self::assertStringContainsString( '"__ENCODED__"', Scheme::build_head_script() );
	}
}
