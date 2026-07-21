<?php
/**
 * Search results template.
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
				<?php
				printf(
					/* translators: %s: the submitted search query. */
					esc_html__( 'Search results for: %s', 'woodev-base-theme' ),
					esc_html( get_search_query() )
				);
				?>
			</h1>
		</header>

		<?php get_template_part( 'template-parts/content/loop' ); ?>
	</div>
	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>
<?php
get_footer();
