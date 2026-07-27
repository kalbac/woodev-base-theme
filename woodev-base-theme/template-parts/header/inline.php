<?php
/**
 * Header variant: branding on the left, navigation on the right, one row.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;
?>
<header class="wtb-header">
	<div class="wtb-container wtb-header__bar">
		<a class="wtb-wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>

		<?php get_template_part( 'template-parts/header/navigation' ); ?>

		<div class="wtb-header__actions">
			<?php get_template_part( 'template-parts/header/scheme-toggle' ); ?>
		</div>
	</div>
</header>
