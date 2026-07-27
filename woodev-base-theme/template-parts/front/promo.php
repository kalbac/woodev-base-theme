<?php
/**
 * Front-page promo strip — mockup §05, CSS ported in T4 as part of
 * `src/css/adapter/blocks.css`.
 *
 * Same constraint as the value band: a promo is a claim about a specific
 * offer, so every word of it is a Customizer setting defaulting to empty.
 * The heading is what gates the section — a promo with art and a button but
 * nothing to say is not a promo.
 *
 * The art side takes the admin's image when there is one and the identity's
 * own plate when there is not, so the section never renders as a bare
 * coloured rectangle. That was the visible defect on the hero in s16.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Customizer\Settings;
use Woodev\Theme\Base\Templates\Plate;

$wtb_promo_title = Settings::front_promo_title();

if ( '' === $wtb_promo_title ) {
	return;
}

$wtb_promo_text      = Settings::front_promo_text();
$wtb_promo_cta_label = Settings::front_promo_cta_label();
$wtb_promo_cta_url   = Settings::front_promo_cta_url();
$wtb_promo_image_id  = Settings::front_promo_image();

/*
 * wp_get_attachment_image() returns '' for an ID that no longer resolves to an
 * image — a deleted attachment, or an ID typed into a tampered theme_mod. The
 * empty string then falls through to the plate below rather than leaving the
 * art column blank, which is the same fail-open-to-something rule the hero
 * follows.
 */
$wtb_promo_image = $wtb_promo_image_id > 0
	? wp_get_attachment_image(
		$wtb_promo_image_id,
		'large',
		false,
		[
			'class' => 'wtb-promo__image',
			'alt'   => '',
		]
	)
	: '';
?>
<section class="wtb-front-section">
	<?php /* No .wtb-container: header.php already opens main.wtb-container. */ ?>
	<div class="wtb-promo">
		<div class="wtb-promo__text">
			<h2><?php echo esc_html( $wtb_promo_title ); ?></h2>

			<?php if ( '' !== $wtb_promo_text ) : ?>
				<p class="wtb-promo__lede"><?php echo esc_html( $wtb_promo_text ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $wtb_promo_cta_label && '' !== $wtb_promo_cta_url ) : ?>
				<div class="wtb-promo__cta">
					<?php /* Basecoat keys variants and sizes off attributes, not BEM classes — see src/css/adapter/buttons.css. */ ?>
					<a class="btn" href="<?php echo esc_url( $wtb_promo_cta_url ); ?>">
						<?php echo esc_html( $wtb_promo_cta_label ); ?>
						<?php woodev_base_icon( 'chevron-right', [ 'size' => 18 ] ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

		<div class="wtb-promo__art">
			<?php
			// Both branches are our own or core's markup, already escaped;
			// escaping either again destroys it.
			echo '' !== $wtb_promo_image ? $wtb_promo_image : Plate::render( 'promo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output, or Plate's own generated SVG.
			?>
		</div>
	</div>
</section>
