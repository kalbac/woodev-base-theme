<?php
/**
 * Minimal global `WC_Order` stand-in for the unit suite.
 *
 * WooCommerce is absent from the unit suite by design (Brain\Monkey fakes the
 * WordPress API surface and no plugin is loaded), but `Account` and `Receipt`
 * narrow on `instanceof WC_Order`, so the type has to genuinely exist under
 * that exact global name. Same rationale as `wc-product-double.php` sitting
 * next to this file — read that file's header for why this is a hand-written
 * double rather than the WooCommerce stub package.
 *
 * `Mockery::mock( 'WC_Order' )` in the test files below builds a mock that
 * `extends` this class, which is what makes `instanceof WC_Order` true for it
 * without this double having to implement anything real itself — every method
 * the tests need is stubbed per-test with `shouldReceive()`.
 *
 * `require_once`d from `tests/php/bootstrap.php` for the same reason
 * `wc-product-double.php` is: loading it from the one test file that happened
 * to need it first would make every other test's view of `WC_Order` depend on
 * PHPUnit's file-discovery order.
 *
 * @package Woodev\Theme\Base\Tests
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Order' ) ) {

	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	// -- Must match WooCommerce's own global class name exactly; a prefixed
	// name would not satisfy the `instanceof` this double exists to satisfy.
	class WC_Order {

		/**
		 * @return string
		 */
		public function get_status() {
			return '';
		}

		/**
		 * @param string|array<int, string> $status
		 * @return bool
		 */
		public function has_status( $status ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature must match WC_Order's; every real test overrides this via Mockery's shouldReceive().
			return false;
		}

		/**
		 * @return int
		 */
		public function get_id() {
			return 0;
		}

		/**
		 * @return int
		 */
		public function get_customer_id() {
			return 0;
		}

		/**
		 * @return string
		 */
		public function get_view_order_url() {
			return '';
		}

		/**
		 * @return string
		 */
		public function get_order_number() {
			return '';
		}
	}

	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
}
