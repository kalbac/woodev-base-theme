<?php
/**
 * AUTO-GENERATED from src/tokens/tokens.mjs — do not edit. Run `npm run tokens`.
 *
 * The seven shipped colour palettes (ADR-008). A palette is three custom
 * properties: the neutral temperature and the accent's hue and chroma. Every
 * surface, border, wash and glow in the theme derives from them, which is what
 * makes "pick a palette" a three-declaration change.
 *
 * `warm-clay` equals the :root defaults, so selecting it emits no override.
 * Labels are NOT here — they are translatable strings and live in PHP source.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead. Emitted by the generator, not added by hand —
// a hand edit here is erased by the next `npm run tokens`.
defined( 'ABSPATH' ) || exit;

return [
	'warm-clay'    => [
		'n-h'      => '68',
		'accent-h' => '40',
		'accent-c' => '0.088',
	],
	'cold-petrol'  => [
		'n-h'      => '264',
		'accent-h' => '214',
		'accent-c' => '0.105',
	],
	'graphite'     => [
		'n-h'      => '264',
		'accent-h' => '250',
		'accent-c' => '0.024',
	],
	'forest'       => [
		'n-h'      => '70',
		'accent-h' => '152',
		'accent-c' => '0.100',
	],
	'sand'         => [
		'n-h'      => '74',
		'accent-h' => '75',
		'accent-c' => '0.110',
	],
	'wine'         => [
		'n-h'      => '60',
		'accent-h' => '18',
		'accent-c' => '0.130',
	],
	'night-indigo' => [
		'n-h'      => '274',
		'accent-h' => '274',
		'accent-c' => '0.130',
	],
];
