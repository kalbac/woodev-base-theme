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
<header class="border-b border-[var(--border)]">
	<div class="mx-auto max-w-5xl p-4">
		<a class="font-semibold" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
	</div>
</header>
<main class="mx-auto max-w-5xl p-4">
