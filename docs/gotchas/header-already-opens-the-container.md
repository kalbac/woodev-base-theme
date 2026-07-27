# `header.php` already opens the container — a new top-level section must not open another

> Discovered s16 (27–28.07.2026) while wiring the first front-page sections.

## The trap

`header.php` ends with:

```php
<main id="wtb-content" class="wtb-container" tabindex="-1">
```

So **every template is already inside `.wtb-container`**, which supplies the max-width cap (`--wtb-container-max`, a Customizer setting) and the page padding. That is invisible from any template you are actually editing: `index.php`, `page.php` and the template parts all start at `.wtb-layout`, and none of them mentions a container.

Reading the mockup instead makes the mistake look correct. The approved design wraps each section in `.wrap` / `.wrap-wide`, because a mockup is one standalone HTML document with no `header.php` above it. Porting that structure verbatim gives:

```html
<main class="wtb-container">          <!-- padding: 1rem -->
  <section class="wtb-hero">
    <div class="wtb-container ...">    <!-- padding: 1rem AGAIN -->
```

Doubled page padding on every new section, and the width cap applied twice. It does not error, does not fail a lint, and looks like a slightly-too-narrow page — the sort of thing read as a design choice rather than a bug. Three review passes read this markup without flagging it; it surfaced only when a different failure sent someone back to `header.php`.

## How to apply here

- A new top-level section renders its own `<section>` and goes straight to its content. **No `.wtb-container`.**
- If a section needs to break out of the cap (the mockup's hero is full-bleed with an inner wrap), that is a deliberate negative-margin or `100vw` treatment in the adapter CSS — not a second container.
- The general form: before porting a wrapper from `docs/design/v2-mockup/`, check what `header.php` has already opened. The mockup is a standalone document and carries chrome the theme supplies elsewhere.

## Related

- [[tailwind-v4-layer-precedence]] — the other place where the mockup's CSS cannot be pasted as-is
- [[qa-gates-cover-less-than-they-claim]] — a defect that produces plausible output is the hardest kind for any gate to see
