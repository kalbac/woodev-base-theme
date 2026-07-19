# The WP test suite strips html5 theme support after every test

> Discovered s3 (19.07.2026) while building the M1 integration harness. The first fix for it was wrong and made things worse — that story is below, because the wrong fix is the tempting one.

## The trap

`WP_UnitTestCase_Base::tear_down()` ends **every** test with an unconditional `remove_theme_support( 'html5' )`:

```php
// /wordpress-phpunit/includes/abstract-testcase.php:226
$this->unregister_all_meta_keys();
remove_theme_support( 'html5' );
remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
```

Grep the whole suite and that is the **only** `remove_theme_support` in it — html5 is singled out, nothing else is. Core added it because html5 support changes the markup helpers (`get_search_form()`, comment forms, galleries) and a test that switches it on would otherwise contaminate every test after it.

The consequence for us: html5 support registered once at `after_setup_theme` survives exactly **one** test. Whichever test runs second sees it gone. So `assertTrue( current_theme_supports( 'html5' ) )` passes or fails on **execution order alone** — green in isolation, red in the suite, and the failure looks like a theme bug (`html5 support is missing 'search-form'`) when nothing in the theme is wrong.

## The wrong fix (tried, reverted)

The obvious repair is to re-register the theme's supports per test:

```php
protected function set_up(): void {
    parent::set_up();
    ( new Setup() )->setup();   // ← don't
}
```

This turned 1 failure into **5**. Core answers with:

> Theme support for `title-tag` should be registered before the `wp_loaded` hook.

That is `_doing_it_wrong()`, and it is *correct* — `set_up()` runs long after `wp_loaded`, so calling our real `after_setup_theme` callback there is genuinely incorrect usage. The suite failing us was the suite doing its job.

The other workaround — re-adding html5 inside the test before asserting it — is a tautology. It asserts that `add_theme_support()` stores what you just passed it, i.e. it tests WordPress, not the theme.

## How to apply here

**Don't assert html5 support at the integration level.** `tests/integration/Integration/SetupTest.php` carries a comment block where the test would be, so the absence reads as a decision rather than an oversight.

The coverage isn't lost, it's just at the right level: the unit suite pins the exact html5 feature list at the point of the `add_theme_support()` call, with Brain\Monkey. Unit proves *we asked for it*; integration proves *WordPress kept it* — and for html5 specifically, WordPress deliberately doesn't keep it, so there is nothing true to assert.

### The second trap: "covered elsewhere" that isn't

The paragraph above was **false when first written**, and that is the more transferable lesson. The unit test it pointed at was:

```php
Functions\expect( 'add_theme_support' )->times( 4 );
```

A bare call count. It is satisfied by any four features with any arguments — gutting the html5 list to `[ 'gallery' ]` kept the suite green, as did swapping `post-thumbnails` for `custom-logo`. So html5 was covered **nowhere**: deliberately absent from integration, and only apparently present in unit. The Codex critic caught it; a mutation run confirmed it in one command.

That is worse than an ordinary gap, because the comment deflecting reviewers to the "real" coverage was itself the thing hiding it. The claim had been inherited from an earlier session's summary and repeated without re-running it.

Two rules fall out:

1. **A cross-reference to coverage elsewhere is a claim, and claims get verified.** Before writing "covered by X", mutate the thing and watch X fail. `->times( n )` and `Mockery::type( 'string' )` are the usual suspects: they assert shape, not content.
2. **Never re-state a previous session's verification as fact.** Re-run it or drop the adjective. "mutation-verified in s2" was the exact wording that carried the falsehood forward.

Generalise it: **any** integration assertion on theme state must survive `tear_down()`. Before adding one, check whether core resets that global — the "Reset template globals" block right above line 226 resets `$wp_stylesheet_path` and `$wp_template_path` too. State that core resets belongs in unit tests.

## Related

- [[wp-env-config-constants-persist]] — the other half of the harness's environment surprises
- `tests/integration/Integration/SetupTest.php` — the comment block standing in for the missing test
