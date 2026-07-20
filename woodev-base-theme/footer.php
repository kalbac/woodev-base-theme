<?php
/**
 * Footer template.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
</main>

<?php get_template_part( 'template-parts/footer/' . \Woodev\Theme\Base\Templates\Layout::footer_variant() ); ?>

<?php wp_footer(); ?>
</body>
</html>
