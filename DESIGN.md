# Woodev Base — Visual Identity Design Brief

> For the commissioned designer (Open Design). Written 25.07.2026.
> Status: **draft for Maksim's review** before hand-off.

This brief asks for the **whole-theme visual identity** of Woodev Base — a free,
publicly distributable WordPress theme with an optional WooCommerce storefront.
The current build works but reads as a clean starter/scaffold, not a *designed*
site. We want a cohesive, modern, distinctive look that could not be mistaken for
a default template — and that we can actually **build** inside the theme's
technical reality.

Read the "Hard constraints" section carefully: the mockup must be *implementable*,
not just pretty. A design that ignores them is not usable to us.

---

## 1. What we're building

Woodev Base is a universal WP theme (classic templates + `theme.json`, hybrid)
on **Basecoat UI + Tailwind CSS v4 + Alpine.js**, with an optional WooCommerce
layer. It ships free on wp.org, so it must stay compliant with WordPress theme
review rules (escaping, no plugin-territory, self-hosted assets only).

Design the theme's **default look** — its flagship visual identity. Under the
hood the theme keeps eight neutral "style packs" (alternate token sets) and a
Customizer accent; this design becomes the *default* one, expressed through
design tokens so it maps cleanly onto that system (see §5).

## 2. The subject (make it concrete)

Design against a **clean, universal online store** — a general/lifestyle shop
that could sell anything (a few categories, a mix of products). Pick the exact
subject, product names and copy yourself and make it concrete; a generic
"Shop / Product / Add to cart" placeholder store is exactly the templated feel
we're trying to escape. Use realistic product imagery and real copy throughout.

## 3. What we want (the ask)

A design with a genuine point of view: deliberate typography, a considered
palette, intentional spacing and rhythm, and **one signature element** the site
is remembered by. Premium and confident, but timeless — avoid the current
AI-design clichés (cream + terracotta + big serif; near-black + one acid accent;
hairline-rule broadsheet). Earn "designed", don't decorate.

**Creative freedoms granted** (these relax the theme's current self-imposed
limits — approved by Maksim, 25.07.2026):

- **Typography — a self-hosted display/heading typeface.** Body text stays on the
  system UI stack (fast, no layout shift); headings/brand/price may use one
  characterful typeface. It MUST be **OFL/GPL-compatible and self-hostable**
  (bundled with the theme, no Google Fonts CDN or any external host). Pair the
  display and body deliberately.
- **A real default accent colour.** The theme may ship in colour out of the box
  (not neutral grey), remaining overridable in the Customizer.
- **Bolder storefront chrome.** Beyond WooCommerce's minimal defaults: a real
  homepage hero, featured-category / promo blocks, a richer product card, an
  archive filter rail, value props, etc.

## 4. Scope — pages & components to design (light AND dark)

Design one cohesive system across:

**Site chrome**
- Header: logo/brand, primary nav (with dropdown), search, cart indicator,
  colour-scheme toggle. Mobile header + drawer.
- Footer: columns, newsletter, secondary nav, payment/social row.

**Home** — hero, featured products, category tiles, a promo/value-prop band,
optional editorial/blog teaser, newsletter.

**Storefront (WooCommerce)**
- Shop / category archive: page header, result count + sort, a filter rail,
  the **product grid**, and the product **card** in all states — default,
  **on-sale**, **out-of-stock**, plus hover.
- Pagination.
- Single product: gallery, title, price (incl. sale), short description, stock,
  quantity + add-to-cart buy box, meta, **tabs** (description/reviews/additional),
  related products.
- Store notices (cart success / info / error).

**Content**
- Blog index (post cards) + single post (article typography, comments).
- A generic page (about-style) to prove long-form type.

**Component kit** — buttons (primary / secondary / outline / ghost), badges,
inputs / select / quantity stepper, cards, forms, alerts/notices, breadcrumbs,
pagination, tags. Show focus states.

## 5. Hard constraints (the design MUST honour these to be buildable)

1. **Token-driven, themable.** Express every colour, radius, spacing step, and
   type role as **design tokens (CSS custom properties)**, not scattered magic
   numbers. We map these onto the theme's token layer:
   `--background --foreground --primary --primary-foreground --secondary --muted
   --muted-foreground --accent --card --card-foreground --border --input --ring
   --destructive --radius` (+ derived `--radius-sm/md/lg/xl`). Add new tokens if
   needed, but keep them named and systematic.
2. **Light + dark, both first-class.** Provide both palettes. Dark elevation via
   surface/border/subtle-glow, not heavy drop shadows.
3. **Colour comes from tokens only** (so the 8 packs + Customizer accent keep
   working). The signature look lives in **type, layout, spacing, motion and the
   one signature element** — things that survive a token swap — NOT in a single
   hardcoded brand colour. Assume the accent can change.
4. **Accessibility (WCAG 2.1 AA).** AA contrast in both schemes, visible keyboard
   focus, semantic structure, `prefers-reduced-motion` honoured. Tap targets ≥
   44px on mobile.
5. **Progressive enhancement.** The design must work as server-rendered HTML;
   motion/interactions are enhancements, never required to see content.
6. **WooCommerce markup reality.** The storefront wraps native Woo markup we only
   restyle: the shop loop item is an `<li>` whose product `<a>` spans the card's
   image+body (a `<header>`/`<footer>` crossing it is invalid HTML); product tabs
   are Woo's `role=tablist` markup restyled, not reinvented. Design the card and
   tabs so they can be achieved by **styling** that markup, not by arbitrary DOM.
7. **Self-hosted everything.** Fonts, icons, images — all local/GPL-legal, no
   external CDNs (CSP + wp.org). Icons: Lucide (already bundled).
8. **Responsive.** Mobile-first; show mobile + desktop for the key screens
   (header, home hero, shop grid, product card, single product).

## 6. Deliverable

Self-contained HTML/CSS mockup(s) (Open Design's artifact format), one cohesive
system, tokens declared as CSS custom properties up top, light + dark. Real
imagery and copy. A short rationale for the direction (typeface pairing, palette,
the signature element) so we understand the intent when translating it to code.

## 7. How it will be used / acceptance

We translate the approved mockup into the theme's token system + classic
templates and Basecoat/Tailwind layers. So we judge the mockup on: does it look
genuinely designed (Maksim's call); is it internally consistent as a system; and
is every distinctive choice reachable through tokens + the real WP/Woo markup.
Maksim reviews, approves, or rejects the direction before we implement.

## 8. Notes for us (not the designer)

- This revisits the v1 spec's **system-font-only** decision
  (`docs/specs/2026-07-17-woodev-base-v1-design.md`) — approved by Maksim
  25.07.2026; record an ADR/spec update when the direction is locked.
- Scope is now **theme-wide visual identity**, broader than issue #12 (storefront
  redesign). #12's committed engineering (grid/cascade/sidebar fixes, `faf7801`)
  carries over regardless of the new skin.
- After mockup approval: brainstorm → writing-plans → implement per milestone
  (storefront first, since that's where M2a sits).
