<?php
/**
 * Content part: pagination for list views.
 *
 * The chevrons are decorative: the_posts_pagination() gives each link a
 * visible page number (or "Previous"/"Next" fallback) as its accessible
 * name, so the icon must not carry its own label — that would announce the
 * name twice. Icons::get() (not the woodev_base_icon() echo wrapper) is used
 * here because prev_text/next_text need the markup as a string, not output.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

use Woodev\Theme\Base\Icons;

the_posts_pagination(
	[
		'mid_size'  => 1,
		'prev_text' => Icons::get( 'chevron-left' ),
		'next_text' => Icons::get( 'chevron-right' ),
	]
);
