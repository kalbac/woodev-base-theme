<?php
/**
 * Front page — the mockup's §05 home, assembled from sources the site already has.
 *
 * The merchandising sections above the fold are additive and self-suppressing:
 * each template part returns early when it has no real data to render, so a site
 * with no WooCommerce and no tagline gets exactly what `index.php` rendered
 * before this file existed. That is the whole reason this template is safe to
 * add to a theme that is already installed somewhere.
 *
 * Below the sections, the front page keeps doing what WordPress told it to do:
 * a static page renders its content, a posts front page renders the loop. This
 * template overrides `page.php` for a static front page, so dropping the content
 * would silently swallow whatever the admin wrote there.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

get_header();

get_template_part( 'template-parts/front/hero' );
get_template_part( 'template-parts/front/category-tiles' );
?>
<div class="wtb-container wtb-front-content">
	<?php if ( is_home() ) : ?>
		<?php
		/*
		 * A posts front page. The `sr-only` site-name heading index.php prints
		 * here is deliberately absent: the hero above renders the site name as a
		 * real `<h1>`, so repeating it would give the document two.
		 */
		?>
		<?php get_template_part( 'template-parts/content/loop' ); ?>
	<?php else : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'wtb-entry' ); ?>>
				<div class="wtb-entry-content">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		endwhile;
		?>
	<?php endif; ?>
</div>
<?php
get_footer();
