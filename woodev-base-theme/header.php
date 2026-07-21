<?php
/**
 * Header template.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="wtb-skip-link" href="#wtb-content"><?php esc_html_e( 'Skip to content', 'woodev-base-theme' ); ?></a>

<?php get_template_part( 'template-parts/header/' . \Woodev\Theme\Base\Templates\Layout::header_variant() ); ?>

<main id="wtb-content" class="wtb-container">
