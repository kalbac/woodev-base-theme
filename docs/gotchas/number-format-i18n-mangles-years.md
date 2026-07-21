# `number_format_i18n()` turns a year into `2,026`

> Discovered s4 (20.07.2026) in the footer copyright line. Caught in-browser, not by a test.

## What happened

The footer copyright was written as:

```php
esc_html( number_format_i18n( (int) wp_date( 'Y' ) ) )
```

and rendered **`© 2,026`** in en_US, **`© 2 026`** in ru_RU. `number_format_i18n()` applies the locale's *thousands separator* — that is its whole job — so any 4-digit number gets grouped. A year is not a count; it must print as plain digits.

## Why the plan led here

AGENTS.md's Russian-plural rule says: avoid `_n()`, use count-agnostic phrasing **+ `number_format_i18n()`**. That guidance is for **counts** ("42 posts" → "42 записи"). The M1-02 plan carried it verbatim into the footer, where the only number is the year — and the worker followed the plan faithfully. The rule was misapplied one level up, in the plan.

## The fix

```php
esc_html( wp_date( 'Y' ) )
```

`wp_date( 'Y' )` returns the site-timezone year as plain Latin digits. No grouping, no `number_format_i18n()`.

## The rule

`number_format_i18n()` is for **quantities the reader counts**. Never for years, IDs, phone numbers, postal codes, version numbers, or any digit string where grouping is wrong. When in doubt, ask "would a thousands separator ever be correct here?" — for a year the answer is always no.

Verify copy that contains numbers **in the browser**, in more than one locale if you can: `number_format_i18n( 2026 )` looks fine in code and wrong on the page.

## Related

- [[qa-gates-cover-less-than-they-claim]] — green lint said nothing; only the rendered page showed `2,026`.
