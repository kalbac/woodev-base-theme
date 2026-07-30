<?php
/**
 * View Order
 *
 * Shows the details of a particular order on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/view-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Woodev Base override, taken from WooCommerce 10.9.4 (M7): a breadcrumb, an
 * order head (title + status badge) and a 4-up meta grid, replacing core's
 * single status sentence. Preserves `do_action( 'woocommerce_view_order',
 * $order_id )` unchanged, which is what renders `order/order-details.php` and
 * `order-details-customer.php`; neither is inlined here.
 *
 * DROPPED, deliberately: core's `<p>` status sentence ("Order #N was placed on
 * D and is currently S.") and, with it, the `woocommerce_order_details_status`
 * filter. The sentence is replaced by the head + meta grid above, which is the
 * whole point of the override; the head, the badge and the meta grid carry
 * every fact that sentence carried.
 *
 * Keeping the filter call with its return value discarded was tried first and
 * rejected. It would mean reproducing core's whole sentence — a `sprintf` over
 * three `<mark>` elements — purely as filter input, which puts a translatable
 * string that NEVER renders into this theme's `.pot`. A translator has to
 * translate it and can never see it, and this theme's translations are
 * hand-maintained (ADR-006). A filter on a string the template does not print
 * has nothing to filter; a plugin that needs to change that sentence is
 * looking at a template which no longer has it, which is the honest signal.
 *
 * The order head uses `<h2>`, not `<h1>`: the My Account page this endpoint
 * renders through already has an `<h1>` from `page.php` →
 * `template-parts/content/content.php` (the page's own title, e.g. "My
 * account") — confirmed by reading both files rather than assumed. A second
 * `<h1>` here would be the exact class of defect the front page hero already
 * guards against (s16).
 *
 * Two strings below reproduce core's own copy — the "#" hash-prefix
 * disambiguation and "Order updates" — and both reuse the `woocommerce` text
 * domain rather than declaring a new one: each is on the issue #52 carve-out
 * allowlist in `tests/php/Unit/I18nSourceTest.php`, which grants that domain
 * only to the exact msgids WooCommerce core already ships and translates, and
 * only at a call site under `woodev-base-theme/woocommerce/`. "Order %s" is
 * this theme's own composition of that hash-prefix string with the order
 * number — not a core msgid itself — so it keeps `woodev-base-theme`.
 *
 * Two OTHER core strings are gone rather than re-declared, and that is the
 * better outcome: the status sentence (dropped with its filter, above) and
 * core's translatable order-note date FORMAT (`l jS \o\f F Y, h:ia`), which
 * this template replaces with the site's own configured Date/Time formats —
 * so no locale has to translate a date format, and the note reads the way
 * every other date on the site already does.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.6.0
 */

use Woodev\Theme\Base\Woo\Account;

defined( 'ABSPATH' ) || exit;

$wtb_notes = $order->get_customer_order_notes();
?>
<nav class="woocommerce-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'woodev-base-theme' ); ?>">
	<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Account', 'woodev-base-theme' ); ?></a>
	<span class="wtb-breadcrumb__sep" aria-hidden="true">/</span>
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'Orders', 'woodev-base-theme' ); ?></a>
	<span class="wtb-breadcrumb__sep" aria-hidden="true">/</span>
	<span aria-current="page">
		<?php
		printf(
			/* translators: %s: the order number, formatted the same way core formats it elsewhere on this page (e.g. "#1024"). */
			esc_html__( 'Order %s', 'woodev-base-theme' ),
			esc_html( _x( '#', 'hash before order number', 'woocommerce' ) . $order->get_order_number() )
		);
		?>
	</span>
</nav>

<div class="wtb-order-head">
	<h2 class="wtb-order-head__title">
		<?php
		printf(
			/* translators: %s: the order number, formatted the same way core formats it elsewhere on this page (e.g. "#1024"). */
			esc_html__( 'Order %s', 'woodev-base-theme' ),
			esc_html( _x( '#', 'hash before order number', 'woocommerce' ) . $order->get_order_number() )
		);
		?>
	</h2>
	<?php echo Account::status_badge( $order->get_status() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Account::status_badge() escapes its own output. ?>
</div>

<div class="wtb-order-meta">
	<div>
		<p class="wtb-order-meta__l"><?php esc_html_e( 'Placed', 'woodev-base-theme' ); ?></p>
		<p class="wtb-order-meta__v"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></p>
	</div>
	<div>
		<p class="wtb-order-meta__l"><?php esc_html_e( 'Payment method', 'woodev-base-theme' ); ?></p>
		<p class="wtb-order-meta__v">
			<?php
			echo $order->get_payment_method_title()
				? wp_kses_post( $order->get_payment_method_title() )
				: esc_html__( '—', 'woodev-base-theme' );
			?>
		</p>
	</div>
	<div>
		<p class="wtb-order-meta__l"><?php esc_html_e( 'Shipping method', 'woodev-base-theme' ); ?></p>
		<p class="wtb-order-meta__v">
			<?php
			echo $order->get_shipping_method()
				? esc_html( $order->get_shipping_method() )
				: esc_html__( '—', 'woodev-base-theme' );
			?>
		</p>
	</div>
	<div>
		<p class="wtb-order-meta__l"><?php esc_html_e( 'Total', 'woocommerce' ); ?></p>
		<p class="wtb-order-meta__v"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
	</div>
</div>

<?php if ( $wtb_notes ) : ?>
	<h2><?php esc_html_e( 'Order updates', 'woocommerce' ); ?></h2>
	<ol class="woocommerce-OrderUpdates commentlist notes">
		<?php foreach ( $wtb_notes as $wtb_note ) : ?>
		<li class="woocommerce-OrderUpdate comment note">
			<div class="woocommerce-OrderUpdate-inner comment_container">
				<div class="woocommerce-OrderUpdate-text comment-text">
					<?php
					/*
					 * Core hardcodes an English-shaped format here (`l jS \o\f F
					 * Y, h:ia`) as a TRANSLATABLE string, so every locale has to
					 * translate a date format. This uses the site's own
					 * configured Date/Time formats instead: no new translatable
					 * string, and it matches how dates read everywhere else on
					 * the site the admin has already set up. `esc_html()` wraps
					 * the RESULT — core escapes the format string instead, which
					 * escapes the wrong end of the operation.
					 */
					$wtb_note_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
					?>
					<p class="woocommerce-OrderUpdate-meta meta"><?php echo esc_html( date_i18n( $wtb_note_format, strtotime( $wtb_note->comment_date ) ) ); ?></p>
					<div class="woocommerce-OrderUpdate-description description">
						<?php echo wp_kses_post( wpautop( wptexturize( $wtb_note->comment_content ) ) ); ?>
					</div>
					<div class="clear"></div>
				</div>
				<div class="clear"></div>
			</div>
		</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>

<?php do_action( 'woocommerce_view_order', $order_id ); ?>
