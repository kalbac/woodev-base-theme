<?php
/**
 * Unit test bootstrap: Composer autoload (dev) + theme autoloader.
 *
 * @package Woodev\Theme\Base\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

/*
 * The unit suite runs WITHOUT WordPress — Brain\Monkey fakes the API surface — but
 * every shipped theme file now ends its header with `defined( 'ABSPATH' ) || exit;`.
 * Undefined here, that `exit` kills the PHP process the instant the autoloader is
 * required, and PHPUnit dies before printing anything WITH EXIT CODE 0. A suite that
 * never ran is indistinguishable from a suite that passed; s15 watched exactly that
 * happen and only caught it because the output was empty rather than wrong.
 *
 * Defining it is not faking a test: the constant's only job is to say "WordPress is
 * loading this", which is true of the suite as much as of a real request.
 */
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../../woodev-base-theme/inc/autoload.php';
