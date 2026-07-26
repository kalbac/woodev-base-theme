# `WP_UnitTestCase` rolls back the database, not `$wp_styles`

**Area:** Testing / WP core · **Found:** s14 (26.07.2026)

## What happens

An integration test asserting that a stylesheet is **absent** passed on its own and failed
inside its own class:

```
Woo\BlockAssetsTest::test_the_handle_is_absent_on_a_page_without_either_block
The block bundle must not even be registered on a page with neither block.
Failed asserting that true is false.
```

`--filter test_the_handle_is_absent_on_a_page_without_either_block` → `OK (1 test)`.
Whole suite → red. The two tests above it in the same class had each enqueued the handle,
and `WP_Styles` is built once per process: `WP_UnitTestCase` wraps every test in a
transaction and rolls the database back, but it does not touch the global asset registries.
Registrations accumulate across tests for the whole run.

This is the works-alone/fails-in-suite shape, and it points the wrong way — the natural
reading is "the production guard is broken", when the guard is fine and the test's
starting state is not.

## What to do

Reset the registry per test:

```php
public function set_up(): void {
    parent::set_up();
    unset( $GLOBALS['wp_styles'] );
}
```

`wp_styles()` lazily rebuilds a fresh `WP_Styles` on its next call, so each test starts
empty. `$wp_scripts` has the identical problem and the identical fix.

**Then mutation-verify.** A reset can just as easily make the assertion vacuous — the point
is an empty registry at the start, not an empty one at the end. Remove the production
early-return, run the suite, confirm the test goes red, restore. In s14 it did.

## Related

- [[wp-test-suite-removes-html5-support]] — the other "core's tear_down changed global state" trap
- [[playwright-browser-newpage-skips-config]] — the same works-alone/fails-in-suite shape, in e2e
