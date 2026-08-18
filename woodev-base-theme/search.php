<?php
/**
 * Search results template.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Templates\Layout;

get_header();
?>
<div class="wtb-layout<?php echo Layout::has_sidebar() ? ' wtb-layout--has-sidebar wtb-layout--sidebar-' . esc_attr( Layout::sidebar_position() ) : ''; ?>">
	<div class="wtb-layout__content">
		<header class="wtb-archive-header mb-8">
			<h1 class="wtb-archive-title wtb-search-summary">
				<?php
				printf(
					/* translators: %s: the submitted search query. */
					esc_html__( 'Search results for: %s', 'woodev-base-theme' ),
					'<strong>' . esc_html( get_search_query() ) . '</strong>'
				);
				?>
			</h1>
		</header>

		<div class="wtb-search-form">
			<?php get_search_form( [ 'aria_label' => __( 'Search the site', 'woodev-base-theme' ) ] ); ?>
		</div>

		<?php get_template_part( 'template-parts/content/search-results' ); ?>
	</div>
	<?php get_template_part( 'template-parts/sidebar' ); ?>
</div>
<?php
get_footer();
