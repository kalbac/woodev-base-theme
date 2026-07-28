<?php
/**
 * Minimal global `WC_Product` stand-in for the unit suite.
 *
 * WooCommerce is absent from the unit suite by design — Brain\Monkey fakes the
 * WordPress API surface and no plugin is loaded — but classes under test narrow
 * on `instanceof WC_Product`, so the type has to genuinely exist under that
 * exact global name.
 *
 * Requiring `vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php` from the
 * bootstrap instead was considered and rejected: that file is a static-analysis
 * artifact which declares several thousand real function symbols, and declaring
 * them takes away Brain\Monkey's ability to mock any of them.
 *
 * This file is `require_once`d by the tests that need it rather than autoloaded:
 * the PSR-4 map points `Woodev\Theme\Base\Tests\` at `tests/php/`, and this
 * class deliberately lives in the global namespace, so no autoloader can find
 * it by name.
 *
 * @package Woodev\Theme\Base\Tests
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product' ) ) {

	/**
	 * The three getters the storefront classes under test actually call.
	 *
	 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	 * -- Must match WooCommerce's own global class name exactly; a prefixed name
	 * would not satisfy the `instanceof` this double exists to satisfy.
	 */
	class WC_Product {

		/**
		 * Regular price, as WooCommerce stores it: a string, empty when the
		 * product has no single regular price (a variable product's range).
		 */
		private string $regular_price = '';

		/**
		 * Sale price, same string-or-empty convention as the regular price.
		 */
		private string $sale_price = '';

		/**
		 * Whether WooCommerce would report this product as on sale.
		 */
		private bool $on_sale = false;

		/**
		 * Set the three values in one call — test-only, not part of
		 * WooCommerce's own interface.
		 *
		 * @param string $regular_price Regular price, '' for none.
		 * @param string $sale_price    Sale price, '' for none.
		 * @param bool   $on_sale       What is_on_sale() should report.
		 */
		public function set_test_prices( string $regular_price, string $sale_price, bool $on_sale ): void {
			$this->regular_price = $regular_price;
			$this->sale_price    = $sale_price;
			$this->on_sale       = $on_sale;
		}

		/**
		 * @return string Regular price.
		 */
		public function get_regular_price(): string {
			return $this->regular_price;
		}

		/**
		 * @return string Sale price.
		 */
		public function get_sale_price(): string {
			return $this->sale_price;
		}

		/**
		 * @return bool Whether the product is on sale.
		 */
		public function is_on_sale(): bool {
			return $this->on_sale;
		}
	}

	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
}
