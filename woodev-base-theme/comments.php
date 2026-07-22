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

/*
 * comment_form()'s own argument array is the canonical lever for styling its
 * controls: it survives core reshaping the surrounding markup between
 * releases, unlike filtering the finished HTML. The id/name/required
 * attributes below are copied verbatim from wp-includes/comment-template.php
 * so submission keeps working; only `class` is added.
 *
 * Field labels repeat core's own copy ("Name"/"Email"/"Website"/"Comment")
 * verbatim under our own text domain: phpcs's WordPress.WP.I18n.MissingArgDomain
 * sniff requires every __()/_x() call in this theme to declare
 * 'woodev-base-theme', so a domain-less call reusing core's translation
 * catalog is not an option here.
 */
$woodev_base_commenter = wp_get_current_commenter();
$woodev_base_req       = (bool) get_option( 'require_name_email' );
$woodev_base_html5     = (bool) current_theme_supports( 'html5', 'comment-form' );

$woodev_base_required_attribute = $woodev_base_html5 ? ' required' : ' required="required"';
$woodev_base_required_indicator = ' ' . wp_required_field_indicator();

$woodev_base_comment_field = sprintf(
	'<p class="comment-form-comment">%s %s</p>',
	sprintf(
		'<label for="comment">%s%s</label>',
		_x( 'Comment', 'noun', 'woodev-base-theme' ),
		$woodev_base_required_indicator
	),
	'<textarea id="comment" name="comment" class="textarea" cols="45" rows="8" maxlength="65525"' . $woodev_base_required_attribute . '></textarea>'
);

$woodev_base_fields = [
	'author' => sprintf(
		'<p class="comment-form-author">%s %s</p>',
		sprintf(
			'<label for="author">%s%s</label>',
			__( 'Name', 'woodev-base-theme' ),
			( $woodev_base_req ? $woodev_base_required_indicator : '' )
		),
		sprintf(
			'<input id="author" name="author" class="input" type="text" value="%s" size="30" maxlength="245" autocomplete="name"%s />',
			esc_attr( $woodev_base_commenter['comment_author'] ),
			( $woodev_base_req ? $woodev_base_required_attribute : '' )
		)
	),
	'email'  => sprintf(
		'<p class="comment-form-email">%s %s</p>',
		sprintf(
			'<label for="email">%s%s</label>',
			__( 'Email', 'woodev-base-theme' ),
			( $woodev_base_req ? $woodev_base_required_indicator : '' )
		),
		sprintf(
			'<input id="email" name="email" class="input" %s value="%s" size="30" maxlength="100" aria-describedby="email-notes" autocomplete="email"%s />',
			( $woodev_base_html5 ? 'type="email"' : 'type="text"' ),
			esc_attr( $woodev_base_commenter['comment_author_email'] ),
			( $woodev_base_req ? $woodev_base_required_attribute : '' )
		)
	),
	'url'    => sprintf(
		'<p class="comment-form-url">%s %s</p>',
		sprintf(
			'<label for="url">%s</label>',
			__( 'Website', 'woodev-base-theme' )
		),
		sprintf(
			'<input id="url" name="url" class="input" %s value="%s" size="30" maxlength="200" autocomplete="url" />',
			( $woodev_base_html5 ? 'type="url"' : 'type="text"' ),
			esc_attr( $woodev_base_commenter['comment_author_url'] )
		)
	),
];
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

	<?php
	comment_form(
		[
			'comment_field' => $woodev_base_comment_field,
			'fields'        => $woodev_base_fields,
			'class_submit'  => 'btn',
		]
	);
	?>
</div>
