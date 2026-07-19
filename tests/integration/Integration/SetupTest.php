<?php
/**
 * Setup integration tests: theme supports and menus, as a real WordPress sees them.
 *
 * The unit suite already covers Setup with Brain\Monkey, but it can only prove
 * that add_theme_support() was CALLED. These assert what WordPress actually
 * ended up with — the registry, after core's own normalization, with the theme
 * really active.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_UnitTestCase;

final class SetupTest extends WP_UnitTestCase {

	public function test_the_theme_under_test_is_the_active_one(): void {
		// Guards the harness itself: every other assertion in this suite is
		// meaningless if the bootstrap failed to switch themes and we are
		// quietly testing a default theme.
		self::assertSame( 'woodev-base-theme', get_stylesheet() );
		self::assertSame( 'woodev-base-theme', get_template() );
	}

	public function test_theme_supports_are_registered(): void {
		self::assertTrue( current_theme_supports( 'title-tag' ) );
		self::assertTrue( current_theme_supports( 'post-thumbnails' ) );
		self::assertTrue( current_theme_supports( 'automatic-feed-links' ) );
	}

	/*
	 * There is deliberately no html5 test here, and it is not an oversight:
	 * WP_UnitTestCase_Base::tear_down() ends every test with an unconditional
	 * remove_theme_support( 'html5' ), so html5 survives exactly one test and any
	 * assertion on it would pass or fail on execution order alone.
	 *
	 * The obvious workarounds are worse. Re-running Setup::setup() per test is
	 * genuinely incorrect usage — core raises _doing_it_wrong for
	 * add_theme_support( 'title-tag' ) after wp_loaded, and the suite fails the
	 * test for it (correctly). Re-adding html5 from the test would assert
	 * WordPress's behaviour rather than ours: tautology.
	 *
	 * The coverage lives in the unit suite instead: tests/php/Unit/SetupTest.php
	 * pins the exact html5 feature list at the point of the add_theme_support()
	 * call. Until s3 that claim was false — the unit test only counted the calls,
	 * so html5 was covered nowhere while this comment said otherwise. Codex
	 * caught it; the assertion is now mutation-verified.
	 * See docs/gotchas/wp-test-suite-removes-html5-support.md
	 */

	public function test_primary_nav_menu_is_registered(): void {
		$menus = get_registered_nav_menus();

		self::assertArrayHasKey( 'primary', $menus );
		self::assertNotEmpty( $menus['primary'] );
	}

	public function test_theme_declares_our_text_domain(): void {
		// load_theme_textdomain() returns false while no .mo exists for the
		// locale, which is the honest state until ru_RU ships (M3) — so assert
		// the wiring, not a translation.
		self::assertSame( 'woodev-base-theme', wp_get_theme()->get( 'TextDomain' ) );
	}
}
