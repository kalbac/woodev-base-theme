<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Setup;

final class SetupTest extends TestCase {

	public function test_register_hooks_after_setup_theme(): void {
		$setup = new Setup();
		$setup->register();
		self::assertNotFalse( \has_action( 'after_setup_theme', [ $setup, 'setup' ] ) );
	}

	public function test_setup_declares_theme_supports_and_menu(): void {
		// Collect the calls rather than counting them: a bare times( 4 ) is
		// satisfied by any four features with any arguments, so gutting the html5
		// list — or swapping a feature for an unrelated one — stayed green.
		// Integration cannot cover html5 either, because core's tear_down()
		// strips it (docs/gotchas/wp-test-suite-removes-html5-support.md), which
		// makes this assertion the only thing standing behind it.
		$supports = [];
		Functions\when( 'add_theme_support' )->alias(
			static function ( string $feature, ...$args ) use ( &$supports ): void {
				$supports[ $feature ] = $args;
			}
		);

		Functions\expect( 'load_theme_textdomain' )
			->once()
			// The real path, not Mockery::type( 'string' ): the point of the
			// assertion is that translations are looked for in /languages, and
			// any string satisfied that while pointing anywhere at all.
			->with( 'woodev-base-theme', '/theme/languages' );
		Functions\expect( 'register_nav_menus' )
			->once()
			->with( [ 'primary' => 'Primary Menu' ] );
		Functions\expect( 'get_template_directory' )->andReturn( '/theme' );
		Functions\when( '__' )->returnArg();

		( new Setup() )->setup();

		self::assertSame(
			[
				'title-tag'            => [],
				'post-thumbnails'      => [],
				'automatic-feed-links' => [],
				'html5'                => [ [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] ],
			],
			$supports
		);
	}
}
