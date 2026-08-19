<?php
/**
 * My Account navigation
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/navigation.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Woodev Base override, taken from WooCommerce 10.9.4 (M2): prints a Lucide
 * icon before each nav label — `wc_get_account_menu_items()`'s labels are
 * `esc_html()`'d by core, so no filter can put markup inside one, which is why
 * this is a template override rather than a hook. Preserves
 * `woocommerce_before_account_navigation` / `_after_account_navigation`,
 * `wc_get_account_menu_item_classes()` on the `<li>` and the `aria-current`
 * core sets, in the same order and with the same arguments. The icon comes
 * from `Account::nav_icon()` (single source of truth, shared with nothing else
 * — this is its only call site today, but it lives on `Account` rather than
 * here so the endpoint→icon map has one home). An endpoint with no entry in
 * that map (a plugin-added tab) renders no icon at all — not a fallback glyph
 * — and still renders its label.
 *
 * The `aria-label` below reuses core's own copy under the `woocommerce` text
 * domain rather than re-declaring it under this theme's own: it is on the
 * issue #52 carve-out allowlist in `tests/php/Unit/I18nSourceTest.php`, which
 * grants that domain only to the exact msgids WooCommerce core already ships
 * and translates, and only at a call site under `woodev-base-theme/woocommerce/`.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

use Woodev\Theme\Base\Woo\Account;

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_attr_e( 'Account pages', 'woocommerce' ); ?>">
	<ul>
		<?php foreach ( wc_get_account_menu_items() as $wtb_endpoint => $wtb_label ) : ?>
			<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $wtb_endpoint ) ); ?>">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( $wtb_endpoint ) ); ?>" <?php echo wc_is_current_account_menu_item( $wtb_endpoint ) ? 'aria-current="page"' : ''; ?>>
					<?php echo Account::nav_icon( $wtb_endpoint ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Account::nav_icon() returns theme-controlled, already-safe inline SVG (or '' for an unmapped endpoint). ?>
					<?php echo esc_html( $wtb_label ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
