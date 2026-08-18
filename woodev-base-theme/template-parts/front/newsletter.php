<?php
/**
 * Front-page newsletter wrapper — mockup §05.
 *
 * The form belongs to a plugin. This theme only exposes a shortcode setting,
 * checks that the tag is registered, and supplies the visual wrapper. A
 * missing plugin therefore hides the section instead of printing raw shortcode
 * syntax or an empty panel.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Customizer\Settings;

$wtb_shortcode = Settings::front_newsletter_shortcode();

if ( '' === $wtb_shortcode || 1 !== preg_match_all( '/\[\s*\/?\s*([a-z0-9_-]+)/i', $wtb_shortcode, $wtb_matches ) ) {
	return;
}


foreach ( array_unique( $wtb_matches[1] ) as $wtb_shortcode_tag ) {
	if ( ! shortcode_exists( $wtb_shortcode_tag ) ) {
		return;
	}
}

$wtb_form = do_shortcode( $wtb_shortcode );

if ( '' === trim( $wtb_form ) ) {
	return;
}
?>
<section class="wtb-front-section wtb-front-newsletter">
	<div class="wtb-newsletter">
		<div class="wtb-newsletter__copy">
			<h2><?php esc_html_e( 'Monthly letter', 'woodev-base-theme' ); ?></h2>
		</div>
		<div class="wtb-newsletter__form">
			<?php echo $wtb_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- registered plugin shortcode owns and escapes its form markup. ?>
		</div>
	</div>
</section>
