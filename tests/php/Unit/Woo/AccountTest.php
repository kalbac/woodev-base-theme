<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Mockery;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\Account;

/**
 * `render_order_status_column()` narrows on `instanceof WC_Order`, a class
 * the unit suite has no source for — tests/php/Support/wc-order-double.php
 * supplies it and the unit bootstrap loads it, so it is available to every
 * test regardless of discovery order.
 */
final class AccountTest extends TestCase {

	public function test_register_hooks_the_order_status_column_filter(): void {
		$account = new Account();
		$account->register();

		self::assertSame(
			10,
			\has_action( 'woocommerce_my_account_my_orders_column_order-status', [ $account, 'render_order_status_column' ] )
		);
	}

	// -------------------------------------------------------------- status_tone()

	/**
	 * Every status `wc_get_order_statuses()` returns, named explicitly, plus
	 * the tone each must land on. This is the exhaustive guard the task calls
	 * for: a status silently falling off this table would otherwise only be
	 * noticed by eye on a live orders screen.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function every_core_status(): array {
		return [
			'pending'    => [ 'pending', 'neutral' ],
			'on-hold'    => [ 'on-hold', 'neutral' ],
			'processing' => [ 'processing', 'accent' ],
			'completed'  => [ 'completed', 'success' ],
			'cancelled'  => [ 'cancelled', 'neutral' ],
			'refunded'   => [ 'refunded', 'neutral' ],
			'failed'     => [ 'failed', 'neutral' ],
		];
	}

	/**
	 * @dataProvider every_core_status
	 */
	public function test_status_tone_maps_every_core_status( string $status, string $expected_tone ): void {
		self::assertSame( $expected_tone, Account::status_tone( $status ) );
	}

	/**
	 * `failed` and `cancelled` are the one hard requirement in the task: they
	 * must never resolve to the same tone as `completed`. Asserted on its own,
	 * separately from the table above, so a future edit cannot make both
	 * "successful" without a test noticing specifically that.
	 */
	public function test_status_tone_never_gives_failed_or_cancelled_the_success_tone(): void {
		self::assertNotSame( 'success', Account::status_tone( 'failed' ) );
		self::assertNotSame( 'success', Account::status_tone( 'cancelled' ) );
	}

	public function test_status_tone_falls_back_to_neutral_for_an_unmapped_status(): void {
		self::assertSame( 'neutral', Account::status_tone( 'a-plugin-added-status' ) );
	}

	// ------------------------------------------------------------- status_badge()

	public function test_status_badge_renders_the_tone_the_slug_and_the_localised_label(): void {
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'wc_get_order_status_name' )->justReturn( 'Processing' );

		self::assertSame(
			'<span class="wtb-status-badge wtb-status-badge--accent is-processing">Processing</span>',
			Account::status_badge( 'processing' )
		);
	}

	/**
	 * The TONE class is what `src/css/woo/account.css` styles — the stylesheet
	 * never names a status, so that the seven-plus-statuses-to-three-tones
	 * mapping lives only in Account::STATUS_TONES. This pins the resolved tone
	 * for a status whose slug and tone differ, which `--accent`/`processing`
	 * above already does, plus the case that would silently regress if the tone
	 * class were dropped and CSS started guessing: a status the map has never
	 * heard of must come out neutral, not unstyled.
	 */
	public function test_status_badge_carries_the_resolved_tone_for_an_unknown_status(): void {
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'wc_get_order_status_name' )->justReturn( 'Awaiting Pickup' );

		self::assertSame(
			'<span class="wtb-status-badge wtb-status-badge--neutral is-awaiting-pickup">Awaiting Pickup</span>',
			Account::status_badge( 'awaiting-pickup' )
		);
	}

	// ------------------------------------------------- render_order_status_column()

	public function test_render_order_status_column_prints_the_badge_for_a_real_order(): void {
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'wc_get_order_status_name' )->justReturn( 'Completed' );

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_status' )->andReturn( 'completed' );

		\ob_start();
		( new Account() )->render_order_status_column( $order );
		$html = \ob_get_clean();

		self::assertSame(
			'<span class="wtb-status-badge wtb-status-badge--success is-completed">Completed</span>',
			$html
		);
	}

	/**
	 * `woocommerce_my_account_my_orders_column_order-status` is a `do_action()`
	 * a third party can re-fire by hand with anything; this file runs under
	 * strict_types, where a typed parameter would turn that into a fatal.
	 */
	public function test_render_order_status_column_prints_nothing_for_a_non_order(): void {
		\ob_start();
		( new Account() )->render_order_status_column( 'not an order' );

		self::assertSame( '', \ob_get_clean() );
	}

	// ------------------------------------------------------------------ nav_icon()

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function every_documented_endpoint(): array {
		return [
			'dashboard'       => [ 'dashboard' ],
			'orders'          => [ 'orders' ],
			'downloads'       => [ 'downloads' ],
			'edit-address'    => [ 'edit-address' ],
			'payment-methods' => [ 'payment-methods' ],
			'edit-account'    => [ 'edit-account' ],
			'customer-logout' => [ 'customer-logout' ],
		];
	}

	/**
	 * Real vendored SVGs, read off disk via `get_template_directory()`, the
	 * same approach `CatalogueTest`'s pagination assertions use — it also
	 * catches the icon silently disappearing if a slug is dropped from
	 * `scripts/copy-icons.mjs`, which a stubbed `Icons` could not.
	 *
	 * @dataProvider every_documented_endpoint
	 */
	public function test_nav_icon_returns_a_real_icon_for_every_documented_endpoint( string $endpoint ): void {
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );
		Functions\when( 'esc_attr' )->returnArg( 1 );

		self::assertStringContainsString( '<svg', Account::nav_icon( $endpoint ) );
		self::assertStringContainsString( 'class="wtb-account-nav__icon"', Account::nav_icon( $endpoint ) );
	}

	/**
	 * An endpoint with no mapping (a plugin-added tab) renders no icon at
	 * all — not a fallback glyph — per the task's contract.
	 */
	public function test_nav_icon_returns_nothing_for_an_unmapped_endpoint(): void {
		self::assertSame( '', Account::nav_icon( 'a-plugin-added-endpoint' ) );
	}
}
