<?php
/**
 * Seed the Woo wp-env site with a self-contained editorial storefront.
 *
 * The file remains development tooling, not distributed theme code. Re-running it
 * updates records by stable identifiers, so the result is repeatable and does not
 * accumulate stale demo data.
 */

// `wp eval-file` evaluates this after its own bootstrap statement, so PHP rejects
// a strict_types declaration here even though the file itself starts correctly.
defined( 'ABSPATH' ) || exit( 1 );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
$woo_activation = activate_plugin( 'woocommerce/woocommerce.php' );
if ( is_wp_error( $woo_activation ) ) {
	throw new RuntimeException( $woo_activation->get_error_message() );
}

switch_theme( 'woodev-base-theme' );

if ( ! function_exists( 'wc_get_product' ) ) {
	throw new RuntimeException( 'WooCommerce must be active before seeding the showcase.' );
}

/**
 * @return int
 */
function wtb_showcase_page( string $title, string $slug, string $content = '' ): int {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$args     = [
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	];

	if ( $existing instanceof WP_Post ) {
		$args['ID'] = $existing->ID;

		return (int) wp_update_post( wp_slash( $args ) );
	}

	return (int) wp_insert_post( wp_slash( $args ) );
}

/**
 * @return int
 */
function wtb_showcase_category( string $name, string $slug ): int {
	$term = get_term_by( 'slug', $slug, 'product_cat' );

	if ( $term instanceof WP_Term ) {
		return (int) $term->term_id;
	}

	$created = wp_insert_term( $name, 'product_cat', [ 'slug' => $slug ] );

	if ( is_wp_error( $created ) ) {
		throw new RuntimeException( $created->get_error_message() );
	}

	return (int) $created['term_id'];
}

/**
 * @param array{0: int, 1: int, 2: int} $palette
 *
 * @return int
 */
function wtb_showcase_image( int $product_id, string $slug, array $palette ): int {
	$old = get_posts(
		[
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'meta_key'       => '_wtb_showcase_image',
			'meta_value'     => $slug,
			'fields'         => 'ids',
			'posts_per_page' => -1,
		]
	);

	foreach ( $old as $attachment_id ) {
		wp_delete_attachment( (int) $attachment_id, true );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$upload     = wp_upload_dir();
	$canvas     = imagecreatetruecolor( 900, 900 );
	[ $red, $green, $blue ] = $palette;
	$background = imagecolorallocate( $canvas, $red, $green, $blue );
	$ink        = imagecolorallocate( $canvas, 246, 241, 232 );
	$shadow     = imagecolorallocate( $canvas, max( 0, $red - 35 ), max( 0, $green - 35 ), max( 0, $blue - 35 ) );
	imagefill( $canvas, 0, 0, $background );
	imagefilledellipse( $canvas, 450, 420, 480, 480, $shadow );
	imagefilledellipse( $canvas, 450, 375, 400, 400, $ink );
	imagefilledrectangle( $canvas, 270, 565, 630, 610, $shadow );
	imagefilledrectangle( $canvas, 325, 175, 575, 235, $ink );

	$filename = sprintf( 'wtb-showcase-%s.png', $slug );
	$path     = trailingslashit( $upload['path'] ) . $filename;
	imagepng( $canvas, $path, 6 );
	imagedestroy( $canvas );

	$attachment_id = wp_insert_attachment(
		[
			'post_mime_type' => 'image/png',
			'post_title'     => sanitize_text_field( str_replace( '-', ' ', $slug ) ),
			'post_status'    => 'inherit',
		],
		$path,
		$product_id
	);
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );
	update_post_meta( $attachment_id, '_wtb_showcase_image', $slug );

	return (int) $attachment_id;
}

/**
 * @param array{name: string, slug: string, sku: string, regular_price: string, sale_price?: string, description: string, short_description: string, palette: array{0: int, 1: int, 2: int}, sales: int} $data
 *
 * @return int
 */
