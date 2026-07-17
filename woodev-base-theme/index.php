<?php
/**
 * Main fallback template.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

get_header();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		?>
		<article <?php post_class( 'mb-8' ); ?>>
			<h2 class="text-xl font-semibold">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
			</h2>
			<div class="mt-2"><?php the_excerpt(); ?></div>
		</article>
		<?php
	}
} else {
	?>
	<p><?php esc_html_e( 'Nothing found.', 'woodev-base-theme' ); ?></p>
	<?php
}

get_footer();
