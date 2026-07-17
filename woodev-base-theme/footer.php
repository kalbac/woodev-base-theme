<?php
/**
 * Footer template.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);
?>
</main>
<footer class="border-t border-[var(--border)]">
	<div class="mx-auto max-w-5xl p-4 text-sm text-[var(--muted-foreground)]">
		<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
