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
