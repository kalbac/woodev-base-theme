# Fixture cleanup keyed on a regenerated parent id never matches anything

**Area:** Testing / fixtures · **Found:** s13 (26.07.2026)

## What happens

`tests/e2e-woo/global-setup.mjs` marks every attachment it creates with
`_wtb_e2e_gallery_marker = <product id>` and, on the next run, deletes the attachments
whose marker equals **the current product id**. Its docblock called this idempotent.

It is not, because `reseedProduct()` runs first and **deletes and recreates the product**,
so the id is new on every run. Last run's attachments carry the OLD id, match nothing, and
survive. The container persists across restarts, so they accumulate.

Measured, not theorised: **35 marked attachments where 5 belong** — seven runs of
accumulation. After keying the cleanup on the marker's *existence* instead of its value:
exactly 5.

## The shape of the bug

Cleanup that filters on a value the setup itself regenerates can never find the previous
generation. It fails **silently and permanently** — no error, no failing test, just a
container that grows. The comment claiming idempotency is what keeps anyone from looking.

Whenever a fixture's cleanup key is derived from something the fixture recreates, the key
is wrong. Use a constant marker and delete everything carrying it.

## What is NOT the cause (checked, because it looks like it should be)

`get_posts()` documents `post_status => 'publish'` as its default, and attachments are
`inherit` — an inviting explanation, and a wrong one. Measured on the container, the marker
query returns the same five rows three ways:

```
any=5  inherit=5  default=5   (exclude_from_search for 'inherit' === false)
```

WP_Query special-cases `post_type => 'attachment'`, and `'any'` includes `inherit` because
that status is not excluded from search. The s13 commit message asserted the status default
was to blame and had to be corrected — **a plausible mechanism is not a cause until it is
measured.**

## Related

- [[parallel-agents-share-one-worktree-and-one-wp-env]] — the other way seeded fixtures rot
- [[qa-gates-cover-less-than-they-claim]] — green gates that looked at less than you think