function wtb_showcase_product( array $data, int $category_id ): int {
	$product_id = (int) wc_get_product_id_by_sku( $data['sku'] );
	$product    = $product_id > 0 ? wc_get_product( $product_id ) : new WC_Product_Simple();

	if ( ! $product instanceof WC_Product ) {
		throw new RuntimeException( 'Could not create showcase product ' . $data['sku'] );
	}

	$product->set_name( $data['name'] );
	$product->set_slug( $data['slug'] );
	$product->set_sku( $data['sku'] );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_regular_price( $data['regular_price'] );
	$product->set_sale_price( $data['sale_price'] ?? '' );
	$product->set_description( $data['description'] );
	$product->set_short_description( $data['short_description'] );
	$product->set_category_ids( [ $category_id ] );
	$product->set_stock_status( 'instock' );
	$product->save();

	$image_id = wtb_showcase_image( (int) $product->get_id(), $data['slug'], $data['palette'] );
	$product->set_image_id( $image_id );
	$product->save();
	update_post_meta( $product->get_id(), 'total_sales', (string) $data['sales'] );

	return (int) $product->get_id();
}

/** Remove the test-only records which would otherwise leak into the showcase. */
function wtb_showcase_remove_e2e_records(): void {
	global $wpdb;

	$product_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_name LIKE 'wtb-%'"
	);
	foreach ( $product_ids as $product_id ) {
		wp_delete_post( (int) $product_id, true );
	}

	$post_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_name LIKE 'wtb-journal-%'"
	);
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	$terms = get_terms(
		[
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		]
	);
	foreach ( $terms as $term ) {
		if ( $term instanceof WP_Term && str_starts_with( $term->slug, 'wtb-' ) ) {
			wp_delete_term( $term->term_id, 'product_cat' );
		}
	}
}

wtb_showcase_remove_e2e_records();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::runcommand( 'wc tool run install_pages --user=admin' );
} elseif ( class_exists( 'WC_Install' ) ) {
	WC_Install::create_pages();
}

$shop_page_id = wc_get_page_id( 'shop' );
if ( $shop_page_id < 1 ) {
	throw new RuntimeException( 'WooCommerce did not create the Shop page.' );
}

$journal_category = get_category_by_slug( 'notes' );
$journal_category = $journal_category instanceof WP_Term ? (int) $journal_category->term_id : (int) wp_create_category( 'Notes' );
update_option( 'default_category', $journal_category );

$uncategorized = get_category_by_slug( 'uncategorized' );
if ( $uncategorized instanceof WP_Term ) {
	wp_delete_category( $uncategorized->term_id );
}

$home    = wtb_showcase_page( 'Home', 'home' );
$journal = wtb_showcase_page( 'Journal', 'journal' );
$about   = wtb_showcase_page( 'About us', 'about-us', '<p>Useful things, chosen with care for the rooms you use every day.</p>' );
$contact = wtb_showcase_page( 'Contact', 'contact', '<p>Write to us with a question about an order, a material, or a small detail.</p>' );

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home );
update_option( 'page_for_posts', $journal );
update_option( 'blogname', 'At Home, Slowly' );
update_option( 'blogdescription', 'Well-made tools for everyday rituals.' );
update_option( 'permalink_structure', '/%postname%/' );
// A fresh Woo install enables its own Coming Soon interceptor. It deliberately
// replaces every store route with a launch screen, which makes a valid demo
// catalogue look empty while direct product queries still work.
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );

$posts = [
	[ 'A quieter kitchen', 'showcase-quieter-kitchen', 'A small edit of tools that make a weekday meal feel less rushed.', '2026-08-12 09:00:00' ],
	[ 'The case for a good tray', 'showcase-good-tray', 'A tray is not decoration. It is a boundary for the things you reach for most.', '2026-08-08 09:00:00' ],
	[ 'Objects with staying power', 'showcase-staying-power', 'We look for materials that grow more familiar instead of simply wearing out.', '2026-08-03 09:00:00' ],
];

foreach ( $posts as [ $title, $slug, $excerpt, $date ] ) {
	$existing = get_page_by_path( $slug, OBJECT, 'post' );
	$args     = [
		'post_title'    => $title,
		'post_name'     => $slug,
		'post_excerpt'  => $excerpt,
		'post_content'  => '<p>' . esc_html( $excerpt ) . '</p><p>Thoughtful, uncomplicated and ready to be used every day.</p>',
		'post_date'     => $date,
		'post_date_gmt' => get_gmt_from_date( $date ),
		'post_status'   => 'publish',
		'post_type'     => 'post',
	];

	$post_id = $existing instanceof WP_Post
		? (int) wp_update_post( wp_slash( $args + [ 'ID' => $existing->ID ] ) )
		: (int) wp_insert_post( wp_slash( $args ) );
	wp_set_post_categories( $post_id, [ $journal_category ] );
}

