<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use function Woodev\Theme\Base\class_path;

final class AutoloadTest extends TestCase {

	public function test_maps_namespaced_class_to_inc_path(): void {
		$path = class_path( 'Woodev\\Theme\\Base\\Customizer\\Colors' );
		self::assertNotNull( $path );
		self::assertStringEndsWith( 'inc/Customizer/Colors.php', \str_replace( '\\', '/', $path ) );
	}

	public function test_returns_null_for_foreign_namespace(): void {
		self::assertNull( class_path( 'OtherVendor\\Thing' ) );
	}

	public function test_theme_class_is_autoloadable(): void {
		self::assertTrue( \class_exists( \Woodev\Theme\Base\Theme::class ) );
	}
}
