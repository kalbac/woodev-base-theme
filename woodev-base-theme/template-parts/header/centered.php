<?php
/**
 * Header variant: branding stacked above a centred navigation row.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
<header class="wtb-header wtb-header--centered">
	<div class="wtb-container wtb-header__bar">
		<a class="wtb-wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>

		<?php get_template_part( 'template-parts/header/navigation' ); ?>

		<div class="wtb-header__actions">
			<?php get_template_part( 'template-parts/header/scheme-toggle' ); ?>
		</div>
	</div>
</header>
