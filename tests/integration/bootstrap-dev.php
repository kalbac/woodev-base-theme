<?php
/**
 * Integration bootstrap for the dev-mode suite.
 *
 * Identical to bootstrap.php except that WOODEV_BASE_DEV is defined before
 * WordPress boots, so Assets::enqueue() takes its dev branch.
 *
 * Why a whole second bootstrap and not a wp-env config constant: wp-env writes
 * `config` keys into BOTH the dev and the tests environment, and appends them to
 * wp-config.php without ever removing them again — dropping the config file and
 * restarting leaves the constant in place, and `--update` does not help either
 * (docs/gotchas/wp-env-config-constants-persist.md). The integration environment
 * would silently stay in dev mode for every later run. A define here is scoped to
 * one PHPUnit process and leaves no residue.
 *
 * The constant cannot be unset once set, which is also why this is a separate
 * process rather than a test-level toggle.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

define( 'WOODEV_BASE_DEV', true );

require_once __DIR__ . '/bootstrap.php';
