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
		<span class="wtb-stock-badge badge" data-variant="secondary"><?php esc_html_e( 'Out of stock', 'woocommerce' ); ?></span>
	<?php endif; ?>
	<div class="wtb-product-card__body">
		<?php
		// Category eyebrow above the title (mockup `span.cat`, line 1989). Woo
		// prints no category anywhere in the loop, so there is no hook to filter
		// — it is emitted here, in the one loop template this theme owns.
		//
		// The FIRST term, not a list: the mockup's card shows one short line of
		// context above the title, and a product filed under four categories
		// would otherwise push the title down a row and break the grid's
		// alignment.
		//
		// "First" here means first in WordPress's own term ordering —
		// `get_the_terms()` defers to `wp_get_object_terms()`, which orders by
		// NAME by default, not by the order an editor assigned them and not by
		// term id. So on a product in several categories this is alphabetical,
		// which is arbitrary but deterministic; there is no "primary category"
		// concept in core to prefer instead (the plugins that add one store it
		// in their own meta). An earlier version of this comment claimed the
		// order was term_id and that an editor controlled it by assignment
		// order — both wrong, and caught by the s18 critic pass.
		$wtb_card_terms = get_the_terms( $product->get_id(), 'product_cat' );

		if ( is_array( $wtb_card_terms ) && [] !== $wtb_card_terms ) :
			$wtb_card_term = reset( $wtb_card_terms );
			?>
			<span class="wtb-product-card__cat"><?php echo esc_html( $wtb_card_term->name ); ?></span>
			<?php
		endif;

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
