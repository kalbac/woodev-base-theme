<?php
/**
 * One compact search-result row.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$wtb_post_type       = get_post_type_object( get_post_type() );
$wtb_post_type_label = $wtb_post_type instanceof \WP_Post_Type
	? $wtb_post_type->labels->singular_name
	: __( 'Content', 'woodev-base-theme' );
$wtb_excerpt         = wp_strip_all_tags( get_the_excerpt() );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'wtb-search-result' ); ?>>
	<p class="wtb-search-result__type"><?php echo esc_html( $wtb_post_type_label ); ?></p>
	<h2 class="wtb-search-result__title">
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>

	<?php if ( '' !== $wtb_excerpt ) : ?>
		<p class="wtb-search-result__excerpt"><?php echo esc_html( $wtb_excerpt ); ?></p>
	<?php endif; ?>

	<a class="wtb-search-result__link" href="<?php the_permalink(); ?>">
		<?php
		printf(
			wp_kses(
				/* translators: %s: search-result title. */
				__( 'View result<span class="sr-only"> about &ldquo;%s&rdquo;</span>', 'woodev-base-theme' ),
				[ 'span' => [ 'class' => [] ] ]
			),
			esc_html( get_the_title() )
		);
		?>
		<?php woodev_base_icon( 'chevron-right' ); ?>
	</a>
</article>
