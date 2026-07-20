<?php
/**
 * Footer variant: site name, the footer menu, and a copyright line.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
<footer class="wtb-footer border-t border-[var(--border)]">
	<div class="wtb-container flex flex-col items-center gap-2 text-sm text-[var(--muted-foreground)]">
		<p class="font-semibold text-[var(--foreground)]"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>

		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<?php
			wp_nav_menu(
				[
					'theme_location'       => 'footer',
					'container'            => 'nav',
					'container_class'      => 'wtb-footer-nav',
					'container_aria_label' => __( 'Footer', 'woodev-base-theme' ),
					'menu_class'           => 'wtb-footer-nav__menu flex flex-wrap items-center gap-4 list-none',
					'fallback_cb'          => false,
				]
			);
			?>
		<?php endif; ?>

		<p>
			<?php
			printf(
				/* translators: 1: current year, 2: site name. */
				esc_html__( '© %1$s %2$s', 'woodev-base-theme' ),
				esc_html( number_format_i18n( (int) wp_date( 'Y' ) ) ),
				esc_html( get_bloginfo( 'name' ) )
			);
			?>
		</p>
	</div>
</footer>
