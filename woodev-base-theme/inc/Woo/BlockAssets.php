<?php
/**
 * Block-based Cart/Checkout asset loading via the Vite manifest.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

use Woodev\Theme\Base\Assets as BaseAssets;

/**
 * Enqueues the block Cart/Checkout CSS bundle, but ONLY when the current
 * page actually renders one of the two blocks — the opposite of Woo\Assets,
 * which loads its bundle unconditionally on every front-end request (see
 * that class's own docblock for why).
 *
 * The two enqueue policies are each correct for their own bundle, not
 * inconsistent with one another. Woo\Assets is unconditional because
 * `[products]`, `[product_category]`, `[featured_products]` and the Woo
 * product blocks can render our `content-product.php` override on ANY
 * page via a shortcode or block loop — a product loop is not itself a
 * "Woo context", so there is no cheap, reliable condition to gate on, and
 * the bundle stays inert (it is entirely `.woocommerce`-scoped) when unused.
 * Cart and Checkout are different in kind: both blocks declare
 * `"multiple": false` in their `block.json` and WooCommerce's
 * `install_pages` creates exactly one page for each, so "does this page
 * contain the block" is both ACCURATE (the block cannot appear anywhere
 * else) and CHEAP (`has_block()` is a `str_contains()` scan of the already-
 * loaded post content, no query). Loading it unconditionally the way
 * Woo\Assets does would put it on every front-end request of a WooCommerce
 * store to serve the only two pages that can ever use it. Size is not the
 * argument and no figure is quoted here on purpose — an earlier draft of
 * this docblock said "~30 KB", which is woo.css's built size, not this
 * bundle's (1.4 KB today, and it grows as the plan's remaining work-list
 * rows land).
 *
 * has_block() with a null $post — verified against the installed WordPress
 * core source (wp-includes/blocks.php, wp-includes/post.php), not assumed:
 * `has_block( $name, $post = null )` calls `has_blocks( $post )`, which calls
 * `get_post( $post )`. `get_post( null )` reads the global `$post` — and if
 * that global is also unset or empty (an archive, a 404, anything before The
 * Loop has set it up), `$post` stays `null`, falls through to
 * `WP_Post::get_instance( null )`, which casts to `(int) 0` and returns
 * `false` immediately (`$post_id <= 0`). Back in `has_blocks()`,
 * `false instanceof WP_Post` is `false`, so it returns `false` — and
 * `has_block()` short-circuits on that before touching `$post` again. So an
 * absent global `$post` degrades to "no block found", exactly like an absent
 * Vite manifest degrades to "enqueue nothing" below: no notice, no fatal,
 * just silently false — no second argument needs to be passed here.
 */
final class BlockAssets {

	/** Vite manifest key for the block Cart/Checkout bundle (the Rollup input's source path). */
	private const ENTRY = 'src/css/woo-blocks.css';

	/**
	 * Hook the conditional enqueue into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue the built block Cart/Checkout bundle, only when the current
	 * page's content contains one of the two blocks.
	 *
	 * An absent manifest degrades to "enqueue nothing", never a fatal — same
	 * contract as Assets::enqueue() and Woo\Assets::enqueue(), via the same
	 * shared reader (BaseAssets::read_manifest()/entry_file()).
	 */
	public function enqueue(): void {
		if ( ! has_block( 'woocommerce/cart' ) && ! has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		$dist     = get_template_directory() . '/assets/dist';
		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$manifest = BaseAssets::read_manifest( $dist . '/.vite/manifest.json' );
		$css      = BaseAssets::entry_file( $manifest, self::ENTRY );

		if ( null !== $css ) {
			wp_enqueue_style( 'woodev-base-woo-blocks', "{$dist_uri}/{$css}", [], null );
		}
	}
}
