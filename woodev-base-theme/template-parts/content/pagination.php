<?php
/**
 * Content part: pagination for list views.
 *
 * The numbered links carry a visible page number as their accessible name, so
 * their chevron-free markup is fine. The previous/next links, however, are
 * ONLY a chevron — and the icon is decorative (woodev_base_icon() / Icons::get()
 * emits aria-hidden when given no label), so without extra text those links
 * would have no accessible name at all. Each therefore carries a visually
 * hidden label. Icons::get() (not the woodev_base_icon() echo wrapper) is used
 * because prev_text/next_text need the markup as a string, not output.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

use Woodev\Theme\Base\Icons;

the_posts_pagination(
	[
		'mid_size'  => 1,
		'prev_text' => Icons::get( 'chevron-left' ) . '<span class="sr-only">' . esc_html__( 'Previous page', 'woodev-base-theme' ) . '</span>',
		'next_text' => Icons::get( 'chevron-right' ) . '<span class="sr-only">' . esc_html__( 'Next page', 'woodev-base-theme' ) . '</span>',
	]
);
