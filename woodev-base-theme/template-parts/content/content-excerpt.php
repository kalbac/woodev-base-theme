<?php
/**
 * Content part: post summary for list views (index, archive, search).
 *
 * Expects the loop to be active ( the_post() already called by the caller ).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'wtb-entry wtb-entry--excerpt wtb-entry-card card' ); ?>>
	<?php
	// FIRST child on purpose: Basecoat rounds `.card > img:first-child` to the
	// card's top corners. A wrapping <div> would break that contract.
	if ( has_post_thumbnail() ) {
		the_post_thumbnail( 'medium_large', [ 'alt' => '' ] );
	}
	?>

	<header>
		<h2 class="wtb-entry-title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>

		<p class="wtb-entry-meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
				<?php echo esc_html( get_the_date() ); ?>
			</time>
		</p>

		<?php woodev_base_category_badges(); ?>
	</header>

	<section class="wtb-entry-summary">
		<?php the_excerpt(); ?>
	</section>

	<footer>
		<a class="wtb-entry-more btn" href="<?php the_permalink(); ?>">
			<?php esc_html_e( 'Read more', 'woodev-base-theme' ); ?>
			<span class="sr-only">
				<?php
				printf(
					/* translators: %s: post title. */
					esc_html__( ' about "%s"', 'woodev-base-theme' ),
					esc_html( get_the_title() )
				);
				?>
			</span>
		</a>
	</footer>
</article>
