<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Mockery;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\Receipt;

/**
 * Both callbacks narrow on `instanceof WC_Order`, a class the unit suite has
 * no source for — tests/php/Support/wc-order-double.php supplies it and the
 * unit bootstrap loads it, so it is available to every test regardless of
 * discovery order.
 */
final class ReceiptTest extends TestCase {

	public function test_register_hooks_both_actions_at_the_documented_priorities(): void {
		$receipt = new Receipt();
		$receipt->register();

		self::assertSame( 10, \has_action( 'woocommerce_before_thankyou', [ $receipt, 'hero' ] ) );
		self::assertSame( 20, \has_action( 'woocommerce_thankyou', [ $receipt, 'actions' ] ) );
	}

	// ------------------------------------------------------------------------ hero()

	private function stub_hero_wp_functions(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		// Real vendored `check.svg`, read off disk — the same reasoning
		// CatalogueTest's pagination assertions use: a stubbed Icons could not
		// catch the icon silently disappearing from scripts/copy-icons.mjs.
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );
	}

	public function test_hero_prints_the_check_icon_and_the_title(): void {
		$this->stub_hero_wp_functions();
		Functions\when( 'wc_get_order' )->justReturn( false );

		\ob_start();
		( new Receipt() )->hero( 123 );
		$html = \ob_get_clean();

		self::assertStringContainsString( '<div class="wtb-receipt-hero">', $html );
		self::assertStringContainsString( '<span class="wtb-receipt-hero__check">', $html );
		self::assertStringContainsString( '<svg', $html );
		self::assertStringContainsString( '<h1 class="wtb-receipt-hero__title">Thank you, your order is in</h1>', $html );
	}

	/**
	 * `woocommerce_before_thankyou` fires BEFORE core's own
	 * `$order->has_status( 'failed' )` branch — printing a success hero ahead
	 * of core's decline notice would be a real, visible defect.
	 */
	public function test_hero_prints_nothing_for_a_failed_order(): void {
		$this->stub_hero_wp_functions();

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'has_status' )->with( 'failed' )->andReturn( true );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		\ob_start();
		( new Receipt() )->hero( 123 );

		self::assertSame( '', \ob_get_clean() );
	}

	public function test_hero_prints_for_a_non_failed_order(): void {
		$this->stub_hero_wp_functions();

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'has_status' )->with( 'failed' )->andReturn( false );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		\ob_start();
		( new Receipt() )->hero( 123 );

		self::assertStringContainsString( 'wtb-receipt-hero', \ob_get_clean() );
	}

	/**
	 * A non-numeric id (a third party re-firing the hook by hand) must not
	 * even reach `wc_get_order()` — no expectation is set for it, so an
	 * unexpected call fails the test outright.
	 */
	public function test_hero_never_calls_wc_get_order_for_a_non_numeric_id(): void {
		$this->stub_hero_wp_functions();
		Functions\expect( 'wc_get_order' )->never();

		\ob_start();
		( new Receipt() )->hero( 'not-an-id' );

		self::assertStringContainsString( 'wtb-receipt-hero', \ob_get_clean() );
	}

	// --------------------------------------------------------------------- actions()

	private function stub_actions_wp_functions(): void {
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'wc_get_page_permalink' )->justReturn( 'https://example.test/shop/' );
	}

	/**
	 * `checkout/thankyou.php` fires `woocommerce_thankyou` AFTER its
	 * failed/success `endif`, so this callback runs on a FAILED order too —
	 * which is the easy thing to assume it does not. Unguarded, the page reads
	 * "your payment was declined" and then offers "Track order" and "Back to
	 * shop", telling the buyer to walk away from a payment they still owe.
	 * hero() has its own, separate guard for the same status; this is not the
	 * same code path and one does not cover the other.
	 */
	public function test_actions_prints_nothing_for_a_failed_order(): void {
		$this->stub_actions_wp_functions();
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 9 );

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'has_status' )->with( 'failed' )->andReturn( true );
		// Reached only if the status guard fails to return early — so these
		// being NEVER called is itself part of the assertion.
		$order->shouldReceive( 'get_customer_id' )->never();
		$order->shouldReceive( 'get_view_order_url' )->never();
		Functions\when( 'wc_get_order' )->justReturn( $order );

		\ob_start();
		( new Receipt() )->actions( 42 );

		self::assertSame( '', \ob_get_clean() );
	}

	/**
	 * A guest receipt on a store with no Shop page set has neither link to
	 * print. The wrapper must not be emitted empty: `.wtb-receipt-actions` is a
	 * centred flex row with a top margin, so an empty one is visible as stray
	 * vertical space rather than as nothing.
	 */
	public function test_actions_prints_no_wrapper_when_neither_link_is_available(): void {
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'wc_get_page_permalink' )->justReturn( '' );
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'has_status' )->with( 'failed' )->andReturn( false );
		$order->shouldReceive( 'get_customer_id' )->andReturn( 0 );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		\ob_start();
		( new Receipt() )->actions( 42 );

		self::assertSame( '', \ob_get_clean() );
	}

	public function test_actions_prints_nothing_when_the_order_does_not_resolve(): void {
		Functions\when( 'wc_get_order' )->justReturn( false );

		\ob_start();
		( new Receipt() )->actions( 999 );

		self::assertSame( '', \ob_get_clean() );
	}

	/**
	 * A non-numeric id must not even reach `wc_get_order()`.
	 */
	public function test_actions_never_calls_wc_get_order_for_a_non_numeric_id(): void {
		Functions\expect( 'wc_get_order' )->never();

		\ob_start();
		( new Receipt() )->actions( 'not-an-id' );

		self::assertSame( '', \ob_get_clean() );
	}

	/**
	 * `get_customer_id()` is `0` for a guest order, and `get_current_user_id()`
	 * is ALSO `0` for a logged-out visitor — `0 === 0` would otherwise match
	 * and leak a tracking link into a guest's own receipt for an order they
	 * do not own. This is the exact guard the task calls out.
	 */
	public function test_actions_omits_the_tracking_link_for_a_guest_order(): void {
		$this->stub_actions_wp_functions();
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$order = Mockery::mock( 'WC_Order' );
		// actions() checks the status before anything else now — a failed order
		// gets no cluster at all (see Receipt::actions()). Declared explicitly
		// rather than through shouldIgnoreMissing(), so a future call this test
		// does not expect still fails loudly.
		$order->shouldReceive( 'has_status' )->with( 'failed' )->andReturn( false );
		$order->shouldReceive( 'get_customer_id' )->andReturn( 0 );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		\ob_start();
		( new Receipt() )->actions( 42 );
		$html = \ob_get_clean();

		self::assertStringNotContainsString( 'Track order', $html );
		self::assertStringContainsString( 'Back to shop', $html );
		self::assertStringContainsString( 'wtb-button--outline', $html );
	}

	/**
	 * The scenario the `0`-guard exists for in isolation: `is_user_logged_in()`
	 * is forced true so the code reaches the ownership comparison at all, and
	 * BOTH ids happen to be `0`. Without an explicit "customer_id is not 0"
	 * guard, `0 === 0` matches and the link renders — the guest-order test
	 * above cannot tell this apart from `is_user_logged_in()` alone doing the
	 * work, because there `is_user_logged_in()` is false and short-circuits
	 * first. This test is what actually exercises the guard the task calls
	 * out; it is the one the mutation below is checked against.
	 */
	public function test_actions_omits_the_tracking_link_when_both_ids_are_zero(): void {
		$this->stub_actions_wp_functions();
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		$order = Mockery::mock( 'WC_Order' );
		// actions() checks the status before anything else now — a failed order
		// gets no cluster at all (see Receipt::actions()). Declared explicitly
		// rather than through shouldIgnoreMissing(), so a future call this test
		// does not expect still fails loudly.
		$order->shouldReceive( 'has_status' )->with( 'failed' )->andReturn( false );
		$order->shouldReceive( 'get_customer_id' )->andReturn( 0 );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		\ob_start();
		( new Receipt() )->actions( 42 );
		$html = \ob_get_clean();

		self::assertStringNotContainsString( 'Track order', $html );
		self::assertStringContainsString( 'Back to shop', $html );
		self::assertStringContainsString( 'wtb-button--outline', $html );
	}

	public function test_actions_omits_the_tracking_link_for_a_different_logged_in_customer(): void {
		$this->stub_actions_wp_functions();
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );

		$order = Mockery::mock( 'WC_Order' );
		// actions() checks the status before anything else now — a failed order
		// gets no cluster at all (see Receipt::actions()). Declared explicitly
		// rather than through shouldIgnoreMissing(), so a future call this test
		// does not expect still fails loudly.
		$order->shouldReceive( 'has_status' )->with( 'failed' )->andReturn( false );
		$order->shouldReceive( 'get_customer_id' )->andReturn( 9 );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		\ob_start();
		( new Receipt() )->actions( 42 );

		self::assertStringNotContainsString( 'Track order', \ob_get_clean() );
	}

	public function test_actions_prints_the_tracking_link_for_the_order_owner(): void {
		$this->stub_actions_wp_functions();
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 9 );

		$order = Mockery::mock( 'WC_Order' );
		// actions() checks the status before anything else now — a failed order
		// gets no cluster at all (see Receipt::actions()). Declared explicitly
		// rather than through shouldIgnoreMissing(), so a future call this test
		// does not expect still fails loudly.
		$order->shouldReceive( 'has_status' )->with( 'failed' )->andReturn( false );
		$order->shouldReceive( 'get_customer_id' )->andReturn( 9 );
		$order->shouldReceive( 'get_view_order_url' )->andReturn( 'https://example.test/my-account/view-order/42/' );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		\ob_start();
		( new Receipt() )->actions( 42 );
		$html = \ob_get_clean();

		self::assertStringContainsString( 'Track order', $html );
		self::assertStringContainsString( 'https://example.test/my-account/view-order/42/', $html );
		self::assertStringContainsString( 'Back to shop', $html );
	}
}
