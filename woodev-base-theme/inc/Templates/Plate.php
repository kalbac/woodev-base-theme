<?php
/**
 * Token-themed inline SVG "plate" art for the front-page merchandising
 * surfaces (hero, promo, category tiles) that have no photo to fall back to.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Templates;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/**
 * Shapes are ported VERBATIM from the `<symbol>` definitions in
 * docs/design/v2-mockup/woodev-base-identity.html (ids `p-hero`, `p-promo`,
 * `p-mug`, `p-lamp`, `p-box`, `p-plaid`, `p-vase`, `p-towel`) — that file is
 * the approved design artefact (ADR-008), so this is a port, not a redraw.
 *
 * The shapes are inlined directly into the returned `<svg>` rather than
 * referenced through `<symbol>`/`<use>` against a shared sprite. This is the
 * exact lesson `inc/Woo/ProductPlaceholder.php` records: a `<use>` reference
 * only resolves in a document that also received the sprite's own print, and
 * a plate rendered by this class has no such guarantee — a REST-rendered
 * block, a feed, an admin preview, or any future consumer outside the normal
 * theme page render. Inlining removes the sprite dependency entirely, at the
 * cost of a few hundred bytes duplicated per plate instead of one shared
 * sprite. See that class's docblock for the fuller account.
 *
 * Fills stay presentation attributes reading `var(--c-bg)`, `var(--c-obj)`,
 * `var(--c-obj2)`, `var(--c-obj3)`, `var(--c-ln)` — exactly as the mockup and
 * `ProductPlaceholder` do, so a class-based CSS selector on `.wtb-plate` can
 * set those custom properties and every shape below picks them up through
 * ordinary CSS inheritance, `<use>`'s shadow-boundary problem or not.
 */
final class Plate {

	/**
	 * The six object plates used for category tiles without a thumbnail, in
	 * the order `tile_variant()` indexes them by `term_id % 6`.
	 */
	private const TILE_VARIANTS = [ 'mug', 'lamp', 'box', 'plaid', 'vase', 'towel' ];

	/**
	 * Per-variant viewBox size and inlined shapes (background `<rect>`
	 * excluded — `render()` prepends it once, identically, for every variant).
	 *
	 * @var array<string, array{width: int, height: int, cover?: bool, shapes: string}>
	 */
	private const VARIANTS = [
		'hero'  => [
			'width'  => 480,
			'cover'  => true,
			'height' => 400,
			'shapes' => '<circle fill="var(--c-obj2)" opacity=".14" cx="246" cy="192" r="156"/>'
				. '<rect fill="var(--c-obj3)" opacity=".92" x="252" y="168" width="112" height="180" rx="16"/>'
				. '<path fill="var(--c-obj)" opacity=".86" d="M112 228h112v96a24 24 0 0 1-24 24h-64a24 24 0 0 1-24-24z"/>'
				. '<circle fill="var(--c-obj)" opacity=".86" cx="310" cy="112" r="44"/>'
				. '<path fill="none" stroke="var(--c-ln)" stroke-width="6" opacity=".34" stroke-linecap="round" stroke-linejoin="round" d="M88 348h304M252 216h112"/>',
		],
		'promo' => [
			'width'  => 480,
			'cover'  => true,
			'height' => 400,
			'shapes' => '<circle fill="var(--c-obj2)" opacity=".14" cx="150" cy="210" r="132"/>'
				. '<path fill="var(--c-obj)" opacity=".86" d="M268 132h132v152a24 24 0 0 1-24 24H268z"/>'
				. '<circle fill="var(--c-obj3)" opacity=".92" cx="176" cy="212" r="72"/>'
				. '<path fill="none" stroke="var(--c-ln)" stroke-width="6" opacity=".34" stroke-linecap="round" stroke-linejoin="round" d="M60 320h360M176 140v-40"/>',
		],
		'mug'   => [
			'width'  => 400,
			'height' => 400,
			'shapes' => '<circle fill="var(--c-obj2)" opacity=".14" cx="118" cy="296" r="98"/>'
				. '<path fill="none" stroke="var(--c-ln)" stroke-width="6" opacity=".34" stroke-linecap="round" stroke-linejoin="round" d="M252 182h28a36 36 0 0 1 0 72h-28"/>'
				. '<path fill="var(--c-obj)" opacity=".86" d="M126 150h126v98a63 63 0 0 1-63 63 63 63 0 0 1-63-63z"/>'
				. '<rect fill="var(--c-obj3)" opacity=".92" x="126" y="150" width="126" height="18" rx="9"/>',
		],
		'lamp'  => [
			'width'  => 400,
			'height' => 400,
			'shapes' => '<circle fill="var(--c-obj2)" opacity=".14" cx="200" cy="150" r="104"/>'
				. '<path fill="var(--c-obj)" opacity=".86" d="M200 92l64 104H136z"/>'
				. '<rect fill="var(--c-obj)" opacity=".86" x="195" y="196" width="10" height="102" rx="5"/>'
				. '<path fill="var(--c-obj)" opacity=".86" d="M146 300h108l12 20H134z"/>'
				. '<circle fill="var(--c-obj3)" opacity=".92" cx="200" cy="230" r="14"/>',
		],
		'box'   => [
			'width'  => 400,
			'height' => 400,
			'shapes' => '<rect fill="var(--c-obj2)" opacity=".14" x="56" y="118" width="288" height="204" rx="18"/>'
				. '<path fill="none" stroke="var(--c-ln)" stroke-width="6" opacity=".34" stroke-linecap="round" stroke-linejoin="round" d="M168 142v-18h64v18"/>'
				. '<path fill="var(--c-obj)" opacity=".86" d="M96 170h208v138a16 16 0 0 1-16 16H112a16 16 0 0 1-16-16Z"/>'
				. '<rect fill="var(--c-obj3)" opacity=".92" x="82" y="142" width="236" height="32" rx="10"/>',
		],
		'plaid' => [
			'width'  => 400,
			'height' => 400,
			'shapes' => '<rect fill="var(--c-obj)" opacity=".86" x="70" y="94" width="260" height="212" rx="22"/>'
				. '<path fill="none" stroke="var(--c-ln)" stroke-width="6" opacity=".34" stroke-linecap="round" stroke-linejoin="round" d="M70 164h260M70 236h260M138 94v212M262 94v212"/>'
				. '<rect fill="var(--c-obj3)" opacity=".92" x="102" y="126" width="76" height="42" rx="10"/>',
		],
		'vase'  => [
			'width'  => 400,
			'height' => 400,
			'shapes' => '<circle fill="var(--c-obj2)" opacity=".14" cx="300" cy="298" r="86"/>'
				. '<path fill="none" stroke="var(--c-ln)" stroke-width="6" opacity=".34" stroke-linecap="round" stroke-linejoin="round" d="M200 122V68"/>'
				. '<path fill="var(--c-obj)" opacity=".86" d="M168 122h64v42c26 22 40 52 40 86 0 42-32 66-72 66s-72-24-72-66c0-34 14-64 40-86z"/>'
				. '<circle fill="var(--c-obj3)" opacity=".92" cx="200" cy="252" r="28"/>',
		],
		'towel' => [
			'width'  => 400,
			'height' => 400,
			'shapes' => '<rect fill="var(--c-obj2)" opacity=".14" x="86" y="70" width="228" height="260" rx="20"/>'
				. '<rect fill="var(--c-obj)" opacity=".86" x="118" y="102" width="164" height="196" rx="14"/>'
				. '<path fill="none" stroke="var(--c-ln)" stroke-width="6" opacity=".34" stroke-linecap="round" stroke-linejoin="round" d="M118 168h164M118 234h164"/>'
				. '<rect fill="var(--c-obj3)" opacity=".92" x="150" y="134" width="100" height="16" rx="8"/>',
		],
	];

