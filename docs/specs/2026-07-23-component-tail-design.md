# The §7 component tail — design

> Written s7, 23.07.2026. Scope and the blog-index treatment settled with Maksim;
> everything else is an engineering call recorded here rather than asked about.

## Problem

Spec §7 lists the components v1 ships. Templates render exactly two of them:
`.btn` (search submit, read-more) and `.input` (search field). Everything else is
CSS the build emits and no page ever uses.

That is not only unfinished — it is **untested by construction**. s5 established
that the 8 style packs share one palette and differ only in component geometry
(`docs/gotchas/basecoat-style-packs-standalone.md`), so a page rendering no
Basecoat component classes looks identical under all 8. The pack machinery is
currently pinned by one `.btn`.

## Scope

**In:** card, badge, alert, and the comment-form controls (textarea, text inputs,
checkbox, submit).

**Out, deliberately:**

- **tabs and accordion → M2.** A classic blog theme has nowhere to put them.
  Wiring them now means inventing a page whose only purpose is to display a
  component, which YAGNI forbids and which would be rebuilt anyway: in M2 they
  have a real home, the WooCommerce single-product tabs.
- **select and radio.** Nothing in the template hierarchy renders either. Core's
  category-dropdown widget emits a bare `<select>`, but that is third-party
  markup arriving through a widget area, not ours to author. Revisit if a
  template ever needs one.

## Basecoat's contracts (read from `basecoat-css@1.0.2`, not assumed)

These decide the markup; they are element-based, so class names alone are not
enough:

| Component | Contract |
|---|---|
| `.card` | `display: flex; flex-direction: column`. Children are **elements**: `> header`, `> section`, `> footer`. `> img:first-child` is rounded to the card's top corners — a featured image must therefore be the first child. Titles are `> header > :is(h2, h3, [data-title], .card-title)`; descriptions `> header > :is(p, [data-description])`. |
| `.badge` | `data-variant` selects the colour: none/`primary` (default), `secondary`, `outline`, `destructive`, `ghost`, `link`. Hover rules are keyed on `[a]`, i.e. it is designed to be a link. |
| `.alert` | A grid. `> svg` is an optional leading icon (auto-placed, spans both rows); `> :is(h2..h6, strong, [data-title])` is the title; `> section` the body. `data-variant="destructive"` for the error tone. |

## Decisions

### The post grid

`content-excerpt.php` becomes a card; `loop.php` wraps the posts (not the
pagination) in `.wtb-post-grid`.

Columns: 1 → 2 at ≥48rem → 3 at ≥80rem, **capped at 2 whenever the sidebar is
present**, since the content column is ~18rem narrower there. The cap rule is
`.wtb-layout--has-sidebar .wtb-post-grid` (0,2,0) declared inside the ≥80rem
query, so it beats the 3-column rule on specificity regardless of order.

Explicit breakpoints rather than `auto-fill`/`minmax`: an intrinsic grid adapts
to the sidebar for free, but its column count at a given width is an emergent
property of the container, which makes it awkward to assert and easy to break by
changing an unrelated padding. The counts here are the contract, so they are
written down and e2e asserts them.

`.mb-8` comes off the article — the grid's `gap` owns that spacing now.

The card's `> section` gets `flex: 1` so footers align across a row of cards of
unequal text length. Grid items stretch to equal height by default, so nothing
else is needed.

### Badges

Post categories, rendered as links with `data-variant="secondary"`, in the card
header and on `single.php`. Built from `get_the_category()` rather than
`get_the_category_list()` — the latter returns finished markup we cannot put a
class inside.

Neutral (`secondary`) rather than the default primary: a row of accent-coloured
chips under every title fights the accent's actual job, which is the call to
action.

### Alerts

Three places we author ourselves:

- `content-none.php` — the empty state.
- `404.php` — same shape, different copy.
- The password-protected post form (`the_password_form` filter).

Plus one we do not author: core's `wp_list_comments()` walker emits
`<p class="comment-awaiting-moderation">`. The adapter maps that class onto the
alert look rather than replacing the walker — the adapter layer is exactly where
"style what a third party rendered" belongs, and a custom walker to change one
paragraph is not a trade worth making.

### Comment-form controls

Through `comment_form()`'s own argument array — the canonical WordPress lever —
not by filtering the finished HTML: `comment_field` carries `.textarea`, the
author/email/url fields `.input`, `class_submit` becomes `btn`, and the cookie
consent checkbox is styled by the adapter. Any markup core changes between
releases keeps working, because we are supplying fields rather than rewriting
them.

## Testing

| Level | What |
|---|---|
| Integration | `comment_form()` really emits the classes (assert the rendered form, not the args array — the args are our input, the markup is the contract). The category-badge helper's output and escaping. |
| e2e | The grid's column count at three viewports; the sidebar cap; badges present and linking to the category archive; the alert on 404 and on an empty search; the comment form's controls carrying their classes. Light and dark. |

**The sidebar-cap case mutates a theme_mod, so it belongs in
`tests/e2e/theme-mods.spec.mjs`** — the one file allowed to, because Playwright
parallelises by file and a second mutating spec would race it
(`docs/CURRENT-STATE.md`, s6). Everything else goes in a new
`components.spec.mjs`, which must stay read-only.

Column count is asserted from **computed style**
(`getComputedStyle(grid).gridTemplateColumns` resolves to concrete pixel tracks,
so the count is the number of tracks) rather than by counting visible cards,
which would silently pass with too few posts.

## Risks

- **A row of cards is the first place the 8 packs visibly differ.** That is the
  point, but it also means the pack e2e can now assert something real. Out of
  scope here; worth doing when the packs are next touched.
- **`post_class()` output is filterable**, so a plugin can add classes to the
  article. Adding `card` alongside is safe; relying on `card` being the *only*
  class is not, and nothing here does.
- **Featured images are unconstrained in aspect ratio.** Cards in a row will have
  images of different heights unless constrained; the card CSS needs a fixed
  aspect ratio on `> img:first-child` or rows look ragged.

## Related

- `docs/gotchas/basecoat-style-packs-standalone.md` — why component classes on the page are what makes packs testable
- `docs/gotchas/tailwind-v4-layer-precedence.md` — adapter vs utilities, which decides where these rules live
- `docs/specs/2026-07-17-woodev-base-v1-design.md` §7 — the inventory this closes
