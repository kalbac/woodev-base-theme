=== Woodev Base Theme ===

Contributors: woodev
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: custom-colors, custom-menu, translation-ready, featured-images, threaded-comments, left-sidebar, right-sidebar, one-column, two-columns, footer-widgets, theme-options, e-commerce

A universal WordPress theme with an optional WooCommerce layer, one coherent visual identity, and a light/dark colour scheme that follows the visitor's system preference.

== Description ==

This is a hybrid theme: classic PHP templates plus `theme.json`. It ships one visual identity rather than a pile of presets, and exposes the parts worth changing through the Customizer instead of asking you to write CSS.

Everything the theme renders is server-rendered HTML. JavaScript enhances; it never draws the page. The theme works with JavaScript disabled, and no request ever leaves the visitor's browser for a third-party host — no font CDN, no analytics, no phone-home.

= What you can change =

* **Palette** — seven presets, each generated so every text/background pair clears WCAG AA in both light and dark. The contrast is checked at build time, not asserted in a readme.
* **Accent colour** — any colour; the theme derives the rest of the scale from it.
* **Corner radius** and **base font size**.
* **Typography** — the bundled Golos Text / IBM Plex pairing, or the system font stack with no download at all.
* **Colour scheme** — light, dark, or follow the operating system, with an optional visitor-facing toggle. The choice is resolved before first paint, so there is no flash of the wrong theme.
* **Layout** — header and footer variants, container width, and an optional right sidebar.

= WooCommerce =

The WooCommerce layer is optional and loads only when WooCommerce is active. Both storefront paths are supported and maintained: the classic shortcode templates, and the newer Cart and Checkout **blocks**.

== Installation ==

1. Upload the theme through Appearance → Themes → Add New → Upload Theme, or unzip it into `wp-content/themes/`.
2. Activate it.
3. Open Appearance → Customize to choose a palette, accent, typography and colour scheme.

No further setup is required, and the theme does not ask you to install anything else.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

No. WooCommerce support is entirely optional and every WooCommerce asset is loaded conditionally. With WooCommerce inactive the theme is a general-purpose blog and site theme.

= Are the fonts downloaded from Google? =

No. Golos Text and IBM Plex are self-hosted inside the theme, subset to Latin and Cyrillic. Nothing is fetched from `fonts.googleapis.com` or any other external host, at any point.

= Can I switch back to system fonts? =

Yes — the Customizer's typography setting has a system-stack option, which downloads no font files at all.

= Does the dark scheme follow the operating system? =

If you leave the default at "system", yes. Visitors can also be given a toggle, and their choice is remembered.

== Copyright ==

Woodev Base Theme, (C) 2026 Woodev
Woodev Base Theme is distributed under the terms of the GNU GPL version 2 or later.

This theme bundles the following third-party resources.

Golos Text, copyright (C) 2020 Paratype
License: SIL Open Font License, version 1.1
Source: https://github.com/paratype/Golos-Text
License text: assets/fonts/LICENSE-golos-text.txt

IBM Plex Sans and IBM Plex Mono, copyright (C) 2017 IBM Corp.
License: SIL Open Font License, version 1.1
Source: https://github.com/IBM/plex
License text: assets/fonts/LICENSE-ibm-plex.txt

Basecoat CSS, copyright (C) Basecoat contributors
License: MIT
Source: https://github.com/hunvreus/basecoat

Lucide icons, copyright (C) Lucide contributors
License: ISC
Source: https://github.com/lucide-icons/lucide

Alpine.js, copyright (C) Caleb Porzio and contributors
License: MIT
Source: https://github.com/alpinejs/alpine

Tailwind CSS, copyright (C) Tailwind Labs Inc.
License: MIT
Source: https://github.com/tailwindlabs/tailwindcss

The theme bundles no images. Product and post placeholders are generated as inline SVG from the theme's own tokens.

== Changelog ==

= 0.1.0 =
* Initial release.
