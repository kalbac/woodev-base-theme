<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Scheme;

final class SchemeTest extends TestCase {

	public function test_the_default_scheme_is_system_and_validates(): void {
		self::assertSame( 'system', Scheme::sanitize_default( 'system' ) );
		self::assertSame( 'dark', Scheme::sanitize_default( 'dark' ) );
		self::assertSame( 'system', Scheme::sanitize_default( 'sepia' ) );
		self::assertSame( 'system', Scheme::sanitize_default( new \stdClass() ) );
		self::assertSame( 'system', Scheme::sanitize_default( [ 'dark' ] ) );
	}

	public function test_the_toggle_is_a_real_boolean(): void {
		self::assertTrue( Scheme::sanitize_toggle( true ) );
		self::assertTrue( Scheme::sanitize_toggle( '1' ) );
		self::assertFalse( Scheme::sanitize_toggle( '' ) );
		self::assertFalse( Scheme::sanitize_toggle( '0' ) );
		self::assertFalse( Scheme::sanitize_toggle( new \stdClass() ) );
	}

	/**
	 * `system` sets NO class on purpose: that is what lets the generated
	 * prefers-color-scheme block decide for a visitor with JS disabled. An
	 * explicit admin choice IS a class, so it survives with JS off too.
	 */
	public function test_only_an_explicit_default_becomes_a_class(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 'system' );
		self::assertSame( '', Scheme::html_class() );

		Functions\when( 'get_theme_mod' )->justReturn( 'dark' );
		self::assertSame( 'dark', Scheme::html_class() );

		Functions\when( 'get_theme_mod' )->justReturn( 'light' );
		self::assertSame( 'light', Scheme::html_class() );
	}
}
