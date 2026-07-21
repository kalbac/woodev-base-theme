<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Woodev\Theme\Base\Assets;

final class AssetsTest extends TestCase {

	private const MANIFEST = [
		'src/js/app.js'          => [
			'file' => 'assets/app-B3xY.js',
			'css'  => [ 'assets/app-D4zQ.css' ],
		],
		'src/css/packs/vega.css' => [ 'file' => 'assets/style-vega-A1bC.css' ],
	];

	public function test_entry_file_resolves_hashed_file(): void {
		self::assertSame( 'assets/app-B3xY.js', Assets::entry_file( self::MANIFEST, 'src/js/app.js' ) );
		self::assertSame( 'assets/style-vega-A1bC.css', Assets::entry_file( self::MANIFEST, 'src/css/packs/vega.css' ) );
	}

	public function test_entry_file_returns_null_for_unknown_entry(): void {
		self::assertNull( Assets::entry_file( self::MANIFEST, 'src/js/missing.js' ) );
	}

	public function test_entry_css_lists_imported_css(): void {
		self::assertSame( [ 'assets/app-D4zQ.css' ], Assets::entry_css( self::MANIFEST, 'src/js/app.js' ) );
		self::assertSame( [], Assets::entry_css( self::MANIFEST, 'src/css/packs/vega.css' ) );
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
	// calls wp_trigger_error() before returning null, which surfaces as a PHP
	// notice on the front end. read_manifest() must therefore never reach the
	// decode for a path that is not a file.
	public function test_read_manifest_never_decodes_an_absent_manifest(): void {
		Functions\expect( 'wp_json_file_decode' )->never();

		self::assertSame( [], Assets::read_manifest( __DIR__ . '/nonexistent/manifest.json' ) );
	}

	// is_file() is true for a file we cannot open, and core's wp_json_file_decode()
	// hands the path straight to file_get_contents() with no readability check of
	// its own — which emits a PHP warning. So existence alone is not enough to
	// keep the decode silent.
	public function test_read_manifest_never_decodes_an_unreadable_manifest(): void {
		$path = \tempnam( \sys_get_temp_dir(), 'wtb-manifest-' );
		self::assertIsString( $path );

		try {
			\chmod( $path, 0000 );

			// Windows ignores POSIX permission bits, and root reads anything —
			// the condition under test cannot exist here, so asserting would be
			// theatre. CI (ubuntu, non-root) is where this runs for real.
			if ( \is_readable( $path ) ) {
				self::markTestSkipped( 'Cannot make a file unreadable on this platform/user.' );
			}

			Functions\expect( 'wp_json_file_decode' )->never();

			self::assertSame( [], Assets::read_manifest( $path ) );
		} finally {
			\chmod( $path, 0644 );
			\unlink( $path );
		}
	}

	// Any real file will do — the decode itself is mocked; is_file() is not.
	public function test_read_manifest_returns_the_decoded_manifest(): void {
		Functions\expect( 'wp_json_file_decode' )
			->once()
			->andReturn( self::MANIFEST );

		self::assertSame( self::MANIFEST, Assets::read_manifest( __FILE__ ) );
	}

	// WOODEV_BASE_DEV can never be undefined once set, so these branches only stay
	// testable in a process of their own. The CSS entry is load-bearing: Vite
	// declares it a separate Rollup input, so app.js does not import it and the
	// dev server would otherwise render the theme with no Tailwind/Basecoat/tokens.
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_dev_mode_enqueues_the_default_pack_from_the_dev_server(): void {
		\define( 'WOODEV_BASE_DEV', true );
		Functions\when( 'get_theme_mod' )->alias( static fn( string $key, $default = false ) => $default );

		$modules = [];
		Functions\when( 'wp_enqueue_script_module' )->alias(
			static function ( string $handle, string $src ) use ( &$modules ): void {
				$modules[ $handle ] = $src;
			}
		);
		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_json_file_decode' )->never();

		( new Assets() )->enqueue();

		self::assertSame(
			[
				'woodev-base-vite-client' => 'http://localhost:5173/@vite/client',
				'woodev-base-style'       => 'http://localhost:5173/src/css/packs/vega.css',
				'woodev-base-app'         => 'http://localhost:5173/src/js/app.js',
			],
			$modules
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_dev_mode_enqueues_the_pack_the_theme_mod_selects(): void {
		\define( 'WOODEV_BASE_DEV', true );
		Functions\when( 'get_theme_mod' )->justReturn( 'nova' );

		$modules = [];
		Functions\when( 'wp_enqueue_script_module' )->alias(
			static function ( string $handle, string $src ) use ( &$modules ): void {
				$modules[ $handle ] = $src;
			}
		);

		( new Assets() )->enqueue();

		self::assertSame(
			'http://localhost:5173/src/css/packs/nova.css',
			$modules['woodev-base-style']
		);
	}
}
