# `x-trap` moves focus asynchronously — asserting a focus trap right after opening races it

> Discovered s5 (21.07.2026). The `mobile drawer › … focus is trapped` e2e went red on `main` immediately after two green PRs merged, and the red looked exactly like a broken focus trap. The trap was fine; the test was racing it.

## The trap

`@alpinejs/focus`'s `x-trap` does **not** move focus synchronously when the bound expression flips to true. Measured on the real page, right after clicking the drawer toggle:

| Moment | `document.activeElement` | Inside `.wtb-nav`? |
|---|---|---|
| synchronously after `click()` | `<body>` | ❌ |
| after a microtask | `<body>` | ❌ |
| by 50 ms | the first menu link | ✅ |

Meanwhile the document's **first focusable element is `a.wtb-skip-link`**, which lives outside `.wtb-nav` by design (it belongs to the document, not the nav — see `header.php`).

So a `Tab` fired inside that window walks from `<body>` to the **skip link**, and an assertion like `nav.contains(document.activeElement)` fails on the very first iteration. The failure message points at the focus trap, but nothing about the trap is broken.

## Why it surfaced when it did

It didn't reproduce for four consecutive full-suite runs, then failed twice in a row — right after an unrelated one-line CSS change (`.wtb-container` max-width 64rem → 90rem). That change cannot affect a 375px-wide viewport at all; it only perturbed timing enough to lose a race that had been latent since M1-02. **Do not go looking for the "cause" in the last diff when a timing-dependent assertion flips** — the last diff is usually just the perturbation.

Bisecting confirmed the ordering (green at the previous commit, red at the next) and would have sent you hunting a phantom layout regression. What actually settled it was instrumenting `activeElement` over time in a real browser.

## How to apply here

- Before tabbing to prove a trap holds, **wait for the trap to have engaged**, then assert:

  ```js
  await expect
    .poll(async () =>
      page.evaluate(() => document.querySelector('.wtb-nav').contains(document.activeElement)),
    )
    .toBe(true);
  ```

  `expect(menu).toBeVisible()` is **not** a sufficient precondition — CSS visibility lands before `x-trap` runs.
- The fix must not weaken the guard. Verify by mutation: strip `x-trap.noscroll.noreturn="open"` from `template-parts/header/navigation.php` and the test must still go red (it does).
- The same shape applies to any "focus moved into X" assertion driven by Alpine — poll the precondition, never assume the click settled it.

## Related

- [[playwright-browser-newpage-skips-config]] — the other e2e trap where the test, not the theme, was wrong
- [[qa-gates-cover-less-than-they-claim]] — a gate's verdict is only as good as what it actually observed
