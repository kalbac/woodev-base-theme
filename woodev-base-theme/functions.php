<?php
/**
 * Woodev Base bootstrap.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/autoload.php';
require_once __DIR__ . '/inc/template-tags.php';

\Woodev\Theme\Base\Theme::boot();
