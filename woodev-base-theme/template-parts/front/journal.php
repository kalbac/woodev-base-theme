<?php
/**
 * Front-page journal teaser — mockup §05, sourced from published posts.
 *
 * The front page gets its own three-card treatment instead of reusing the
 * archive excerpt partial: the archive card carries the archive's complete
 * vocabulary, while this section is a deliberately compact editorial teaser.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Templates\Plate;

$wtb_journal = new WP_Query(
	[
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	]
);

if ( ! $wtb_journal->have_posts() ) {
	return;
}

$wtb_posts_url = get_permalink( (int) get_option( 'page_for_posts' ) );

if ( ! is_string( $wtb_posts_url ) || '' === $wtb_posts_url ) {
	$wtb_posts_url = get_post_type_archive_link( 'post' );
}

$wtb_post_plates = [ 'post-a', 'post-b', 'post-c' ];
?>
<section class="wtb-front-section wtb-front-journal">
	<div class="wtb-section-head">
		<h2><?php esc_html_e( 'Journal', 'woodev-base-theme' ); ?></h2>
		<?php if ( is_string( $wtb_posts_url ) && '' !== $wtb_posts_url ) : ?>
			<a class="wtb-section-head__more" href="<?php echo esc_url( $wtb_posts_url ); ?>">
				<?php esc_html_e( 'All articles', 'woodev-base-theme' ); ?>
				<?php woodev_base_icon( 'chevron-right', [ 'size' => 16 ] ); ?>
			</a>
		<?php endif; ?>
	</div>

	<div class="wtb-front-editorial">
		<?php while ( $wtb_journal->have_posts() ) : ?>
			<?php
			$wtb_journal->the_post();
			$wtb_post_id       = (int) get_the_ID();
			$wtb_post_date     = get_the_date();
			$wtb_post_date_iso = get_the_date( DATE_W3C );
			$wtb_post_date     = is_string( $wtb_post_date ) ? $wtb_post_date : '';
			$wtb_post_date_iso = is_string( $wtb_post_date_iso ) ? $wtb_post_date_iso : '';
			$wtb_categories    = get_the_category();
			$wtb_category      = is_array( $wtb_categories ) && [] !== $wtb_categories ? $wtb_categories[0]->name : '';
			$wtb_thumbnail     = has_post_thumbnail()
				? get_the_post_thumbnail(
					$wtb_post_id,
					'medium_large',
					[
						'class' => 'wtb-front-editorial__image',
						'alt'   => '',
					]
				)
				: '';
			$wtb_plate         = Plate::render( $wtb_post_plates[ $wtb_post_id % count( $wtb_post_plates ) ] );
			?>
			<article <?php post_class( 'wtb-front-editorial__card card' ); ?>>
				<div class="wtb-front-editorial__thumb">
					<?php
					// Core's own thumbnail markup, or Plate's generated SVG; both are escaped at source.
					echo '' !== $wtb_thumbnail ? $wtb_thumbnail : $wtb_plate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core thumbnail or Plate's own generated SVG.
					?>
				</div>
				<div class="wtb-front-editorial__body">
					<p class="wtb-front-editorial__meta">
					<time datetime="<?php echo esc_attr( $wtb_post_date_iso ); ?>"><?php echo esc_html( $wtb_post_date ); ?></time>
						<?php if ( '' !== $wtb_category ) : ?>
							<span aria-hidden="true">·</span><span><?php echo esc_html( $wtb_category ); ?></span>
						<?php endif; ?>
					</p>
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<?php if ( '' !== trim( get_the_excerpt() ) ) : ?>
						<p class="wtb-front-editorial__excerpt"><?php echo wp_kses_post( get_the_excerpt() ); ?></p>
					<?php endif; ?>
					<a class="wtb-entry-more" href="<?php the_permalink(); ?>">
						<?php esc_html_e( 'Read article', 'woodev-base-theme' ); ?>
						<?php woodev_base_icon( 'chevron-right', [ 'size' => 15 ] ); ?>
					</a>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</section>
<?php wp_reset_postdata(); ?>
