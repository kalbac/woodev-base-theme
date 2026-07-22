<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Templates;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Templates\Layout;
use Woodev\Theme\Base\Tests\Unit\TestCase;

final class LayoutTest extends TestCase {

	public function test_header_variant_defaults_to_inline(): void {
		Functions\when( 'get_theme_mod' )->alias( static fn( string $key, $default = false ) => $default );

		self::assertSame( 'inline', Layout::header_variant() );
	}

	public function test_header_variant_reads_the_theme_mod(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'centered' );

		self::assertSame( 'centered', Layout::header_variant() );
	}

	/**
	 * An unknown stored value must not reach get_template_part(): a stale or
	 * hand-edited theme_mod would otherwise ask for a part file that does not
	 * exist and render nothing at all.
	 */
	public function test_an_unknown_header_variant_falls_back_to_the_default(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'does-not-exist' );

		self::assertSame( 'inline', Layout::header_variant() );
	}

	public function test_footer_variant_defaults_to_simple_and_validates(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'nonsense' );

		self::assertSame( 'simple', Layout::footer_variant() );
	}

	public function test_sidebar_is_off_by_default(): void {
		Functions\when( 'get_theme_mod' )->alias( static fn( string $key, $default = false ) => $default );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );
		Functions\when( 'is_archive' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );

		self::assertFalse( Layout::has_sidebar() );
	}

	/**
	 * A sidebar setting of 'right' with an empty widget area would render an
	 * empty column and shrink the content for nothing.
	 */
	public function test_sidebar_requires_widgets_to_be_present(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( false );
		Functions\when( 'is_home' )->justReturn( false );
		Functions\when( 'is_archive' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );

		self::assertFalse( Layout::has_sidebar() );
	}

	/**
	 * Spec §7: the sidebar applies to blog/archive/single contexts. A static
	 * page is a layout the author controls with blocks; a sidebar bolted onto it
	 * fights the page's own design.
	 */
	public function test_a_static_page_never_gets_the_sidebar(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );
		Functions\when( 'is_archive' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( false );

		self::assertFalse( Layout::has_sidebar() );
	}

	public function test_sidebar_shows_on_a_single_post_when_enabled_and_filled(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );
		Functions\when( 'is_archive' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );

		self::assertTrue( Layout::has_sidebar() );
	}

	/**
	 * Calling get_theme_mod() returns mixed and the value can be reshaped by a
	 * `theme_mod_header_variant` filter or a half-migrated option. Casting an
	 * object without __toString() to string throws Error — and this runs inside
	 * header.php, i.e. on every front-end request. Fail closed instead.
	 */
	public function test_a_non_string_header_variant_falls_back_instead_of_fataling(): void {
		Functions\when( 'get_theme_mod' )->justReturn( new \stdClass() );

		self::assertSame( 'inline', Layout::header_variant() );
	}

	public function test_an_array_footer_variant_falls_back_instead_of_warning(): void {
		Functions\when( 'get_theme_mod' )->justReturn( [ 'columns' ] );

		self::assertSame( 'simple', Layout::footer_variant() );
	}

	public function test_sanitizers_are_reusable_by_the_customizer(): void {
		self::assertSame( 'centered', Layout::sanitize_header_variant( 'centered' ) );
		self::assertSame( 'inline', Layout::sanitize_header_variant( 'bogus' ) );
		self::assertSame( 'columns', Layout::sanitize_footer_variant( 'columns' ) );
		self::assertSame( 'simple', Layout::sanitize_footer_variant( null ) );
		self::assertSame( 'right', Layout::sanitize_sidebar_position( 'right' ) );
		self::assertSame( 'none', Layout::sanitize_sidebar_position( 'left' ) );
	}

	/**
	 * Spec §7 lists blog, archive and single. The old `! is_page()` was broader —
	 * it also caught 404s, attachments and any future singular type.
	 */
	public function test_the_404_template_never_gets_the_sidebar(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );
		Functions\when( 'is_archive' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( false );

		self::assertFalse( Layout::has_sidebar() );
	}

	/**
	 * Codex P2 on the M1-04 diff. is_single() is true for attachment queries
	 * and for every public custom post type, so the "positive allow-list" was
	 * still letting in exactly what it claimed to exclude. is_singular( 'post' )
	 * is what actually means "a blog post".
	 */
	public function test_an_attachment_or_custom_post_type_never_gets_the_sidebar(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );
		Functions\when( 'is_archive' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( false );

		// is_singular( 'post' ) is false for an attachment and for a CPT single,
		// while the old is_single() was true for both.
		Functions\when( 'is_singular' )->alias(
			static fn( $type = '' ) => 'post' !== $type
		);

		self::assertFalse( Layout::has_sidebar() );
	}

	public function test_the_search_results_list_gets_the_sidebar(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_home' )->justReturn( false );
		Functions\when( 'is_archive' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( true );
		Functions\when( 'is_singular' )->justReturn( false );

		self::assertTrue( Layout::has_sidebar() );
	}
}
