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

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="wtb-layout">
	<div class="wtb-layout__content wtb-error-404">
		<p class="wtb-error-404__code" aria-hidden="true">404</p>

		<div class="wtb-no-results alert">
			<?php woodev_base_icon( 'search' ); ?>
			<h1 class="wtb-archive-title" data-title>
				<?php esc_html_e( 'Page not found', 'woodev-base-theme' ); ?>
			</h1>
			<section>
				<p><?php esc_html_e( 'The page you were looking for could not be found. Try a search instead.', 'woodev-base-theme' ); ?></p>
			</section>
		</div>

		<div class="mt-4">
			<?php get_search_form(); ?>
		</div>
	</div>
</div>
<?php
get_footer();
