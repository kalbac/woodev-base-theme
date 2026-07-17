<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Woodev\Theme\Base\Setup;

final class SetupTest extends TestCase {

	public function test_register_hooks_after_setup_theme(): void {
		$setup = new Setup();
		$setup->register();
		self::assertNotFalse( \has_action( 'after_setup_theme', [ $setup, 'setup' ] ) );
	}

	public function test_setup_declares_theme_supports_and_menu(): void {
		Functions\expect( 'add_theme_support' )->times( 4 );
		Functions\expect( 'load_theme_textdomain' )
			->once()
			->with( 'woodev-base-theme', \Mockery::type( 'string' ) );
		Functions\expect( 'register_nav_menus' )->once();
		Functions\expect( 'get_template_directory' )->andReturn( '/theme' );
		Functions\when( '__' )->returnArg();

		( new Setup() )->setup();
	}
}
