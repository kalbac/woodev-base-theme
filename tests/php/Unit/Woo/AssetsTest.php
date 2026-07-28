<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\Assets;

final class AssetsTest extends TestCase {

	public function test_register_hooks_both_enqueue_callbacks(): void {
		$assets = new Assets();
		$assets->register();

		self::assertNotFalse( \has_action( 'wp_enqueue_scripts', [ $assets, 'enqueue' ] ) );
		self::assertNotFalse( \has_action( 'wp_enqueue_scripts', [ $assets, 'enqueue_storefront_behaviour' ] ) );
	}

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
	 * Stub the three Woo conditional tags the gate reads, so each test states
	 * the whole request context rather than leaving two of them undefined —
	 * the unit suite runs without WooCommerce, so an unstubbed one is a fatal,
	 * not a `false`.
	 *
	 * @param bool $product  is_product()
	 * @param bool $shop     is_shop()
	 * @param bool $taxonomy is_product_taxonomy()
	 */
	private function stub_storefront_context( bool $product, bool $shop, bool $taxonomy ): void {
		Functions\when( 'is_product' )->justReturn( $product );
		Functions\when( 'is_shop' )->justReturn( $shop );
		Functions\when( 'is_product_taxonomy' )->justReturn( $taxonomy );
	}

	/**
	 * `enqueue_storefront_behaviour()` is gated on the storefront pages that
	 * have something for the module to enhance, unlike `enqueue()` above — see
	 * that method's docblock for why the two differ.
	 */
	public function test_enqueue_storefront_behaviour_does_nothing_off_the_storefront(): void {
		$this->stub_storefront_context( false, false, false );
		Functions\expect( 'wp_json_file_decode' )->never();
		Functions\expect( 'wp_enqueue_script_module' )->never();

		( new Assets() )->enqueue_storefront_behaviour();
	}

	public function test_enqueue_storefront_behaviour_enqueues_the_script_on_a_product_page(): void {
		$this->stub_storefront_context( true, false, false );

		$scripts = $this->enqueue_stepper_with_manifest(
			[ 'src/js/woo.js' => [ 'file' => 'assets/woo-Xyz789.js' ] ]
		);

		self::assertSame(
			[ 'woodev-base-woo-js' => 'https://example.test/wp-content/themes/woodev-base-theme/assets/dist/assets/woo-Xyz789.js' ],
			$scripts
		);
	}

	/**
	 * The archive cases, and they are not decoration: this method was
	 * `is_product()`-only when it shipped, which excluded exactly the two pages
	 * the filter rail renders on. The rail's mobile collapse (A14) lives in
	 * this module, so it never ran — the rail stayed expanded above the grid on
	 * every phone, looking like a CSS bug rather than a missing script.
	 *
	 * @return list<array{0: bool, 1: bool}>
	 */
	public static function archive_contexts(): array {
		return [
			'shop page'        => [ true, false ],
			'product taxonomy' => [ false, true ],
		];
	}

	/**
	 * @dataProvider archive_contexts
	 *
	 * @param bool $shop     is_shop()
	 * @param bool $taxonomy is_product_taxonomy()
	 */
	public function test_enqueue_storefront_behaviour_enqueues_the_script_on_a_product_archive( bool $shop, bool $taxonomy ): void {
		$this->stub_storefront_context( false, $shop, $taxonomy );

		$scripts = $this->enqueue_stepper_with_manifest(
			[ 'src/js/woo.js' => [ 'file' => 'assets/woo-Xyz789.js' ] ]
		);

		self::assertSame(
			[ 'woodev-base-woo-js' => 'https://example.test/wp-content/themes/woodev-base-theme/assets/dist/assets/woo-Xyz789.js' ],
			$scripts
		);
	}

	/**
	 * An absent manifest degrades to "enqueue nothing", exactly like
	 * enqueue()'s own absent-manifest test above.
	 */
	public function test_enqueue_storefront_behaviour_an_absent_manifest_enqueues_nothing_and_does_not_fatal(): void {
		$this->stub_storefront_context( true, false, false );

		$root = \sys_get_temp_dir() . '/wtb-woo-js-' . \uniqid();

		Functions\when( 'get_template_directory' )->justReturn( $root );
		Functions\when( 'get_template_directory_uri' )->justReturn( 'https://example.test/wp-content/themes/woodev-base-theme' );
		Functions\expect( 'wp_json_file_decode' )->never();
		Functions\expect( 'wp_enqueue_script_module' )->never();

		( new Assets() )->enqueue_storefront_behaviour();
	}

	/**
	 * Sets up a real, readable temp manifest decoding to the given content,
	 * runs Assets::enqueue_storefront_behaviour(), and returns the scripts that
	 * were enqueued.
	 *
	 * @param array<string, array{file: string, css?: list<string>}> $manifest
	 * @return array<string, string>
	 */
	private function enqueue_stepper_with_manifest( array $manifest ): array {
		$root = \sys_get_temp_dir() . '/wtb-woo-js-' . \uniqid();
		\mkdir( $root . '/assets/dist/.vite', 0777, true );
		\file_put_contents( $root . '/assets/dist/.vite/manifest.json', '{}' );

		try {
			Functions\when( 'get_template_directory' )->justReturn( $root );
			Functions\when( 'get_template_directory_uri' )->justReturn( 'https://example.test/wp-content/themes/woodev-base-theme' );
			Functions\expect( 'wp_json_file_decode' )->once()->andReturn( $manifest );

			$scripts = [];
			Functions\when( 'wp_enqueue_script_module' )->alias(
				static function ( string $handle, string $src ) use ( &$scripts ): void {
					$scripts[ $handle ] = $src;
				}
			);

			( new Assets() )->enqueue_storefront_behaviour();

			return $scripts;
		} finally {
			\unlink( $root . '/assets/dist/.vite/manifest.json' );
			\rmdir( $root . '/assets/dist/.vite' );
			\rmdir( $root . '/assets/dist' );
			\rmdir( $root . '/assets' );
			\rmdir( $root );
		}
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
