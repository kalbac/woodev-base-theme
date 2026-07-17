<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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

	// wp_json_file_decode() returns null for a corrupt file. Callers index into
	// the result, so read_manifest() must absorb that into an array.
	public function test_read_manifest_returns_empty_array_when_file_cannot_be_decoded(): void {
		Functions\expect( 'wp_json_file_decode' )
			->once()
			->with( __FILE__, [ 'associative' => true ] )
			->andReturn( null );

		self::assertSame( [], Assets::read_manifest( __FILE__ ) );
	}

	// An absent manifest is the normal state of a fresh checkout (assets/dist is
	// gitignored), so it must stay silent. wp_json_file_decode() does NOT: it
	// calls wp_trigger_error() before returning null, which is a PHP warning on
	// the front end. read_manifest() must therefore never reach the decode for a
	// path that is not a file.
	public function test_read_manifest_never_decodes_an_absent_manifest(): void {
		Functions\expect( 'wp_json_file_decode' )->never();

		self::assertSame( [], Assets::read_manifest( __DIR__ . '/nonexistent/manifest.json' ) );
	}

	// Any real file will do — the decode itself is mocked; is_file() is not.
	public function test_read_manifest_returns_the_decoded_manifest(): void {
		Functions\expect( 'wp_json_file_decode' )
			->once()
			->andReturn( self::MANIFEST );

		self::assertSame( self::MANIFEST, Assets::read_manifest( __FILE__ ) );
	}

	// WOODEV_BASE_DEV can never be undefined once set, so this branch only stays
	// testable in a process of its own.
	//
	// The CSS entry is the load-bearing one: Vite declares it as a separate
	// Rollup input, so app.js does not import it and the dev server would
	// otherwise render the theme with no Tailwind, Basecoat or tokens. Vite
	// serves /src/css/app.css as a JS module (text/javascript) that injects the
	// style and carries HMR — hence a script module, not wp_enqueue_style.
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_dev_mode_enqueues_vite_client_css_and_js_from_the_dev_server(): void {
		\define( 'WOODEV_BASE_DEV', true );

		$modules = [];
		Functions\when( 'wp_enqueue_script_module' )->alias(
			static function ( string $handle, string $src ) use ( &$modules ): void {
				$modules[ $handle ] = $src;
			}
		);

		// Dev mode must not touch the dist build at all.
		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_json_file_decode' )->never();

		( new Assets() )->enqueue();

		self::assertSame(
			[
				'woodev-base-vite-client' => 'http://localhost:5173/@vite/client',
				'woodev-base-style'       => 'http://localhost:5173/src/css/app.css',
				'woodev-base-app'         => 'http://localhost:5173/src/js/app.js',
			],
			$modules
		);
	}
}
