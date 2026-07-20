<?php
/**
 * The optional right sidebar, rendered only when the layout resolver allows it.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

use Woodev\Theme\Base\Templates\Layout;

if ( ! Layout::has_sidebar() ) {
	return;
}
?>
<aside class="wtb-sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'woodev-base-theme' ); ?>">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
