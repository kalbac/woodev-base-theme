<?php
/**
 * Main fallback template: the blog list view.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Templates\Breadcrumbs;
use Woodev\Theme\Base\Templates\Layout;

get_header();
?>
<div class="wtb-layout<?php echo Layout::has_sidebar() ? ' wtb-layout--has-sidebar wtb-layout--sidebar-' . esc_attr( Layout::sidebar_position() ) : ''; ?>">
	<div class="wtb-layout__content">
		<?php if ( is_home() ) : ?>
			<header class="wtb-archive-header mb-8">
				<?php Breadcrumbs::render(); ?>
				<h1 class="wtb-archive-title"><?php echo esc_html( single_post_title( '', false ) ); ?></h1>
				<?php if ( '' !== get_bloginfo( 'description', 'display' ) ) : ?>
					<p class="wtb-journal-description"><?php echo esc_html( get_bloginfo( 'description', 'display' ) ); ?></p>
				<?php endif; ?>
				<?php
				$wtb_journal_categories = get_categories(
					[
						'hide_empty' => true,
						'number'     => 8,
					]
				);
				if ( count( $wtb_journal_categories ) > 1 ) :
					?>
					<nav class="wtb-journal-categories" aria-label="<?php esc_attr_e( 'Journal categories', 'woodev-base-theme' ); ?>">
						<?php foreach ( $wtb_journal_categories as $wtb_journal_category ) : ?>
							<a href="<?php echo esc_url( get_category_link( $wtb_journal_category->term_id ) ); ?>">
								<?php echo esc_html( $wtb_journal_category->name ); ?>
							</a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/content/loop' ); ?>
	</div>
	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>
<?php
get_footer();
