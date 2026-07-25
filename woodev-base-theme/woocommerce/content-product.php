<?php
/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Woodev Base override: re-parents the loop item into the theme's card
 * vocabulary (adds the `wtb-product-card card` classes, a body wrapper and an
 * out-of-stock badge) while calling the exact same hooks in the same order as
 * core. It is audited against WooCommerce on each major so the hook contract
 * and version below stay in sync with upstream.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}
?>
<li <?php wc_product_class( 'wtb-product-card card', $product ); ?>>
	<?php
	// Opens the loop-product anchor, then the sale flash and thumbnail — all
	// emitted INSIDE that anchor by core's hooks.
	do_action( 'woocommerce_before_shop_loop_item' );
	do_action( 'woocommerce_before_shop_loop_item_title' );
	?>
	<?php if ( ! $product->is_in_stock() ) : ?>
		<span class="wtb-stock-badge badge" data-variant="secondary"><?php esc_html_e( 'Out of stock', 'woodev-base-theme' ); ?></span>
	<?php endif; ?>
	<div class="wtb-product-card__body">
		<?php
		// Title, rating and price — still inside the anchor opened above.
		do_action( 'woocommerce_shop_loop_item_title' );
		do_action( 'woocommerce_after_shop_loop_item_title' );
		?>
	</div>
	<?php
	// Closes the anchor (priority 5), then prints the add-to-cart button
	// (priority 10) as a direct child of this <li>.
	do_action( 'woocommerce_after_shop_loop_item' );
	?>
</li>
