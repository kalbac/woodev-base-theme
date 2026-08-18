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

use Woodev\Theme\Base\Templates\Layout;

/*
 * `$args` is get_template_part()'s third parameter (WP 5.5+; the theme's floor
 * is 6.8). front-page.php passes `hide_entry_head` because the hero above it
 * has already rendered the page's title as the document's <h1> and its featured
 * image as the hero art — printing them again here gave a static front page two
 * <h1>s and the same photograph twice. `page.php` passes it too, on the
 * order-received screen, where `Woo\Receipt::hero()` prints the <h1>.
 *
 * The `isset()` is not load-bearing and the reason once given for it was
 * false: `$args['hide_entry_head'] ?? false` handles an undefined `$args`
 * and a missing key without a warning, so "a template loaded outside
 * get_template_part() has no `$args`" was never an argument for either form.
 * The explicit `true ===` comparison is the part that matters — a truthy
 * string or `1` should not silently suppress the header.
 */
$wtb_hide_entry_head     = isset( $args['hide_entry_head'] ) && true === $args['hide_entry_head'];
$wtb_is_post             = 'post' === get_post_type();
$wtb_show_featured_image = ! $wtb_is_post || Layout::show_post_featured_image();
$wtb_post_categories     = $wtb_is_post ? get_the_category() : [];
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'wtb-entry mb-8' ); ?>>
	<?php if ( ! $wtb_hide_entry_head ) : ?>
		<header class="wtb-entry-header mb-4">
			<h1 class="wtb-entry-title"><?php the_title(); ?></h1>

			<?php
			/*
			 * A publication date and a byline belong to a POST. On a static
			 * page they are noise at best and wrong at worst — and this
			 * template renders every page, WooCommerce's shortcode pages
			 * included, so before this guard the classic cart read
			 * "WTB Classic Cart / JULY 28, 2026 BY" with an empty author (a
			 * page created by wp-cli has no author display name to print).
			 * Found by looking at the rendered cart, s19 (#42) — no test saw
			 * it, and the mockup draws no meta line on any of the commerce
			 * screens or on its static-page example.
			 *
			 * `'post' === get_post_type()` rather than `is_single()`: core sets
			 * `is_single()` for attachments and every public CPT, which is the
			 * mistake `Layout::has_sidebar()` already records having made once.
			 */
			if ( $wtb_is_post ) :
				?>
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
					<?php if ( ! empty( $wtb_post_categories ) ) : ?>
						<span class="wtb-entry-meta__categories">
							<?php foreach ( $wtb_post_categories as $wtb_category ) : ?>
								<a href="<?php echo esc_url( get_category_link( $wtb_category->term_id ) ); ?>">
									<?php echo esc_html( $wtb_category->name ); ?>
								</a>
							<?php endforeach; ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<?php if ( ! $wtb_hide_entry_head && $wtb_show_featured_image && has_post_thumbnail() ) : ?>
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
