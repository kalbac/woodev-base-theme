<?php
/**
 * Archive template: category, tag, date, author and other post-list archives.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

use Woodev\Theme\Base\Templates\Layout;

get_header();
?>
<div class="wtb-layout<?php echo Layout::has_sidebar() ? ' wtb-layout--has-sidebar' : ''; ?>">
	<div class="wtb-layout__content">
		<header class="wtb-archive-header mb-8">
			<h1 class="wtb-archive-title text-3xl font-semibold tracking-tight">
				<?php the_archive_title(); ?>
			</h1>
			<div class="wtb-archive-description mt-2 text-[var(--muted-foreground)]">
				<?php the_archive_description(); ?>
			</div>
		</header>

		<?php get_template_part( 'template-parts/content/loop' ); ?>
	</div>
	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>
<?php
get_footer();
