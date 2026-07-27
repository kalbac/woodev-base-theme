<?php
/**
 * Front-page hero — mockup §05, the CSS ported in T4 as `src/css/adapter/hero.css`.
 *
 * Every word on this surface comes from the site itself: the name, the tagline,
 * and a link to the shop page WooCommerce owns. That constraint is why the hero
 * sat unbuilt for three sessions — the approved mockup fills it with a specific
 * shop's copy ("Дом, в котором всё на месте"), and shipping somebody's product
 * copy inside a generic theme is not porting a design, it is inventing content.
 * What the design actually contributes is the *shape*, and the shape works with
 * the site's own identity in it.
 *
 * Consequently the eyebrow and the trust badges from the mockup are absent:
 * they have no source but invention. The classes stay in the CSS, so a later
 * Customizer surface can fill them without touching this file's structure.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

$wtb_tagline   = get_bloginfo( 'description', 'display' );
$wtb_shop_link = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

/*
 * The art slot takes the static front page's featured image when there is one.
 * With no image the slot still renders: hero.css gives it a surface, a rule, a
 * radius and a shadow, so it reads as a deliberate plate rather than a gap —
 * and it holds the layout's second column, which would otherwise collapse.
 *
 * The post ID is passed explicitly. This part renders BEFORE the main loop, so
 * the global `$post` the argument-less forms read is not set yet: the first
 * version asked `has_post_thumbnail()` with no ID and got false on every static
 * front page that had one, which looks exactly like "no image was set".
 * `get_queried_object_id()` is 0 on a posts front page, which is the correct
 * answer there — that page has no featured image of its own.
 */
$wtb_front_id   = get_queried_object_id();
$wtb_hero_image = ( $wtb_front_id > 0 && has_post_thumbnail( $wtb_front_id ) )
	? get_the_post_thumbnail( $wtb_front_id, 'large', [ 'class' => 'wtb-hero__image' ] )
	: '';
?>
<section class="wtb-hero">
	<div class="wtb-container wtb-hero__inner">
		<div class="wtb-hero__copy">
			<h1><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></h1>

			<?php if ( '' !== $wtb_tagline ) : ?>
				<p class="wtb-hero__lede"><?php echo esc_html( $wtb_tagline ); ?></p>
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
		</div>

		<div class="wtb-hero__art">
			<?php
			// Core's own markup, already escaped; escaping it again destroys it.
			echo $wtb_hero_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() output.
			?>
		</div>
	</div>
</section>
