<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Icons;

final class IconsTest extends TestCase {

	/**
	 * Points the helper at the real committed icons rather than a fixture: the
	 * files it must parse are the files that ship, and a fixture would let the
	 * two drift.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 3 ) . '/woodev-base-theme' );
		Functions\when( 'esc_attr' )->returnArg();
	}

	/**
	 * @dataProvider provide_rejected_names
	 */
	public function test_rejects_names_that_are_not_plain_icon_slugs( string $name ): void {
		self::assertSame( '', Icons::get( $name ) );
	}

	public static function provide_rejected_names(): array {
		return [
			'traversal'          => [ '../../../wp-config' ],
			'traversal encoded'  => [ '..%2Fwp-config' ],
			'absolute path'      => [ '/etc/passwd' ],
			'nested path'        => [ 'sub/sun' ],
			'null byte'          => [ "sun\0.php" ],
			'uppercase'          => [ 'Sun' ],
			'leading dash'       => [ '-sun' ],
			'trailing dash'      => [ 'sun-' ],
			'double dash'        => [ 'sun--moon' ],
			'empty'              => [ '' ],
			'unknown but valid'  => [ 'definitely-not-an-icon' ],
		];
	}
}
