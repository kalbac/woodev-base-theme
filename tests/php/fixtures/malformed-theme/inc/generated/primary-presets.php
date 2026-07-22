<?php
/**
 * FIXTURE, not a build artifact. Stands in for a primary-presets.php that has
 * been tampered with or half-written, so SettingsTest can prove Settings
 * rejects everything that did not come out of `npm run tokens`.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

return [
	// Breaks out of the declaration and hides the page. The one entry that
	// matters: it must never reach a <style> block.
	'injected'     => [
		'light' => [
			'--primary'            => 'red; } body { display:none } /*',
			'--primary-foreground' => 'oklch(0.985 0 0)',
			'--ring'               => 'oklch(43.9% 0 0)',
		],
		'dark'  => [
			'--primary'            => 'oklch(70.8% 0 0)',
			'--primary-foreground' => 'oklch(0.145 0 0)',
			'--ring'               => 'oklch(70.8% 0 0)',
		],
	],
	// A CSS function we do not emit — url() would fetch, var() would indirect.
	'not_oklch'    => [
		'light' => [
			'--primary'            => 'var(--evil)',
			'--primary-foreground' => 'oklch(0.985 0 0)',
			'--ring'               => 'oklch(43.9% 0 0)',
		],
		'dark'  => [
			'--primary'            => 'oklch(70.8% 0 0)',
			'--primary-foreground' => 'oklch(0.145 0 0)',
			'--ring'               => 'oklch(70.8% 0 0)',
		],
	],
	// Half-written file: the dark half never made it to disk.
	'missing_dark' => [
		'light' => [
			'--primary'            => 'oklch(54.6% 0.245 262.881)',
			'--primary-foreground' => 'oklch(0.985 0 0)',
			'--ring'               => 'oklch(54.6% 0.245 262.881)',
		],
	],
	// Well-formed, and the only entry that may survive.
	'sound'        => [
		'light' => [
			'--primary'            => 'oklch(54.6% 0.245 262.881)',
			'--primary-foreground' => 'oklch(0.985 0 0)',
			'--ring'               => 'oklch(54.6% 0.245 262.881)',
		],
		'dark'  => [
			'--primary'            => 'oklch(70.7% 0.165 254.624)',
			'--primary-foreground' => 'oklch(0.145 0 0)',
			'--ring'               => 'oklch(70.7% 0.165 254.624)',
		],
	],
];
