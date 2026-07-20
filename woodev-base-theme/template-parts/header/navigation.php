<?php
/**
 * Shared primary navigation, used by both header variants.
 *
 * Deliberately plain and server-rendered — no markup here assumes JS. Task 4
 * (M1-02) enhances this with a mobile drawer, Alpine state and a focus trap
 * without touching the header variants that call it.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

if ( ! has_nav_menu( 'primary' ) ) {
	return;
}

wp_nav_menu(
	[
		'theme_location'       => 'primary',
		'container'            => 'nav',
		'container_class'      => 'wtb-nav',
		'container_aria_label' => __( 'Primary', 'woodev-base-theme' ),
		'menu_class'           => 'wtb-nav__menu flex flex-wrap items-center gap-6 list-none',
		'fallback_cb'          => false,
	]
);
