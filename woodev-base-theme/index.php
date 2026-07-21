<?php
/**
 * Main fallback template: the blog list view.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

use Woodev\Theme\Base\Templates\Layout;

get_header();
?>
<div class="wtb-layout<?php echo Layout::has_sidebar() ? ' wtb-layout--has-sidebar' : ''; ?>">
	<div class="wtb-layout__content">
		<?php if ( is_home() && ! is_front_page() ) : ?>
			<header class="wtb-archive-header mb-8">
				<h1 class="wtb-archive-title text-3xl font-semibold tracking-tight"><?php echo esc_html( single_post_title( '', false ) ); ?></h1>
			</header>
		<?php else : ?>
			<?php
			// Front page shows posts: give the view a heading for the a11y tree
			// without visually duplicating the site branding already in the header.
			?>
			<h1 class="sr-only"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/content/loop' ); ?>
	</div>
	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>
<?php
get_footer();
