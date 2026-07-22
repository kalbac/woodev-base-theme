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
		$this->stub_mods( [ 'color_scheme_default' => 'dark' ] );

		self::assertSame( 'lang="en-US" class="dark"', ( new Scheme() )->add_html_class( 'lang="en-US"' ) );
	}

	public function test_system_leaves_language_attributes_untouched(): void {
		$this->stub_mods( [ 'color_scheme_default' => 'system' ] );

		self::assertSame( 'lang="en-US"', ( new Scheme() )->add_html_class( 'lang="en-US"' ) );
	}
}
