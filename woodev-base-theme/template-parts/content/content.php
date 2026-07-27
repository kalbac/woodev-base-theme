<?php
/**
 * Content part: full post markup for singular views.
 *
 * Expects the loop to be active ( the_post() already called by the caller ).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/*
 * `$args` is get_template_part()'s third parameter (WP 5.5+; the theme's floor
 * is 6.8). front-page.php passes `hide_entry_head` because the hero above it
 * has already rendered the page's title as the document's <h1> and its featured
 * image as the hero art — printing them again here gave a static front page two
 * <h1>s and the same photograph twice. `isset()` rather than `??` because a
 * template loaded outside get_template_part() has no `$args` at all.
 */
$wtb_hide_entry_head = isset( $args['hide_entry_head'] ) && true === $args['hide_entry_head'];
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'wtb-entry mb-8' ); ?>>
	<?php if ( ! $wtb_hide_entry_head ) : ?>
		<header class="wtb-entry-header mb-4">
			<h1 class="wtb-entry-title"><?php the_title(); ?></h1>

			<div class="wtb-entry-meta">
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
	<?php endif; ?>

	<?php if ( ! $wtb_hide_entry_head && has_post_thumbnail() ) : ?>
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
