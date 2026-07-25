<?php
/**
 * FIXTURE, not a build artifact. Stands in for a palettes.php that has been
 * tampered with or half-written, so PalettesTest (and, through it,
 * Settings/InlineStyles) can prove the theme survives it rather than
 * fatal-erroring or leaking a broken value into a <style> block.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

return [
	// Breaks out of the custom-property declaration and hides the page.
	// The one entry that matters most: it must never reach a <style> block.
	'injected'         => [
		'n-h'      => '68; } body { display:none } /*',
		'accent-h' => '40',
		'accent-c' => '0.088',
	],
	// A CSS function/indirection we never emit for these two properties.
	'not_numeric'      => [
		'n-h'      => 'var(--evil)',
		'accent-h' => '40',
		'accent-c' => '0.088',
	],
	// Half-written file: accent-c never made it to disk.
	'missing_accent_c' => [
		'n-h'      => '68',
		'accent-h' => '40',
	],
	// Not an array at all.
	'not_an_array'     => 'oklch(47% 0.088 40)',
	// Well-formed, and the only entry (besides the synthesised warm-clay
	// default, which this file deliberately omits) that may survive.
	'sound'            => [
		'n-h'      => '264',
		'accent-h' => '214',
		'accent-c' => '0.105',
	],
];
