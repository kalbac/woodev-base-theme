<?php
/**
 * FIXTURE, not a build artifact. A palettes.php truncated mid-write (a
 * plausible real failure: a build interrupted between opening and closing
 * the file) — a genuine PHP syntax error. PalettesTest proves the resulting
 * ParseError is caught, not merely that a well-formed-but-wrong file is
 * rejected.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

return [
	'warm-clay' => [
		'n-h'      => '68',
