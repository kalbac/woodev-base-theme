<?php
/**
 * Header variant: branding on the left, navigation on the right, one row.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
<header class="wtb-header border-b border-[var(--border)]">
	<div class="wtb-container flex items-center justify-between gap-4">
		<a class="font-semibold" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>

		<?php get_template_part( 'template-parts/header/navigation' ); ?>

		<div class="wtb-header__actions">
			<?php
			// M1-05 inserts the colour-scheme switcher here (spec §6).
			?>
		</div>
	</div>
</header>
