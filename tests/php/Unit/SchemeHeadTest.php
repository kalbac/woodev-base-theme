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
	 * Three review rounds killed the merge-into-the-existing-attribute version
	 * of this: every regex that looked right was wrong somewhere (a word
	 * boundary matched `data-class=`; unquoted, spaced and uppercase forms were
	 * missed; `str_replace()` rewrote identical text inside other attributes; a
	 * match inside a quoted VALUE won). The function now declines to touch a
	 * string that mentions a class at all.
	 *
	 * What this pins is the SAFETY property, which is the one that matters: we
	 * never emit a second class attribute and never rewrite anyone else's.
	 */
	public function test_an_existing_class_attribute_is_left_alone(): void {
		$filter = static function ( string $input ): string {
			return ( new Scheme() )->add_html_class( $input );
		};

		Functions\when( 'get_theme_mod' )->justReturn( 'dark' );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'is_embed' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( false );

		// Every one of these was a way the old merge corrupted the output.
		foreach (
			[
				'lang="en-US" class="no-js"',
				"lang='en' class='no-js'",
				'lang="en" class=no-js',
				'lang="en" class = "no-js"',
				'lang="en" CLASS="no-js"',
				'lang="en" data-class="card"',
				'lang="en" data-note="foo class=bar" class="real"',
				"lang=\"en\" class=\"foo\nbar\"",
				'lang="en" class',
			] as $input
		) {
			$output = $filter( $input );

			self::assertSame( $input, $output, 'a string that mentions class must come back untouched' );
			self::assertSame(
				substr_count( $input, 'class' ),
				substr_count( $output, 'class' ),
				'no class token may be added or removed'
			);
		}

		// The plain case — nobody else has touched it — still gets the class.
		self::assertSame( 'lang="en-US" class="dark"', $filter( 'lang="en-US"' ) );
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
