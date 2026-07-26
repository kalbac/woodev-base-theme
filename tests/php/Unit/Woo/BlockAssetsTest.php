<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\BlockAssets;

final class BlockAssetsTest extends TestCase {

	/**
	 * The defining difference from Woo\Assets (AssetsTest): this bundle is
	 * conditional, so a page carrying the Cart block must enqueue it.
	 */
	public function test_enqueues_the_bundle_when_the_page_has_the_cart_block(): void {
		$styles = $this->enqueue_with(
			[ 'src/css/woo-blocks.css' => [ 'file' => 'assets/woo-blocks-Abc123.css' ] ],
			[ 'woocommerce/cart' ]
		);

		self::assertSame(
			[ 'woodev-base-woo-blocks' => 'https://example.test/wp-content/themes/woodev-base-theme/assets/dist/assets/woo-blocks-Abc123.css' ],
			$styles
		);
	}

	/**
	 * Same contract, the other block: Cart and Checkout are independent
	 * `has_block()` checks, either one is sufficient.
	 */
	public function test_enqueues_the_bundle_when_the_page_has_the_checkout_block(): void {
		$styles = $this->enqueue_with(
			[ 'src/css/woo-blocks.css' => [ 'file' => 'assets/woo-blocks-Abc123.css' ] ],
			[ 'woocommerce/checkout' ]
		);

		self::assertSame(
			[ 'woodev-base-woo-blocks' => 'https://example.test/wp-content/themes/woodev-base-theme/assets/dist/assets/woo-blocks-Abc123.css' ],
			$styles
		);
	}

	/**
	 * The opposite of AssetsTest's "no Woo context" test: THIS bundle must
	 * NOT load when neither block is present, because it is deliberately
	 * conditional (see BlockAssets::enqueue()'s docblock for why). The
	 * manifest reader is asserted to never even run — the early return must
	 * happen before any manifest I/O, not merely resolve to no <link>.
	 */
	public function test_does_not_enqueue_when_neither_block_is_present(): void {
		Functions\when( 'has_block' )->justReturn( false );
		Functions\expect( 'wp_json_file_decode' )->never();
		Functions\expect( 'wp_enqueue_style' )->never();

		( new BlockAssets() )->enqueue();
	}

	/**
	 * An absent manifest is the normal state of a fresh checkout (assets/dist
	 * is gitignored). It must degrade to "enqueue nothing" — never a fatal —
	 * exactly like Woo\Assets::enqueue() and the base theme's own
	 * Assets::enqueue(). The block IS present here (has_block() true for
	 * Cart), so this proves the manifest-absent guard, not the block-absent
	 * one already covered above.
	 */
	public function test_an_absent_manifest_enqueues_nothing_and_does_not_fatal(): void {
		Functions\when( 'has_block' )->alias( static fn ( string $name ): bool => 'woocommerce/cart' === $name );

		$root = \sys_get_temp_dir() . '/wtb-woo-blocks-' . \uniqid();

		Functions\when( 'get_template_directory' )->justReturn( $root );
		Functions\when( 'get_template_directory_uri' )->justReturn( 'https://example.test/wp-content/themes/woodev-base-theme' );
		Functions\expect( 'wp_json_file_decode' )->never();
		Functions\expect( 'wp_enqueue_style' )->never();

		( new BlockAssets() )->enqueue();
	}

	/**
	 * Sets up has_block() to report the given block names present, a real,
	 * readable temp manifest decoding to the given content, runs
	 * BlockAssets::enqueue(), and returns the styles that were enqueued.
	 *
	 * @param array<string, array{file: string, css?: list<string>}> $manifest
	 * @param list<string>                                           $present_blocks
	 * @return array<string, string>
	 */
	private function enqueue_with( array $manifest, array $present_blocks ): array {
		Functions\when( 'has_block' )->alias(
			static fn ( string $name ): bool => \in_array( $name, $present_blocks, true )
		);

		$root = \sys_get_temp_dir() . '/wtb-woo-blocks-' . \uniqid();
		\mkdir( $root . '/assets/dist/.vite', 0777, true );
		\file_put_contents( $root . '/assets/dist/.vite/manifest.json', '{}' );

		try {
			Functions\when( 'get_template_directory' )->justReturn( $root );
			Functions\when( 'get_template_directory_uri' )->justReturn( 'https://example.test/wp-content/themes/woodev-base-theme' );
			Functions\expect( 'wp_json_file_decode' )->once()->andReturn( $manifest );

			$styles = [];
			Functions\when( 'wp_enqueue_style' )->alias(
				static function ( string $handle, string $src ) use ( &$styles ): void {
					$styles[ $handle ] = $src;
				}
			);

			( new BlockAssets() )->enqueue();

			return $styles;
		} finally {
			\unlink( $root . '/assets/dist/.vite/manifest.json' );
			\rmdir( $root . '/assets/dist/.vite' );
			\rmdir( $root . '/assets/dist' );
			\rmdir( $root . '/assets' );
			\rmdir( $root );
		}
	}
}
