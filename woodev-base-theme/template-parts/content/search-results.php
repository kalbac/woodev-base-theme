<?php
/**
 * Compact search-result rows with the main WordPress query and pagination.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( have_posts() ) {
	echo '<div class="wtb-search-results">';

	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content/search-result' );
	}

	echo '</div>';

	get_template_part( 'template-parts/content/pagination' );
} else {
	get_template_part( 'template-parts/content/content-none', null, [ 'show_search_form' => false ] );
}
