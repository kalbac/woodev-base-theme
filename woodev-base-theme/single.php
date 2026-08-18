<?php
/**
 * Single post template.
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
		<?php
		while ( have_posts() ) {
			the_post();
			get_template_part( 'template-parts/content/content' );

			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		}
		?>
	</div>
	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>
<?php
get_footer();
