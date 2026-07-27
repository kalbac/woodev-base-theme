<?php
/**
 * Every shipped PHP file refuses direct access.
 *
 * A theme file reached over HTTP runs outside WordPress: constants are undefined,
 * functions are missing, and the fatal that follows prints a path. The one-line guard
 * costs nothing and is what a wp.org reviewer expects to see.
 *
 * This walks the shipped theme rather than listing files, so a new file added without
 * the guard fails here instead of at review. That is the whole point — the audit that
 * found the gap looked at 45 files by hand exactly once.
 *
 * @package Woodev\Theme\Base\Tests\Unit
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DirectAccessGuardTest extends PHPUnitTestCase {

	private const THEME_DIR = __DIR__ . '/../../../woodev-base-theme';

	/**
	 * @return list<string> Theme-relative paths of every shipped PHP file.
	 */
	private function shipped_php_files(): array {
		$root  = realpath( self::THEME_DIR );
		$files = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);

		/** @var SplFileInfo $file */
		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$path = str_replace( '\\', '/', $file->getPathname() );

			// assets/dist is build output, not source, and is not in git.
			if ( str_contains( $path, '/assets/dist/' ) ) {
				continue;
			}

			$files[] = ltrim( substr( $path, strlen( str_replace( '\\', '/', $root ) ) ), '/' );
		}

		sort( $files );

		return $files;
	}

	public function test_the_walker_actually_finds_the_theme(): void {
		// Guards this test rather than the theme: a typo'd path would make every
		// assertion below iterate an empty list and pass, which is the failure mode
		// this project keeps meeting.
		$files = $this->shipped_php_files();

		self::assertGreaterThan( 30, \count( $files ), 'Found suspiciously few PHP files — is the path right?' );
		self::assertContains( 'functions.php', $files );
		self::assertContains( 'inc/Theme.php', $files );
	}

	/**
	 * The executable head of a file: comments and docblocks removed, whitespace
	 * collapsed. Counting raw characters instead was the first version of this test and
	 * it was wrong — `woocommerce/content-product.php` carries a 22-line upstream
	 * docblock, so its perfectly good guard sat past an 800-character window and the
	 * test reported a defect that did not exist.
	 */
	private function executable_head( string $source ): string {
		$stripped = preg_replace( '#/\*.*?\*/#s', '', $source ) ?? $source;
		$stripped = preg_replace( '#^\s*//.*$#m', '', $stripped ) ?? $stripped;
		$stripped = preg_replace( '#\s+#', ' ', $stripped ) ?? $stripped;

		return substr( trim( $stripped ), 0, 400 );
	}

	public function test_every_shipped_php_file_refuses_direct_access(): void {
		$unguarded = [];

		foreach ( $this->shipped_php_files() as $relative ) {
			$source = (string) file_get_contents( self::THEME_DIR . '/' . $relative );

			// The guard must come before anything executes. Grepping the whole file
			// would pass on a guard sitting uselessly at the bottom.
			if ( ! str_contains( $this->executable_head( $source ), 'ABSPATH' ) ) {
				$unguarded[] = $relative;
			}
		}

		self::assertSame(
			[],
			$unguarded,
			"These files can be requested directly:\n  " . implode( "\n  ", $unguarded )
		);
	}
}
