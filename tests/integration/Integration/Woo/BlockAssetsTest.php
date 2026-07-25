<?php
/**
 * Block Cart/Checkout asset integration tests: the handle appears only on a
 * page whose content actually carries the block, inside a real WordPress
 * request/query — not just as a bare PHP array from the unit suite
 * (tests/php/Unit/Woo/BlockAssetsTest.php).
 *
 * WordPress core's `has_block()` needs no block registration, so —
 * unlike BootstrapTest in this same directory — these tests do NOT skip when
 * WooCommerce is inactive (see BootstrapTest.php's own docblock: this
 * integration environment has no WooCommerce active). BlockAssets is
 * instantiated directly rather than via Theme::boot(), which never
 * registers the Woo layer at all without `class_exists( 'WooCommerce' )`.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration\Woo;

use WP_UnitTestCase;
use Woodev\Theme\Base\Woo\BlockAssets;

final class BlockAssetsTest extends WP_UnitTestCase {

	private const HANDLE = 'woodev-base-woo-blocks';

	/**
	 * Reset the style registry before every test in this class.
	 *
	 * `WP_UnitTestCase` rolls back the database, not the `$wp_styles` global:
	 * `WP_Styles` is built once per process and every enqueue in this class
	 * accumulates in it. Without this reset, the "neither block" test below
	 * passed when run alone (`--filter`) and FAILED inside the suite, because
	 * the two tests above it had already registered the handle — the classic
	 * works-alone/fails-in-suite shape, and the reason it is worth a comment
	 * rather than a silent `set_up()`. Unsetting the global is the whole fix:
	 * `wp_styles()` lazily rebuilds a fresh `WP_Styles` on its next call, so
	 * each test starts from an empty registry.
	 */
	public function set_up(): void {
		parent::set_up();
		unset( $GLOBALS['wp_styles'] );
	}

	/**
	 * Guards the two "present" assertions below: an absent Vite build
	 * (assets/dist is gitignored) means BlockAssets::enqueue() finds no
	 * manifest entry and enqueues nothing even on a page WITH the block —
	 * mirrors AssetsProductionTest::test_built_assets_are_enqueued_from_the_manifest()'s
	 * identical guard on the base theme's own bundle.
	 */
	private static function manifest_is_present(): bool {
		return is_file( get_template_directory() . '/assets/dist/.vite/manifest.json' );
	}

	public function test_the_handle_is_enqueued_on_a_page_with_the_cart_block(): void {
		if ( ! self::manifest_is_present() ) {
			self::markTestSkipped( 'No Vite build present — run `npm run build` to cover this path.' );
		}

		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_content' => '<!-- wp:woocommerce/cart --><div class="wp-block-woocommerce-cart"></div><!-- /wp:woocommerce/cart -->',
			]
		);
		$this->go_to( get_permalink( $page_id ) );

		( new BlockAssets() )->enqueue();

		self::assertTrue( wp_style_is( self::HANDLE, 'enqueued' ), 'Expected the block bundle to be enqueued on a page containing the Cart block.' );
	}

	public function test_the_handle_is_enqueued_on_a_page_with_the_checkout_block(): void {
		if ( ! self::manifest_is_present() ) {
			self::markTestSkipped( 'No Vite build present — run `npm run build` to cover this path.' );
		}

		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_content' => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout"></div><!-- /wp:woocommerce/checkout -->',
			]
		);
		$this->go_to( get_permalink( $page_id ) );

		( new BlockAssets() )->enqueue();

		self::assertTrue( wp_style_is( self::HANDLE, 'enqueued' ), 'Expected the block bundle to be enqueued on a page containing the Checkout block.' );
	}

	/**
	 * Runs regardless of whether a build is present: BlockAssets::enqueue()
	 * must return before touching the manifest at all when neither block is
	 * in the page's content — mirrors the unit suite's assertion that
	 * wp_json_file_decode() is never called for this case, at the
	 * integration level (a real WP_Query-backed $post, not a Brain\Monkey
	 * stub).
	 */
	public function test_the_handle_is_absent_on_a_page_without_either_block(): void {
		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_content' => '<p>Just a plain page, no Woo blocks here.</p>',
			]
		);
		$this->go_to( get_permalink( $page_id ) );

		( new BlockAssets() )->enqueue();

		self::assertFalse( wp_style_is( self::HANDLE, 'registered' ), 'The block bundle must not even be registered on a page with neither block.' );
	}
}
