<?php
/**
 * Woo storefront asset loading via the Vite manifest.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Assets as BaseAssets;

/**
 * Enqueues the storefront CSS bundle unconditionally on the front end (this
 * class only ever loads when WooCommerce is active — see `enqueue()` for why
 * that alone is guard enough).
 */
final class Assets {

	/** Vite manifest key for the storefront bundle (the Rollup input's source path). */
	private const WOO_ENTRY = 'src/css/woo.css';

	/**
	 * Hook the storefront enqueue into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue the built storefront bundle unconditionally on the front end.
	 *
	 * There is no Woo-context guard here on purpose. `[products]`,
	 * `[product_category]`, `[featured_products]` and the Woo product blocks
	 * render `woocommerce/content-product.php` (our override, with all the
	 * `wtb-*` classes) on ANY page — a shortcode/block loop is not itself a
	 * "Woo context" (`is_woocommerce()` is `is_shop() || is_product_taxonomy()
	 * || is_product()`, false there), so a conditional enqueue used to ship
	 * that markup with no stylesheet. Unconditional is safe because every rule
	 * in `src/css/woo.css` is nested inside a top-level `.woocommerce { … }`
	 * block (plus two `.woocommerce`-prefixed rules) — the bundle is
	 * completely inert on a page with no Woo markup, since Woo's own shortcode
	 * wrapper (`<div class="woocommerce columns-N">`) and its `woocommerce`
	 * body class are what supply that hook. WooCommerce itself enqueues its
	 * front-end styles on every page for the same reason; being conditional
	 * here only created an asymmetry where Woo markup could appear WITH Woo's
	 * CSS but WITHOUT ours. And "unconditional" is narrower than it sounds:
	 * this class only loads inside `class_exists( 'WooCommerce' )`
	 * (inc/Theme.php), and `wp_enqueue_scripts` only fires on the front end —
	 * so in practice this means "every front-end request of a site that runs
	 * WooCommerce".
	 */
	public function enqueue(): void {
		$dist     = get_template_directory() . '/assets/dist';
		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$manifest = BaseAssets::read_manifest( $dist . '/.vite/manifest.json' );
		$css      = BaseAssets::entry_file( $manifest, self::WOO_ENTRY );

		if ( null !== $css ) {
			wp_enqueue_style( 'woodev-base-woo', "{$dist_uri}/{$css}", [], null );
		}
	}
}
