<?php
/**
 * Content part: post summary for list views (index, archive, search).
 *
 * Expects the loop to be active ( the_post() already called by the caller ).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;
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
		<a class="wtb-entry-more" href="<?php the_permalink(); ?>">
			<?php
			/*
			 * One string, not two. The visible label and the screen-reader tail are one
			 * sentence, and splitting them handed the translator a fragment that began
			 * with a space — unreorderable, and leading whitespace is exactly what a PO
			 * editor silently trims. The markup travels inside the string (WordPress
			 * canon, cf. core's "Continue reading" link) so a translation can put the
			 * title first; wp_kses bounds what a translation is allowed to inject.
			 */
			printf(
				wp_kses(
					/* translators: %s: post title. */
					__( 'Read more<span class="sr-only"> about &ldquo;%s&rdquo;</span>', 'woodev-base-theme' ),
					[ 'span' => [ 'class' => [] ] ]
				),
				esc_html( get_the_title() )
			);
			?>
			<?php woodev_base_icon( 'chevron-right' ); ?>
		</a>
	</footer>
</article>
