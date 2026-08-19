<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Woodev Base override, taken from WooCommerce 10.9.4 (M3–M5): the mockup's
 * dashboard — a greeting banner, three metric cards, and a recent-orders
 * table — replacing core's two plain paragraphs. Preserves
 * `woocommerce_account_dashboard` and the two deprecated actions core still
 * fires (`woocommerce_before_my_account`, `woocommerce_after_my_account`), in
 * the same order, at the end of the template exactly as core has them.
 *
 * Core's SECOND paragraph ("From your account dashboard you can view your
 * recent orders, manage your…") is deliberately gone: it is not in the
 * mockup, and restyling text the design does not have is not what this
 * override is for. The FIRST paragraph (the "Hello NAME" greeting, with Woo's
 * own translated `wc_logout_url()` link) is kept, verbatim, just moved inside
 * the mockup's banner wrapper.
 *
 * The mockup's third metric card is a loyalty-points balance, i.e. plugin
 * territory for a theme — not faked here. `wc_get_customer_total_spent()`'s
 * lifetime total substitutes for it. The second card ("orders currently in
 * transit") has no literal core status either: without a shipment-tracking
 * plugin, `processing` (payment received, order being prepared/shipped) is
 * the closest core signal, so that is what is counted.
 *
 * Every count below is a `wc_get_orders()` call with `return => 'ids'` and
 * `paginate => true` — the data store answers with `->total` from its own
 * found-rows count, never hydrating a single `WC_Order` object, per
 * `includes/data-stores/class-wc-order-data-store-cpt.php::query()`.
 *
 * The strings below that reproduce core's own copy verbatim (the greeting
 * sentence, and the "Recent orders" table's headings, "#" hash-prefix and
 * "View" action) reuse the `woocommerce` text domain rather than declaring a
 * new one: each is on the issue #52 carve-out allowlist in
 * `tests/php/Unit/I18nSourceTest.php`, which grants that domain only to the
 * exact msgids WooCommerce core already ships and translates, and only at a
 * call site under `woodev-base-theme/woocommerce/`. Strings the mockup adds
 * that core has no equivalent for ("Orders in the last 12 months", "Orders in
 * progress", "Lifetime spent", "No orders yet.") keep `woodev-base-theme`, as
 * does "Action" (core's own copy is "Actions", plural).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

use Woodev\Theme\Base\Icons;
use Woodev\Theme\Base\Woo\Account;

defined( 'ABSPATH' ) || exit;

$wtb_allowed_html = [
	'a' => [
		'href' => [],
	],
];

$wtb_year_ago = ( new DateTimeImmutable( '-12 months' ) )->format( 'Y-m-d' );

$wtb_orders_last_year = wc_get_orders(
	[
		'customer'     => $current_user->ID,
		'date_created' => '>' . $wtb_year_ago,
		'limit'        => 1,
		'return'       => 'ids',
		'paginate'     => true,
	]
);

$wtb_orders_in_transit = wc_get_orders(
	[
		'customer' => $current_user->ID,
		'status'   => [ 'wc-processing' ],
		'limit'    => 1,
		'return'   => 'ids',
		'paginate' => true,
	]
);

$wtb_recent_orders = wc_get_orders(
	[
		'customer' => $current_user->ID,
		'limit'    => 3,
	]
);
?>

<div class="woocommerce-message wtb-account-greeting" role="status">
	<?php echo Icons::get( 'circle-check', [ 'size' => 20 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns theme-controlled, already-safe inline SVG. ?>
	<span>
		<?php
		printf(
			/* translators: 1: user display name 2: logout url */
			wp_kses( __( 'Hello %1$s (not %1$s? <a href="%2$s">Log out</a>)', 'woocommerce' ), $wtb_allowed_html ),
			'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
			esc_url( wc_logout_url() )
		);
		?>
	</span>
</div>

<div class="wtb-dash-cards">
	<div class="wtb-dash-card">
		<p class="wtb-dash-card__k"><?php echo esc_html( number_format_i18n( $wtb_orders_last_year->total ) ); ?></p>
		<p class="wtb-dash-card__l"><?php esc_html_e( 'Orders in the last 12 months', 'woodev-base-theme' ); ?></p>
	</div>
	<div class="wtb-dash-card">
		<p class="wtb-dash-card__k"><?php echo esc_html( number_format_i18n( $wtb_orders_in_transit->total ) ); ?></p>
		<p class="wtb-dash-card__l"><?php esc_html_e( 'Orders in progress', 'woodev-base-theme' ); ?></p>
	</div>
	<div class="wtb-dash-card">
		<p class="wtb-dash-card__k"><?php echo wp_kses_post( wc_price( wc_get_customer_total_spent( $current_user->ID ) ) ); ?></p>
		<p class="wtb-dash-card__l"><?php esc_html_e( 'Lifetime spent', 'woodev-base-theme' ); ?></p>
	</div>
</div>

<h2 class="wtb-account-section-title"><?php esc_html_e( 'Recent orders', 'woocommerce' ); ?></h2>

<?php if ( [] === $wtb_recent_orders ) : ?>
	<p><?php esc_html_e( 'No orders yet.', 'woodev-base-theme' ); ?></p>
<?php else : ?>
	<table class="shop_table wtb-recent-orders">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Order', 'woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Date', 'woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Action', 'woodev-base-theme' ); ?></span></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $wtb_recent_orders as $wtb_order ) : ?>
				<tr>
					<td data-title="<?php esc_attr_e( 'Order', 'woocommerce' ); ?>">
						<a href="<?php echo esc_url( $wtb_order->get_view_order_url() ); ?>">
							<?php echo esc_html( _x( '#', 'hash before order number', 'woocommerce' ) . $wtb_order->get_order_number() ); ?>
						</a>
					</td>
					<?php
					/*
					 * `get_date_created()` is nullable — an order row whose
					 * `date_created` never got written (an interrupted import, a
					 * hand-inserted row) returns null, and `->date()` on null is
					 * a fatal on the customer's own account page.
					 * `wc_format_datetime()` already type-checks and returns ''
					 * for a non-WC_DateTime, so only the `datetime` attribute
					 * needs the guard; a `<time>` without one is invalid, so the
					 * element is dropped rather than emitted empty.
					 */
					$wtb_created = $wtb_order->get_date_created();
					?>
					<td data-title="<?php esc_attr_e( 'Date', 'woocommerce' ); ?>">
						<?php if ( null === $wtb_created ) : ?>
							&mdash;
						<?php else : ?>
							<time datetime="<?php echo esc_attr( $wtb_created->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $wtb_created ) ); ?></time>
						<?php endif; ?>
					</td>
					<td data-title="<?php esc_attr_e( 'Status', 'woocommerce' ); ?>">
						<?php echo Account::status_badge( $wtb_order->get_status() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Account::status_badge() escapes its own output. ?>
					</td>
					<td data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>">
						<?php echo wp_kses_post( $wtb_order->get_formatted_order_total() ); ?>
					</td>
					<td>
						<a class="button" href="<?php echo esc_url( $wtb_order->get_view_order_url() ); ?>"><?php esc_html_e( 'View', 'woocommerce' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php
	/**
	 * My Account dashboard.
	 *
	 * @since 2.6.0
	 */
	do_action( 'woocommerce_account_dashboard' );

	/**
	 * Deprecated woocommerce_before_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_before_my_account' );

	/**
	 * Deprecated woocommerce_after_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_after_my_account' );

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
