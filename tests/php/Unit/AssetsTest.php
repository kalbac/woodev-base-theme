<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Assets;

final class AssetsTest extends TestCase {

	private const MANIFEST = [
		'src/js/app.js'   => [
			'file' => 'assets/app-B3xY.js',
			'css'  => [ 'assets/app-D4zQ.css' ],
		],
		'src/css/app.css' => [ 'file' => 'assets/style-A1bC.css' ],
	];

	public function test_entry_file_resolves_hashed_file(): void {
		self::assertSame( 'assets/app-B3xY.js', Assets::entry_file( self::MANIFEST, 'src/js/app.js' ) );
		self::assertSame( 'assets/style-A1bC.css', Assets::entry_file( self::MANIFEST, 'src/css/app.css' ) );
	}

	public function test_entry_file_returns_null_for_unknown_entry(): void {
		self::assertNull( Assets::entry_file( self::MANIFEST, 'src/js/missing.js' ) );
	}

	public function test_entry_css_lists_imported_css(): void {
		self::assertSame( [ 'assets/app-D4zQ.css' ], Assets::entry_css( self::MANIFEST, 'src/js/app.js' ) );
		self::assertSame( [], Assets::entry_css( self::MANIFEST, 'src/css/app.css' ) );
	}

	// wp_json_file_decode() returns null for a missing or corrupt file. Callers
	// index into the result, so read_manifest() must absorb that into an array.
	public function test_read_manifest_returns_empty_array_when_file_cannot_be_decoded(): void {
		Functions\expect( 'wp_json_file_decode' )
			->once()
			->with( '/nonexistent/manifest.json', [ 'associative' => true ] )
			->andReturn( null );

		self::assertSame( [], Assets::read_manifest( '/nonexistent/manifest.json' ) );
	}

	public function test_read_manifest_returns_the_decoded_manifest(): void {
		Functions\expect( 'wp_json_file_decode' )
			->once()
			->andReturn( self::MANIFEST );

		self::assertSame( self::MANIFEST, Assets::read_manifest( '/theme/manifest.json' ) );
	}
}
