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
<section class="wtb-no-results mb-8">
	<h2 class="text-2xl font-semibold"><?php esc_html_e( 'Nothing found', 'woodev-base-theme' ); ?></h2>

	<?php if ( is_search() ) : ?>
		<p class="mt-2 text-[var(--muted-foreground)]">
			<?php esc_html_e( 'No results matched your search. Try different keywords.', 'woodev-base-theme' ); ?>
		</p>

		<div class="mt-4">
			<?php get_search_form(); ?>
		</div>
	<?php else : ?>
		<p class="mt-2 text-[var(--muted-foreground)]">
			<?php esc_html_e( 'Nothing here yet. Check back soon.', 'woodev-base-theme' ); ?>
		</p>
	<?php endif; ?>
</section>
