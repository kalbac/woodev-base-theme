<?php
/**
 * Conditional Woo storefront asset loading via the Vite manifest.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

use Woodev\Theme\Base\Assets as BaseAssets;

/**
 * Enqueues the storefront CSS bundle only on WooCommerce contexts.
 */
final class Assets {

	/** Vite manifest key for the storefront bundle (the Rollup input's source path). */
	private const WOO_ENTRY = 'src/css/woo.css';

	/**
	 * Hook the conditional storefront enqueue into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue the built storefront bundle on Woo contexts only.
	 *
	 * Dev-server delivery is intentionally not wired here: the Woo storefront is
	 * only ever served/tested against built production assets (v1 YAGNI), so an
	 * absent manifest on a fresh checkout degrades silently to "enqueue nothing".
	 */
	public function enqueue(): void {
		// is_woocommerce() is false on cart/checkout/account, so each must be
		// checked explicitly (verified Woo 10.9.4 contract). Off a Woo context we
		// enqueue nothing and never even read the manifest.
		if ( ! ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
			return;
		}

		$dist     = get_template_directory() . '/assets/dist';
		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$manifest = BaseAssets::read_manifest( $dist . '/.vite/manifest.json' );
		$css      = BaseAssets::entry_file( $manifest, self::WOO_ENTRY );

		if ( null !== $css ) {
			wp_enqueue_style( 'woodev-base-woo', "{$dist_uri}/{$css}", [], null );
		}
	}
}
