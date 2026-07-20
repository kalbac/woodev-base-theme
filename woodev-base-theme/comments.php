<?php
/**
 * The comment area: count heading, comment list, pagination and the form.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

if ( post_password_required() ) {
	return;
}
?>
<div id="comments" class="wtb-comments mt-12">
	<?php if ( have_comments() ) : ?>
		<h2 class="wtb-comments__title text-xl font-semibold">
			<?php
			// Count-agnostic phrasing, not _n(): Russian has 3 plural forms vs
			// WP's 2, so a count-sensitive string would be wrong in translation.
			printf(
				/* translators: %s: number of comments, already localized. */
				esc_html__( 'Comments (%s)', 'woodev-base-theme' ),
				esc_html( number_format_i18n( get_comments_number() ) )
			);
			?>
		</h2>

		<ol class="wtb-comment-list mt-4">
			<?php wp_list_comments( [ 'style' => 'ol' ] ); ?>
		</ol>

		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php comment_form(); ?>
</div>
