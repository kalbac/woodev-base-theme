<?php
/**
 * FIXTURE, not a build artifact. A palettes.php that parses fine but does not
 * return an array at all (a totally different kind of corruption than
 * malformed-theme's per-entry tampering) — PalettesTest proves this degrades
 * to the synthesised warm-clay default rather than a fatal TypeError.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

return 'not an array';
