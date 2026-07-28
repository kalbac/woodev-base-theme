<?php
/**
 * Single Product Meta
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/meta.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Woodev Base override: reshapes the flat `<div class="product_meta">` into
 * the mockup's `<dl>` definition list (SKU / Category / Tags, one row each).
 * Core's category/tag lines interleave `, `-joined links with a bare-text
 * label baked into the SAME string (`wc_get_product_category_list()`'s
 * `$before`/`$after` args), which cannot be split into a `<dt>`/`<dd>` pair
 * by CSS alone — hence a template override rather than a hook. `dt`/`dd`
 * are pulled apart by passing empty `$before`/`$after` and supplying our own
 * `<dt>` labels instead. The `product_meta` / `sku` / `posted_in` /
 * `tagged_as` classes are kept because third-party plugins and Woo's own
 * CSS key off them; `woocommerce_product_meta_start` / `_end` still fire in
 * the same positions relative to the printed rows so a plugin hooking
 * either one still lands inside the same element. A `<div>` between `<dl>`
 * and `<dt>` is valid HTML (the HTML Standard's `<dl>` content model is
 * zero-or-more groups of `<dt>`+`<dd>`, each optionally wrapped in a
 * `<div>`) and is what the mockup itself does.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     9.7.0
 */

use Automattic\WooCommerce\Enums\ProductType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

$wtb_category_list = wc_get_product_category_list( $product->get_id(), ', ', '', '' );
$wtb_tag_list      = wc_get_product_tag_list( $product->get_id(), ', ', '', '' );
?>
<dl class="wtb-product-meta product_meta">

	<?php do_action( 'woocommerce_product_meta_start' ); ?>

	<?php if ( wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( ProductType::VARIABLE ) ) ) : ?>
		<div>
			<dt><?php esc_html_e( 'SKU', 'woodev-base-theme' ); ?></dt>
			<?php $wtb_sku = $product->get_sku(); ?>
			<dd class="sku"><?php echo '' !== $wtb_sku ? esc_html( $wtb_sku ) : esc_html__( 'N/A', 'woodev-base-theme' ); ?></dd>
		</div>
	<?php endif; ?>

	<?php if ( is_string( $wtb_category_list ) && '' !== $wtb_category_list ) : ?>
		<div>
			<dt><?php esc_html_e( 'Category', 'woodev-base-theme' ); ?></dt>
			<dd class="posted_in"><?php echo $wtb_category_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_get_product_category_list() returns escaped term-link markup. ?></dd>
		</div>
	<?php endif; ?>

	<?php if ( is_string( $wtb_tag_list ) && '' !== $wtb_tag_list ) : ?>
		<div>
			<dt><?php esc_html_e( 'Tags', 'woodev-base-theme' ); ?></dt>
			<dd class="tagged_as"><?php echo $wtb_tag_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_get_product_tag_list() returns escaped term-link markup. ?></dd>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_product_meta_end' ); ?>

</dl>
