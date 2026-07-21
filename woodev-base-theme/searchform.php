<?php
/**
 * Search form — Basecoat-styled input + button.
 *
 * Overrides core's default get_search_form() output so the field and submit
 * carry Basecoat's `.input` / `.btn` component classes. This is one of the
 * surfaces where the active style pack becomes visible (spec §6–7): packs
 * differ in component shape, so a bare form would look identical across packs.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

$wtb_search_id = 'wtb-search-' . wp_unique_id();

/*
 * get_search_form() passes its $args through to this template, and core's
 * default markup honours `aria_label` — it is how a caller distinguishes two
 * search landmarks on one page. Overriding the template must not silently drop
 * that contract. $args is absent if the file is ever included directly.
 */
$wtb_search_aria_label = isset( $args['aria_label'] ) ? (string) $args['aria_label'] : '';
?>
<form
	role="search"
	<?php if ( '' !== $wtb_search_aria_label ) : ?>
		aria-label="<?php echo esc_attr( $wtb_search_aria_label ); ?>"
	<?php endif; ?>
	method="get"
	class="wtb-search flex gap-2"
	action="<?php echo esc_url( home_url( '/' ) ); ?>"
>
	<label class="sr-only" for="<?php echo esc_attr( $wtb_search_id ); ?>">
		<?php esc_html_e( 'Search for:', 'woodev-base-theme' ); ?>
	</label>
	<input
		type="search"
		id="<?php echo esc_attr( $wtb_search_id ); ?>"
		class="input"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Search &hellip;', 'woodev-base-theme' ); ?>"
	/>
	<button type="submit" class="btn">
		<?php esc_html_e( 'Search', 'woodev-base-theme' ); ?>
	</button>
</form>
