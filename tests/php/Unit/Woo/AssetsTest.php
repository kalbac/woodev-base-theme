<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\Assets;

final class AssetsTest extends TestCase {

	/**
	 * The old contract — "off every Woo context, enqueue nothing" — is gone:
	 * `[products]`/`[product_category]`/`[featured_products]` and the Woo
	 * product blocks render our `content-product.php` override on ANY page,
	 * not only on `is_woocommerce()`/`is_cart()`/`is_checkout()`/
	 * `is_account_page()`, so the bundle now has to be there too. This test
	 * proves the replacement: on a page carrying none of those Woo context
	 * tags, the bundle is still enqueued, because a shortcode/block loop can
	 * still render there.
	 */
	public function test_enqueues_the_bundle_on_a_page_with_no_woo_context(): void {
		$styles = $this->enqueue_with_manifest(
			[ 'src/css/woo.css' => [ 'file' => 'assets/woo-Abc123.css' ] ]
		);

		self::assertSame(
			[ 'woodev-base-woo' => 'https://example.test/wp-content/themes/woodev-base-theme/assets/dist/assets/woo-Abc123.css' ],
			$styles
		);
	}

	/**
	 * An absent manifest is the normal state of a fresh checkout
	 * (assets/dist is gitignored). It must degrade to "enqueue nothing" —
	 * never a fatal, never a PHP diagnostic — exactly like the base theme's
	 * own Assets::enqueue() (inc/Assets.php).
	 */
	public function test_an_absent_manifest_enqueues_nothing_and_does_not_fatal(): void {
		$root = \sys_get_temp_dir() . '/wtb-woo-' . \uniqid();

		Functions\when( 'get_template_directory' )->justReturn( $root );
		Functions\when( 'get_template_directory_uri' )->justReturn( 'https://example.test/wp-content/themes/woodev-base-theme' );
		Functions\expect( 'wp_json_file_decode' )->never();
		Functions\expect( 'wp_enqueue_style' )->never();

		( new Assets() )->enqueue();
	}

	/**
	 * Sets up a real, readable temp manifest decoding to the given content,
	 * runs Assets::enqueue(), and returns the styles that were enqueued.
	 *
	 * @param array<string, array{file: string, css?: list<string>}> $manifest
	 * @return array<string, string>
	 */
	private function enqueue_with_manifest( array $manifest ): array {
		$root = \sys_get_temp_dir() . '/wtb-woo-' . \uniqid();
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

			( new Assets() )->enqueue();

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
