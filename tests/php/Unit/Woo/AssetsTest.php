<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\Assets;

final class AssetsTest extends TestCase {

	public function test_off_every_woo_context_enqueues_nothing_and_never_reads_the_manifest(): void {
		Functions\when( 'is_woocommerce' )->justReturn( false );
		Functions\when( 'is_cart' )->justReturn( false );
		Functions\when( 'is_checkout' )->justReturn( false );
		Functions\when( 'is_account_page' )->justReturn( false );

		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_json_file_decode' )->never();

		( new Assets() )->enqueue();
	}

	public function test_on_the_shop_enqueues_the_woo_bundle_with_the_resolved_hashed_url(): void {
		$styles = $this->enqueue_on_context(
			[
				'is_woocommerce'  => true,
				'is_cart'         => false,
				'is_checkout'     => false,
				'is_account_page' => false,
			]
		);

		self::assertSame(
			[ 'woodev-base-woo' => 'https://example.test/wp-content/themes/woodev-base-theme/assets/dist/assets/woo-Abc123.css' ],
			$styles
		);
	}

	public function test_on_the_cart_alone_enqueues_the_woo_bundle(): void {
		$styles = $this->enqueue_on_context(
			[
				'is_woocommerce'  => false,
				'is_cart'         => true,
				'is_checkout'     => false,
				'is_account_page' => false,
			]
		);

		self::assertArrayHasKey( 'woodev-base-woo', $styles );
	}

	public function test_on_checkout_alone_enqueues_the_woo_bundle(): void {
		$styles = $this->enqueue_on_context(
			[
				'is_woocommerce'  => false,
				'is_cart'         => false,
				'is_checkout'     => true,
				'is_account_page' => false,
			]
		);

		self::assertArrayHasKey( 'woodev-base-woo', $styles );
	}

	public function test_on_the_account_page_alone_enqueues_the_woo_bundle(): void {
		$styles = $this->enqueue_on_context(
			[
				'is_woocommerce'  => false,
				'is_cart'         => false,
				'is_checkout'     => false,
				'is_account_page' => true,
			]
		);

		self::assertArrayHasKey( 'woodev-base-woo', $styles );
	}

	/**
	 * Sets up a real, readable temp manifest so read_manifest() succeeds, stubs
	 * the given Woo context tags, mocks the manifest decode to resolve the woo
	 * bundle, runs Assets::enqueue(), and returns the styles that were enqueued.
	 *
	 * @param array{is_woocommerce: bool, is_cart: bool, is_checkout: bool, is_account_page: bool} $context
	 * @return array<string, string>
	 */
	private function enqueue_on_context( array $context ): array {
		$root = \sys_get_temp_dir() . '/wtb-woo-' . \uniqid();
		\mkdir( $root . '/assets/dist/.vite', 0777, true );
		\file_put_contents( $root . '/assets/dist/.vite/manifest.json', '{}' );

		try {
			Functions\when( 'is_woocommerce' )->justReturn( $context['is_woocommerce'] );
			Functions\when( 'is_cart' )->justReturn( $context['is_cart'] );
			Functions\when( 'is_checkout' )->justReturn( $context['is_checkout'] );
			Functions\when( 'is_account_page' )->justReturn( $context['is_account_page'] );
			Functions\when( 'get_template_directory' )->justReturn( $root );
			Functions\when( 'get_template_directory_uri' )->justReturn( 'https://example.test/wp-content/themes/woodev-base-theme' );
			Functions\expect( 'wp_json_file_decode' )->once()->andReturn( [ 'src/css/woo.css' => [ 'file' => 'assets/woo-Abc123.css' ] ] );

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
