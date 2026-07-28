<?php
/**
 * Front-page category tiles — mockup §05, CSS ported in T4 as `src/css/adapter/blocks.css`.
 *
 * This is the section that made the whole front page buildable. The mockup fills
 * it with "Кухня · 48 товаров", "Свет · 23 товара" — which reads as invented copy
 * and is not: those are product categories and their counts, and WooCommerce
 * already holds both. So the largest visual block on the approved home page has a
 * real data source and needs no copy from us at all.
 *
 * Renders nothing at all when there is nothing to show: no WooCommerce, or no
 * non-empty product category. A merchandising grid of zero tiles is worse than
 * a front page without one.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Templates\Plate;

if ( ! taxonomy_exists( 'product_cat' ) ) {
	return;
}

/*
 * Six is the mockup's grid, and it is also what the CSS is built for: three
 * columns at desktop, two at 53.75rem, one at 35rem. Top-level categories only
 * — a flat list mixing parents and their children reads as duplication.
 */
$wtb_categories = get_terms(
	[
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'parent'     => 0,
		'number'     => 6,
		'orderby'    => 'count',
		'order'      => 'DESC',
	]
);

if ( is_wp_error( $wtb_categories ) || empty( $wtb_categories ) ) {
	return;
}
?>
<section class="wtb-front-section">
	<?php /* No .wtb-container wrapper: header.php already opens main.wtb-container. */ ?>
	<div class="wtb-section-head">
		<h2><?php esc_html_e( 'Categories', 'woodev-base-theme' ); ?></h2>
	</div>

	<div class="wtb-cat-tiles">
		<?php foreach ( $wtb_categories as $wtb_category ) : ?>
			<?php
			/*
			 * get_term_link() returns WP_Error, not a string, when a term cannot be
			 * resolved — and casting WP_Error to string is a fatal in PHP 8, on the
			 * front page of the site. A tile with nowhere to go is worth losing;
			 * the page is not.
			 */
			$wtb_term_link = get_term_link( $wtb_category );

			if ( is_wp_error( $wtb_term_link ) ) {
				continue;
			}

			$wtb_thumbnail_id = (int) get_term_meta( $wtb_category->term_id, 'thumbnail_id', true );
			?>
			<a class="wtb-cat-tile" href="<?php echo esc_url( $wtb_term_link ); ?>">
				<?php if ( $wtb_thumbnail_id > 0 ) : ?>
					<span class="bg">
						<?php
						// Core's own markup, already escaped.
						echo wp_get_attachment_image( $wtb_thumbnail_id, 'medium', false, [ 'alt' => '' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output.
						?>
					</span>
				<?php else : ?>
					<?php
					/*
					 * A category with no image is the common case — WooCommerce
					 * does not ask for one — so the tile that renders without it
					 * IS the default look, not a degraded one. The plate is the
					 * mockup's own tile art, picked by term id so a given
					 * category keeps the same illustration across renders, and
					 * placed as an object in the lower right rather than as a
					 * full-bleed background (blocks.css, `.bg--plate`), which is
					 * what keeps the label readable over it.
					 */
					?>
					<span class="bg bg--plate">
						<?php
						// Our own generated SVG, already escaped.
						echo Plate::render( Plate::tile_variant( $wtb_category->term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plate's own generated SVG.
						?>
					</span>
				<?php endif; ?>

				<span class="arrow"><?php woodev_base_icon( 'chevron-right', [ 'size' => 16 ] ); ?></span>

				<?php
				/*
				 * A div, not the mockup's span: a span accepts phrasing content only,
				 * and an h3 is flow content, so the mockup's markup is invalid HTML
				 * and gives the accessibility tree something to guess at.
				 *
				 * `wtb-tile-label`, not the mockup's bare `.label`, because Basecoat
				 * ships `.label` as its FORM LABEL component and the tile was
				 * inheriting `align-items: center` and `user-select: none` from it.
				 * blocks.css selects the renamed class; e2e:woo asserts the computed
				 * alignment, since neither the markup nor phpcs can see this.
				 */
				?>
				<div class="wtb-tile-label">
					<h3><?php echo esc_html( $wtb_category->name ); ?></h3>
					<?php
					/*
					 * Count-agnostic phrasing rather than _n(): Russian has three
					 * plural forms and gettext's two-form call cannot express them
					 * (AGENTS.md). number_format_i18n() localises the digits.
					 */
					?>
					<span class="count">
						<?php
						printf(
							/* translators: %s: number of products in the category, already localized. */
							esc_html__( 'Products: %s', 'woodev-base-theme' ),
							esc_html( number_format_i18n( (int) $wtb_category->count ) )
						);
						?>
					</span>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</section>
