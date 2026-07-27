<?php
/**
 * The optional right sidebar, rendered only when the layout resolver allows it.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Templates\Layout;

if ( ! Layout::has_sidebar() ) {
	return;
}
?>
<aside class="wtb-sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'woodev-base-theme' ); ?>">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
