<?php
/**
 * Footer variant: site name, the footer menu, and a copyright line.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;
?>
<footer class="wtb-footer">
	<div class="wtb-container wtb-footer__simple">
		<p class="wtb-footer__brand"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>

		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<?php
			wp_nav_menu(
				[
					'theme_location'       => 'footer',
					'container'            => 'nav',
					'container_class'      => 'wtb-footer-nav',
					'container_aria_label' => __( 'Footer', 'woodev-base-theme' ),
					'menu_class'           => 'wtb-footer-nav__menu',
					'fallback_cb'          => false,
				]
			);
			?>
		<?php endif; ?>

		<p class="wtb-footer__copy">
			<?php
			printf(
				/* translators: 1: current year, 2: site name. */
				esc_html__( '© %1$s %2$s', 'woodev-base-theme' ),
				esc_html( wp_date( 'Y' ) ),
				esc_html( get_bloginfo( 'name' ) )
			);
			?>
		</p>
	</div>
</footer>
