<?php
/**
 * Content part: full post markup for singular views.
 *
 * Expects the loop to be active ( the_post() already called by the caller ).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'wtb-entry mb-8' ); ?>>
	<header class="wtb-entry-header mb-4">
		<h1 class="wtb-entry-title text-3xl font-semibold tracking-tight"><?php the_title(); ?></h1>

		<div class="wtb-entry-meta mt-2 text-sm text-[var(--muted-foreground)]">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
				<?php echo esc_html( get_the_date() ); ?>
			</time>
			<span class="wtb-entry-meta__author">
				<?php
				printf(
					/* translators: %s: post author display name. */
					esc_html__( 'by %s', 'woodev-base-theme' ),
					esc_html( get_the_author() )
				);
				?>
			</span>
		</div>
	</header>

	<?php if ( has_post_thumbnail() ) : ?>
		<div class="wtb-entry-thumbnail mb-6">
			<?php the_post_thumbnail( 'large', [ 'class' => 'w-full h-auto rounded-lg' ] ); ?>
		</div>
	<?php endif; ?>

	<div class="wtb-entry-content">
		<?php the_content(); ?>
	</div>

	<?php
	wp_link_pages(
		[
			'before' => '<nav class="wtb-page-links mt-6" aria-label="' . esc_attr__( 'Page navigation', 'woodev-base-theme' ) . '"><p>',
			'after'  => '</p></nav>',
		]
	);
	?>
</article>
