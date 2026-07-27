<?php
/**
 * Front-page hero — mockup §05, the CSS ported in T4 as `src/css/adapter/hero.css`.
 *
 * The headline and the shop link come from the site itself: the name, and the
 * page WooCommerce owns. That constraint is why the hero sat unbuilt for three
 * sessions — the approved mockup fills it with a specific shop's copy ("Дом, в
 * котором всё на месте"), and shipping somebody's product copy inside a generic
 * theme is not porting a design, it is inventing content. What the design
 * actually contributes is the *shape*, and the shape works with the site's own
 * identity in it.
 *
 * The three surfaces with no such source — the eyebrow, the lede and the trust
 * badges — are Customizer settings defaulting to empty (AGENTS.md: a fork with
 * a workable default is a setting, not a question). Each is skipped entirely
 * when unset, so the default hero is still name + tagline + shop link.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Customizer\Settings;
use Woodev\Theme\Base\Templates\Plate;

$wtb_eyebrow   = Settings::front_hero_eyebrow();
$wtb_trust     = Settings::front_hero_trust();
$wtb_shop_link = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

/*
 * The admin's lede displaces the site tagline rather than joining it: two
 * paragraphs of subtitle under one headline is not a layout the design has,
 * and a site that sets both means the second one.
 */
$wtb_lede = Settings::front_hero_lede();

if ( '' === $wtb_lede ) {
	$wtb_lede = get_bloginfo( 'description', 'display' );
}

/*
 * The art slot takes the static front page's featured image when there is one,
 * and the identity's own plate when there is not — an empty slot rendered as a
 * grey rectangle with a border and a shadow, which reads as a broken image
 * rather than as a deliberate surface (#18). An admin who wants neither turns
 * the column off, and the hero goes single-column instead of holding a third of
 * its width for nothing.
 *
 * The post ID is passed explicitly. This part renders BEFORE the main loop, so
 * the global `$post` the argument-less forms read is not set yet: the first
 * version asked `has_post_thumbnail()` with no ID and got false on every static
 * front page that had one, which looks exactly like "no image was set".
 * `get_queried_object_id()` is 0 on a posts front page, which is the correct
 * answer there — that page has no featured image of its own.
 */
$wtb_show_art = Settings::FRONT_HERO_ART_OFF !== Settings::front_hero_art();
$wtb_front_id = get_queried_object_id();

$wtb_hero_image = ( $wtb_show_art && $wtb_front_id > 0 && has_post_thumbnail( $wtb_front_id ) )
	? get_the_post_thumbnail( $wtb_front_id, 'large', [ 'class' => 'wtb-hero__image' ] )
	: '';
?>
<section class="wtb-hero">
	<?php /* No .wtb-container: header.php already opens main.wtb-container. */ ?>
	<div class="wtb-hero__inner<?php echo $wtb_show_art ? '' : ' wtb-hero__inner--single'; ?>">
		<div class="wtb-hero__copy">
			<?php if ( '' !== $wtb_eyebrow ) : ?>
				<p class="wtb-hero__eyebrow">
					<?php /* Decorative and empty by design: hero.css draws the dot. */ ?>
					<span class="dot" aria-hidden="true"></span>
					<?php echo esc_html( $wtb_eyebrow ); ?>
				</p>
			<?php endif; ?>

			<h1><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></h1>

			<?php if ( '' !== $wtb_lede ) : ?>
				<p class="wtb-hero__lede"><?php echo esc_html( $wtb_lede ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $wtb_shop_link ) : ?>
				<div class="wtb-hero__cta">
					<?php /* Basecoat keys variants and sizes off attributes, not BEM classes — see src/css/adapter/buttons.css. */ ?>
					<a class="btn" data-size="lg" href="<?php echo esc_url( $wtb_shop_link ); ?>">
						<?php esc_html_e( 'Browse the shop', 'woodev-base-theme' ); ?>
						<?php woodev_base_icon( 'chevron-right', [ 'size' => 18 ] ); ?>
					</a>
				</div>
			<?php endif; ?>

			<?php if ( [] !== $wtb_trust ) : ?>
				<div class="wtb-hero__trust">
					<?php foreach ( $wtb_trust as $wtb_badge ) : ?>
						<div>
							<?php woodev_base_icon( $wtb_badge['icon'], [ 'size' => 18 ] ); ?>
							<?php echo esc_html( $wtb_badge['label'] ); ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $wtb_show_art ) : ?>
			<div class="wtb-hero__art">
				<?php
				// Both branches are core's markup or our own generated SVG,
				// already escaped; escaping either again destroys it.
				echo '' !== $wtb_hero_image ? $wtb_hero_image : Plate::render( 'hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() output, or Plate's own generated SVG.
				?>
			</div>
		<?php endif; ?>
	</div>
</section>
