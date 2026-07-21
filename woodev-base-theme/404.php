<?php
/**
 * 404 template: nothing matched the request.
 *
 * Full width, no sidebar: a "not found" page is not the blog/archive/single
 * content spec §7 scopes the optional sidebar layout to, so this template
 * skips template-parts/sidebar entirely rather than asking the resolver.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

get_header();
?>
<div class="wtb-layout">
	<div class="wtb-layout__content">
		<h1 class="wtb-archive-title text-3xl font-semibold tracking-tight">
			<?php esc_html_e( 'Page not found', 'woodev-base-theme' ); ?>
		</h1>

		<p class="mt-2 text-[var(--muted-foreground)]">
			<?php esc_html_e( 'The page you were looking for could not be found. Try a search instead.', 'woodev-base-theme' ); ?>
		</p>

		<div class="mt-4">
			<?php get_search_form(); ?>
		</div>
	</div>
</div>
<?php
get_footer();
