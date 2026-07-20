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
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'is_page' )->justReturn( false );

		self::assertFalse( Layout::has_sidebar() );
	}

	/**
	 * A sidebar setting of 'right' with an empty widget area would render an
	 * empty column and shrink the content for nothing.
	 */
	public function test_sidebar_requires_widgets_to_be_present(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'is_page' )->justReturn( false );

		self::assertFalse( Layout::has_sidebar() );
	}

	/**
	 * Spec §7: the sidebar applies to blog/archive/single contexts. A static
	 * page is a layout the author controls with blocks; a sidebar bolted onto it
	 * fights the page's own design.
	 */
	public function test_pages_never_get_the_sidebar(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'is_page' )->justReturn( true );

		self::assertFalse( Layout::has_sidebar() );
	}

	public function test_sidebar_shows_on_a_single_post_when_enabled_and_filled(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'right' );
		Functions\when( 'is_active_sidebar' )->justReturn( true );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'is_page' )->justReturn( false );

		self::assertTrue( Layout::has_sidebar() );
	}
}
