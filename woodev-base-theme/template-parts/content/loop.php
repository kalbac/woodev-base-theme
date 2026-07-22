<?php
/**
 * Shared post list loop: content-excerpt per post, then pagination — or the
 * empty state when the query has no results.
 *
 * Used by index.php, archive.php and search.php, each of which renders its
 * own page-level heading itself; this part starts directly at the loop so
 * that heading stays outside it.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

if ( have_posts() ) {
	echo '<div class="wtb-post-grid">';

	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content/content-excerpt' );
	}

	echo '</div>';

	get_template_part( 'template-parts/content/pagination' );
} else {
	get_template_part( 'template-parts/content/content-none' );
}