	public static function render( string $variant ): string {
		if ( ! isset( self::VARIANTS[ $variant ] ) ) {
			return '';
		}

		$definition = self::VARIANTS[ $variant ];

		/*
		 * The two panel plates COVER their box; the tile plates do not.
		 *
		 * This attribute is not part of the `<symbol>` the shapes were ported
		 * from — a symbol carries no preserveAspectRatio, the USE site does,
		 * and the mockup's hero and promo both write
		 * `preserveAspectRatio="xMidYMid slice"` there while its tile plates
		 * (`plate plate--bare`) leave the default. Porting the symbols alone
		 * silently dropped it, and the default (`meet`) letterboxes: the promo
		 * column measured 623x280 against a 480x400 viewBox, so the artwork
		 * drew 336px wide and centred, leaving the plate's own background rect
		 * short of the column's edges — a lighter rectangle inside a darker
		 * one, which is exactly the "reads as a broken image" the plate exists
		 * to prevent. Measured in the browser, not reasoned about.
		 */
		$aspect = ( $definition['cover'] ?? false )
			? ' preserveAspectRatio="xMidYMid slice"'
			: '';

		return \sprintf(
			'<svg class="wtb-plate wtb-plate--%1$s" viewBox="0 0 %2$d %3$d"%5$s aria-hidden="true" focusable="false">'
				. '<rect fill="var(--c-bg)" width="%2$d" height="%3$d"/>%4$s</svg>',
			$variant,
			$definition['width'],
			$definition['height'],
			$definition['shapes'],
			$aspect
		);
	}

	/**
	 * Deterministically picks one of the six object plates for a category
	 * tile with no thumbnail — variety without randomness, so a page's look
	 * is stable across renders and across a test run.
	 *
	 * `$term_id % 6` alone is not total over the int range: PHP's `%` keeps
	 * the dividend's sign, so a negative term_id (never expected from
	 * WordPress, but the parameter type does not forbid it) would produce a
	 * negative index and an out-of-range array access. Normalising into
	 * `[0, 6)` first is what makes this function total.
	 *
	 * @param int $term_id Term ID of the category the tile represents.
	 */
	public static function tile_variant( int $term_id ): string {
		$index = ( ( $term_id % 6 ) + 6 ) % 6;

		return self::TILE_VARIANTS[ $index ];
	}
}
