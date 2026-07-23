<?php
/**
 * Template tags — thin, escaping-safe wrappers for use inside templates.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

use Woodev\Theme\Base\Icons;

/**
 * Echo an inline Lucide icon.
 *
 * The SVG is assembled attribute-by-attribute in Icons::get() with esc_attr()
 * on every value, and its inner markup comes from a vendored file in the theme
 * — not from user input. Escaping the result again would destroy it, so this is
 * deliberately unescaped output of already-escaped markup.
 *
 * @param string               $name Icon slug.
 * @param array<string, mixed> $args See Icons::get().
 */
function woodev_base_icon( string $name, array $args = [] ): void {
	echo Icons::get( $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Assembled from esc_attr()'d attributes and a vendored SVG; see the docblock.
}

/**
 * Echo the current post's categories as Basecoat badge links.
 *
 * Built from get_the_category() rather than get_the_category_list(), which
 * returns finished markup with no way to put a class on the anchors.
 *
 * `secondary` rather than the default accent variant on purpose: a row of
 * accent-coloured chips under every title competes with the accent's actual
 * job on the page, which is the call to action.
 */
function woodev_base_category_badges(): void {
	$categories = get_the_category();

	if ( empty( $categories ) ) {
		return;
	}

	echo '<div class="wtb-entry-categories">';

	foreach ( $categories as $category ) {
		printf(
			'<a class="badge" data-variant="secondary" href="%1$s">%2$s</a>',
			esc_url( get_category_link( $category->term_id ) ),
			esc_html( $category->name )
		);
	}

	echo '</div>';
}
