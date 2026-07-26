<?php
/**
 * Footer template.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;
?>
</main>

<?php get_template_part( 'template-parts/footer/' . \Woodev\Theme\Base\Templates\Layout::footer_variant() ); ?>

<?php wp_footer(); ?>
</body>
</html>
