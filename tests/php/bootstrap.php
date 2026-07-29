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

/*
 * The `WC_Product` stand-in every storefront test needs. Loaded HERE rather
 * than from the one test file that happened to need it first: `require_once`
 * in a test file makes every other test's view of `WC_Product` depend on
 * PHPUnit's file-discovery order. Nothing failed because of it — PHPUnit
 * includes all test files before running any test, so the double always won
 * the race against Mockery's own on-the-fly class definition — but the
 * coupling was invisible and one rename away from mattering. Raised by the
 * s18 critic pass; the mechanism it predicted did not reproduce, the coupling
 * it pointed at was real.
 */
require_once __DIR__ . '/Support/wc-product-double.php';

/*
 * The `WC_Order` stand-in `Account` and `Receipt` tests need — same rationale
 * and loading position as `wc-product-double.php` immediately above.
 */
require_once __DIR__ . '/Support/wc-order-double.php';