$kitchen  = wtb_showcase_category( 'Kitchen', 'showcase-kitchen' );
$table    = wtb_showcase_category( 'Table', 'showcase-table' );
$textiles = wtb_showcase_category( 'Textiles', 'showcase-textiles' );
$products = [
	[
		'name' => 'Stoneware Pour-Over Set', 'slug' => 'stoneware-pour-over-set', 'sku' => 'SHOWCASE-POUR',
		'regular_price' => '68.00', 'description' => 'A warm, weighty pour-over set for unhurried mornings.',
		'short_description' => 'Stoneware, hand-finished.', 'palette' => [ 176, 110, 78 ], 'sales' => 90, 'category' => $kitchen,
	],
	[
		'name' => 'Linen Table Runner', 'slug' => 'linen-table-runner', 'sku' => 'SHOWCASE-LINEN',
		'regular_price' => '42.00', 'sale_price' => '34.00', 'description' => 'Soft-washed linen that makes an everyday table feel considered.',
		'short_description' => 'European flax, 45 × 180 cm.', 'palette' => [ 143, 153, 105 ], 'sales' => 80, 'category' => $textiles,
	],
	[
		'name' => 'Walnut Serving Tray', 'slug' => 'walnut-serving-tray', 'sku' => 'SHOWCASE-TRAY',
		'regular_price' => '74.00', 'description' => 'A solid walnut tray for tea, letters, and the small things that collect.',
		'short_description' => 'Oiled walnut, made to age well.', 'palette' => [ 110, 78, 57 ], 'sales' => 70, 'category' => $table,
	],
	[
		'name' => 'Glass Pantry Jar', 'slug' => 'glass-pantry-jar', 'sku' => 'SHOWCASE-JAR',
		'regular_price' => '24.00', 'description' => 'A clear, practical jar with a quietly satisfying seal.',
		'short_description' => 'Borosilicate glass, 1.2 L.', 'palette' => [ 88, 124, 130 ], 'sales' => 60, 'category' => $kitchen,
	],
];

$product_ids = [];
foreach ( $products as $product ) {
	$product_ids[] = wtb_showcase_product( $product, $product['category'] );
}

$menu    = wp_get_nav_menu_object( 'Showcase navigation' );
$menu_id = $menu instanceof WP_Term ? (int) $menu->term_id : (int) wp_create_nav_menu( 'Showcase navigation' );
foreach ( wp_get_nav_menu_items( $menu_id ) ?: [] as $item ) {
	wp_delete_post( (int) $item->ID, true );
}
foreach ( [ $home, $journal, $about, $contact ] as $page_id ) {
	wp_update_nav_menu_item(
		$menu_id,
		0,
		[
			'menu-item-object-id' => $page_id,
			'menu-item-object'    => 'page',
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		]
	);
}
$locations            = get_theme_mod( 'nav_menu_locations', [] );
$locations['primary'] = $menu_id;
set_theme_mod( 'nav_menu_locations', $locations );

set_theme_mod( 'front_hero_eyebrow', 'Objects for everyday rituals' );
set_theme_mod( 'front_hero_lede', 'Useful, tactile pieces for a home that feels like your own.' );
set_theme_mod( 'front_hero_trust', "Considered materials | check\nPacked with care | package\nEasy returns | refresh-cw" );
set_theme_mod( 'front_value_items', "Small-batch choices | Useful things, no clutter | leaf\nPacked to travel well | Thoughtful from shelf to door | package\nHere when you need us | Clear help from real people | headphones" );
set_theme_mod( 'front_promo_title', 'Make room for the ritual.' );
set_theme_mod( 'front_promo_text', 'A considered edit of kitchen, table and textile pieces for slower daily routines.' );
set_theme_mod( 'front_promo_cta_label', 'Explore the collection' );
set_theme_mod( 'front_promo_cta_url', get_permalink( wc_get_page_id( 'shop' ) ) );
set_theme_mod( 'product_trust_badge_one', 'Packed with care | package' );
set_theme_mod( 'product_trust_badge_two', 'Easy returns | refresh-cw' );

flush_rewrite_rules();

echo wp_json_encode(
	[
		'home'     => get_permalink( $home ),
		'shop'     => get_permalink( wc_get_page_id( 'shop' ) ),
		'journal'  => get_permalink( $journal ),
		'products' => $product_ids,
	],
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
