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
<article id="post-<?php the_ID(); ?>" <?php post_class( 'wtb-entry wtb-entry--excerpt mb-8' ); ?>>
	<h2 class="wtb-entry-title text-xl font-semibold">
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>

	<div class="wtb-entry-meta mt-1 text-sm text-[var(--muted-foreground)]">
		<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
			<?php echo esc_html( get_the_date() ); ?>
		</time>
	</div>

	<div class="wtb-entry-summary mt-2">
		<?php the_excerpt(); ?>
	</div>

	<p class="mt-2">
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
	</p>
</article>
