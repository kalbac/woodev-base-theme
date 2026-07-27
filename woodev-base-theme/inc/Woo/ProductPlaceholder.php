<?php
/**
 * Token-themed SVG placeholder for products without a photo.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/**
 * Swaps WooCommerce's raster placeholder image for an inline SVG "plate"
 * themed with the identity's tokens, matching the treatment a real product
 * photo gets: docs/design/v2-mockup/woodev-base-identity.html §17
 * "Изображения" — `svg.plate` is the one swap point for real photography,
 * same markup and size as the real `<img>`. Real product photos need no
 * change at all; only the no-image fallback is replaced here.
 *
 * The SVG is fully self-contained: `render()` returns one `<svg>` with the
 * plate's shapes inlined directly, no `<symbol>`/`<use>` sprite involved. An
 * earlier version shared one `<symbol>` sprite printed once on `wp_footer`
 * and referenced it via `<use href="#…">` from every card, to save bytes.
 * That broke wherever a placeholder can be server-rendered outside a
 * document that reaches this theme's `wp_footer`:
 * `woocommerce/src/Blocks/BlockTypes/ProductImage.php` calls
 * `wc_placeholder_img()` (and so `render()`) while server-rendering a
 * Product Image block for a REST response — block editor previews, and any
 * other custom/REST/feed/email path that renders WooCommerce blocks outside
 * a normal theme page render. Those documents' `<use>` reference resolves
 * against a `<symbol>` that was never printed into them, so the placeholder
 * shows as an empty box. Inlining the shapes removes the sprite dependency
 * entirely — there is no document the placeholder can land in where it
 * fails to render. The trade: a few hundred bytes duplicated per placeholder
 * card instead of one shared sprite. A product with a real photo never
 * calls this path at all, so the cost only lands on products that have no
 * photo to begin with.
 *
 * `<use>` cloning into a shadow tree that document CSS selectors cannot
 * reach (docs/gotchas/svg-use-shadow-boundary-needs-custom-props.md) is why
 * the shapes originally read their fills from CSS custom properties as
 * presentation attributes rather than being styled by class — and now that
 * `<use>` is gone, that shadow-boundary problem is gone with it: the shapes
 * are direct children of this `<svg class="plate">`, so a class-based
 * selector in woo.css could reach them directly instead. They still read
 * `var(--c-bg)`/`var(--c-obj)` as presentation attributes anyway: custom
 * properties inherit down the DOM regardless of `<use>`, so
 * `.woocommerce .plate`'s declarations in woo.css still reach them exactly
 * as before. Keeping the presentation-attribute contract is the lower-risk
 * choice — the CSS stays exactly as it is today, and this class does not
 * own woo.css to edit it anyway.
 */
final class ProductPlaceholder {

	/**
	 * Hook the placeholder swap into WordPress.
	 */
	public function register(): void {
		add_filter( 'woocommerce_placeholder_img', [ $this, 'render' ] );
	}

	/**
	 * Replace WooCommerce's placeholder `<img>` with the themed,
	 * self-contained SVG plate.
	 *
	 * `wc_placeholder_img()` filters its whole return value through
	 * `woocommerce_placeholder_img`, so this fully replaces the raster image
	 * rather than editing it — same swap point real product photography does
	 * not use, since a product with an uploaded image never calls this.
	 *
	 * The shapes are inlined rather than referenced via `<use>` against a
	 * shared sprite — see the class docblock for why: a shared sprite can
	 * only be resolved by a document that also received the sprite's own
	 * print, which is not guaranteed for every path that can reach this
	 * filter (a REST-rendered block, notably).
	 *
	 * Accessibility: `aria-hidden="true"` and `focusable="false"` — this is
	 * a decorative fallback, not photography; the product title elsewhere
	 * in the card already carries the accessible name. Unchanged from the
	 * sprite version, and nothing about the self-contained markup gives a
	 * reason to change it.
	 */
	public function render(): string {
		return '<svg class="plate wtb-plate--placeholder" viewBox="0 0 400 400" aria-hidden="true" focusable="false">'
			. '<rect width="400" height="400" fill="var(--c-bg)" />'
			. '<path d="M96 280 L176 176 L232 224 L288 128 L352 280 Z" fill="var(--c-obj)" opacity=".55" />'
			. '<circle cx="152" cy="144" r="28" fill="var(--c-obj)" opacity=".55" />'
			. '</svg>';
	}
}
