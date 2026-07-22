<?php
/**
 * Guards against the WP_Script_Modules print-memo leaking across test classes.
 *
 * @package Woodev\Theme\Base\Tests\Integration\Support
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration\Support;

/**
 * Fails loudly, instead of silently, when one of our script module handles
 * was already printed earlier in this PHPUnit process.
 *
 * `WP_Script_Modules::$done` (wp-includes/class-wp-script-modules.php,
 * `print_script_module()`) is a private, per-singleton list array that marks
 * each module id as printed and silently no-ops a second print of the same
 * id within the same process. `WP_UnitTestCase::tear_down()` resets
 * `$wp_scripts`/`$wp_styles` but not `$wp_script_modules`, so nothing between
 * test classes clears it.
 *
 * **That property is WordPress 6.9+.** Verified against the 6.8 and 6.9 tags
 * of wordpress-develop: 6.8 — our declared floor — declares only
 * `$registered`, `$enqueued_before_registered` and `$a11y_available`, and
 * gained `$queue`/`$done` in 6.9. So the reflection below is version-specific
 * and MUST degrade rather than throw: an unconditional `getProperty( 'done' )`
 * raises `ReflectionException` on 6.8 and takes the whole integration suite
 * down with it. It is invisible locally because wp-env runs `core: null`,
 * i.e. latest.
 *
 * Degrading costs nothing. This guard never decides whether a test passes —
 * it only replaces a confusing assertion failure with an explained one. Where
 * the property is absent the assertions still fail on a short capture, just
 * without the diagnosis.
 *
 * The production PHPUnit config collects every `*Test.php` under
 * `Integration/`, not just one file. If any other test class in that
 * directory ever renders `wp_head`/`wp_footer` before this file's memoized
 * `render_front_end_assets()` runs, our handles would already be in `$done`
 * and the module tags would silently vanish from the string this class
 * asserts against — a false pass, not a red test.
 *
 * There is no public API to reset that private state, and this class does
 * not try to invent one via reflection; it only reads the array, which is
 * enough to convert a silent wrong result into an explicit, explained
 * failure.
 */
final class ScriptModuleGuard {

	/**
	 * Not instantiable — a namespace for a single static assertion helper.
	 */
	private function __construct() {}

	/**
	 * Throw if any of the given script module handles were already printed.
	 *
	 * Call this once, immediately before the first (memoized) render in a
	 * test file, with the full list of module handles that file's assertions
	 * depend on.
	 *
	 * @param list<string> $handles Script module handles this test file is about to render and assert on.
	 */
	public static function assert_none_already_done( array $handles ): void {
		// WordPress < 6.9 has no $done property at all (see the class docblock);
		// there is nothing to inspect and nothing to report.
		if ( ! \property_exists( \WP_Script_Modules::class, 'done' ) ) {
			return;
		}

		$reflection    = new \ReflectionClass( \WP_Script_Modules::class );
		$done_property = $reflection->getProperty( 'done' );
		$done_property->setAccessible( true );

		/** @var list<string> $done */
		$done = $done_property->getValue( wp_script_modules() );

		$already_done = \array_intersect( $handles, $done );

		if ( [] !== $already_done ) {
			throw new \RuntimeException(
				\sprintf(
					'ScriptModuleGuard: handle(s) [%s] are already present in WP_Script_Modules::$done before ' .
					'this test file rendered wp_head/wp_footer for the first time. That property is not reset ' .
					'between test classes in the same PHPUnit process, so the memoized render this test file relies ' .
					'on would silently capture markup missing these module tags — a false pass, not a red test. ' .
					'Another test class under Integration/ rendered wp_head/wp_footer (and printed these handles) ' .
					'before this one ran.',
					\implode( ', ', $already_done )
				)
			);
		}
	}
}
