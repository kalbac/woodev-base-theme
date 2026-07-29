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
 * The checkout page — a WP page like any other — renders through this same
 * template, order-received endpoint included. `Receipt::hero()` prints its
 * own `<h1>` on `woocommerce_before_thankyou` (see
 * `inc/Woo/Receipt.php`), so on that one page `hide_entry_head` is passed to
 * `template-parts/content/content`, the same `$args` contract
 * `front-page.php` already uses for its own hero — otherwise the page would
 * carry two `<h1>`s, the exact class of defect the front page already
 * guards against (s16). `function_exists()` because this template also runs
 * with WooCommerce inactive, where `is_order_received_page()` does not exist.
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
<div class="wtb-layout<?php echo Layout::has_sidebar() ? ' wtb-layout--has-sidebar' : ''; ?>">
	<div class="wtb-layout__content">
		<?php
		while ( have_posts() ) {
			the_post();

			$wtb_content_args = ( function_exists( 'is_order_received_page' ) && is_order_received_page() )
				? [ 'hide_entry_head' => true ]
				: [];

			get_template_part( 'template-parts/content/content', null, $wtb_content_args );

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
