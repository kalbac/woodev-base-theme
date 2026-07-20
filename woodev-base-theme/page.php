<?php
/**
 * Static page template.
 *
 * Layout::has_sidebar() always returns false while is_page() is true (spec
 * §7 scopes the optional sidebar to blog/archive/single contexts), so the
 * shared sidebar partial below simply renders nothing on a page — the
 * wrapper stays for consistency with the other templates rather than being
 * special-cased.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

use Woodev\Theme\Base\Templates\Layout;

get_header();
?>
<div class="wtb-layout<?php echo Layout::has_sidebar() ? ' wtb-layout--has-sidebar' : ''; ?>">
	<div class="wtb-layout__content">
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
