# The integration suite is PHPUnit 9 — `#[RunInSeparateProcess]` there is silently ignored

**Area:** Testing / Tooling · **Found:** s12 (a preload integration test that was green for the wrong reason)

## What happened

A new integration test needed a fresh render (`wp_head()` output is memoised per request), so it was written the modern way:

```php
#[RunInSeparateProcess]
#[PreserveGlobalState(false)]
public function test_the_system_font_mode_prints_no_display_font_preload_link(): void { … }
```

It passed. It passed while asserting the opposite of what the code did, because the attribute did nothing: the test read a **stale, memoised render left by an earlier test in the same process**.

## Why

This project runs two different PHPUnit majors, on purpose:

| Suite | Runner | Version | Isolation syntax |
|---|---|---|---|
| Unit (`composer test:unit`) | own `phpunit.xml.dist` | PHPUnit **10.5** | attributes work |
| Integration (`npm run test:integration`) | WordPress core test suite | PHPUnit **^9.6** | **doc-comment annotations only** |

The integration suite's version is pinned by the WordPress core test suite, not by us. PHPUnit 9 has no attribute support at all — the `PHPUnit\Framework\Attributes` namespace does not exist in it — so an attribute is just an unknown annotation on a class member. It does not warn. It does not error. It is discarded.

Use the annotations there:

```php
/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
```

## The wider trap

An ignored isolation directive does not usually make a test red — it makes it **green against leaked state**, which is worse. The same shape appears whenever a test depends on something a previous test in the process already established: a memoised WP render, `wp_styles()`'s registry, `add_filter()` calls nobody removed, a `static` in our own code.

So: **whenever a test needs isolation, prove the isolation works before trusting the assertion.** Cheapest proof is a mutation — break the production code the test names and confirm it goes red. That is what caught this one.

## Related

- [[wp-test-suite-removes-html5-support]] — the other place where WP's own suite silently changes state between tests
- [[qa-gates-cover-less-than-they-claim]] — exit 0 means only "what this gate looked at was clean"
- [[three-rounds-of-fixes-means-change-the-approach]]
