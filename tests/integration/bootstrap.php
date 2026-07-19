<?php
/**
 * Integration test bootstrap: boots a real WordPress with our theme active.
 *
 * The mirror image of tests/php/bootstrap.php, which must never boot WordPress
 * (Brain\Monkey needs the WP functions absent). The two suites cannot share a
 * bootstrap, a phpunit.xml, or even a Composer root — see this directory's
 * composer.json.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

// Must precede the core bootstrap: core does a class_exists() check on the
// polyfills and exit(1)s if they are not already loaded.
require_once __DIR__ . '/vendor/autoload.php';

$woodev_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/wordpress-phpunit';

if ( ! file_exists( $woodev_tests_dir . '/includes/functions.php' ) ) {
	exit( "Could not find the WordPress test suite at {$woodev_tests_dir}. This suite only runs inside wp-env.\n" );
}

require_once $woodev_tests_dir . '/includes/functions.php';

// The core suite reinstalls the database on every run, which would undo a
// wp-cli theme activation. Switching on this hook is what makes the theme
// active for the tests themselves.
tests_add_filter(
	'setup_theme',
	static function (): void {
		switch_theme( 'woodev-base-theme' );
	}
);

require $woodev_tests_dir . '/includes/bootstrap.php';
