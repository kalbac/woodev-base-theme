<?php
/**
 * Front-page product picks — mockup §05, ordered by product sales.
 *
 * This is intentionally not a "current week" report. Product sales are the
 * closest native, queryable popularity signal. A calendar-week
 * report needs a plugin-owned query and cache. The editorial label remains useful
 * without inventing that reporting layer, and the section disappears when
 * WooCommerce or products do not exist.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_get_products' ) ) {
	return;
}

$wtb_products = wc_get_products(
	[
		'limit'      => 4,
		'status'     => 'publish',
		'visibility' => 'visible',
		'orderby'    => [
			'meta_value_num' => 'DESC',
			'ID'             => 'DESC',
		],
		'meta_key'   => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- WooCommerce-managed sales counter; the front page reads four visible products.
		'return'     => 'objects',
	]
);

if ( ! is_array( $wtb_products ) ) {
	return;
}

$wtb_products = array_values(
	array_filter(
		$wtb_products,
		static fn ( mixed $product ): bool => $product instanceof \WC_Product && $product->is_visible()
	)
);

if ( [] === $wtb_products ) {
	return;
}

$wtb_shop_url = wc_get_page_permalink( 'shop' );
global $product, $post, $woocommerce_loop;

$wtb_original_product = $product ?? null;
$wtb_original_post    = $post ?? null;
$wtb_original_loop    = $woocommerce_loop ?? null;
?>
<section class="wtb-front-section wtb-front-products woocommerce">
	<div class="wtb-section-head">
		<h2><?php esc_html_e( "Week's picks", 'woodev-base-theme' ); ?></h2>
		<?php if ( '' !== $wtb_shop_url ) : ?>
			<a class="wtb-section-head__more" href="<?php echo esc_url( $wtb_shop_url ); ?>">
				<?php esc_html_e( 'Shop all', 'woodev-base-theme' ); ?>
				<?php woodev_base_icon( 'chevron-right', [ 'size' => 16 ] ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php woocommerce_product_loop_start(); ?>
	<?php foreach ( $wtb_products as $wtb_product ) : ?>
		<?php
		$post = get_post( $wtb_product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WooCommerce's loop template reads the current product post through the documented global.

		if ( ! $post instanceof \WP_Post ) {
			continue;
		}

		$product = $wtb_product;
		setup_postdata( $post );
		wc_get_template_part( 'content', 'product' );
		?>
	<?php endforeach; ?>
	<?php woocommerce_product_loop_end(); ?>
</section>
<?php
wp_reset_postdata();

if ( null === $wtb_original_post ) {
	unset( $GLOBALS['post'] );

	foreach ( [ 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' ] as $wtb_postdata_global ) {
		unset( $GLOBALS[ $wtb_postdata_global ] );
	}
} else {
	$post = $wtb_original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore the front-page post after rendering the isolated Woo loop.
	setup_postdata( $post );
}

if ( null === $wtb_original_product ) {
	unset( $GLOBALS['product'] );
} else {
	$product = $wtb_original_product;
}

if ( null === $wtb_original_loop ) {
	unset( $GLOBALS['woocommerce_loop'] );
} else {
	$woocommerce_loop = $wtb_original_loop; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore WooCommerce loop state after rendering the isolated front-page loop.
}
