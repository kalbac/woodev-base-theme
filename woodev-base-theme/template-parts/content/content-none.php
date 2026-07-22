<?php
/**
 * Content part: the empty state when no posts match the current query.
 *
 * Assumes it is called instead of the loop, on a view that already carries
 * its own page-level heading (archive/search title) — so this part's own
 * heading stays one level down, at h2.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
<div class="wtb-no-results alert">
	<?php woodev_base_icon( 'search' ); ?>
	<h2><?php esc_html_e( 'Nothing found', 'woodev-base-theme' ); ?></h2>
	<section>
		<?php if ( is_search() ) : ?>
			<p><?php esc_html_e( 'No results matched your search. Try different keywords.', 'woodev-base-theme' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing here yet. Check back soon.', 'woodev-base-theme' ); ?></p>
		<?php endif; ?>
	</section>
</div>

<?php if ( is_search() ) : ?>
	<div class="mt-4">
		<?php get_search_form(); ?>
	</div>
<?php endif; ?>
