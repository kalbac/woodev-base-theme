<?php
/**
 * Footer variant: three widget-area columns above the shared bottom bar.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
<footer class="wtb-footer">
	<div class="wtb-container">
		<?php if ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) ) : ?>
			<div class="wtb-footer__grid">
				<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
					<div class="wtb-footer__column">
						<?php dynamic_sidebar( 'footer-1' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( is_active_sidebar( 'footer-2' ) ) : ?>
					<div class="wtb-footer__column">
						<?php dynamic_sidebar( 'footer-2' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( is_active_sidebar( 'footer-3' ) ) : ?>
					<div class="wtb-footer__column">
						<?php dynamic_sidebar( 'footer-3' ); ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="wtb-footer__bottom">
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
	</div>
</footer>
