<?php
/**
 * Archive template: category, tag, date, author and other post-list archives.
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
		<?php Breadcrumbs::render(); ?>
		<header class="wtb-archive-header mb-8">
			<h1 class="wtb-archive-title">
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
