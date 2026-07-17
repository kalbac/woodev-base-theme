# Woodev Base

> **Status: Working draft / project brief — not an approved specification.**
>
> This document captures the current project direction and the reasoning behind it. It is intentionally incomplete. Before implementation begins, the project requirements, architecture, WordPress contracts, customization model, testing strategy, and release workflow must be discussed and documented in detail.

## Project identity

- **Product name:** Woodev Base
- **Theme slug:** `woodev-base-theme`
- **Text domain:** `woodev-base-theme`
- **PHP namespace:** `Woodev\\Theme\\Base`
- **Short prefix:** `wtb` (subject to final convention review)
- **Type:** Universal WordPress theme with optional WooCommerce support
- **Distribution:** Intended to become a free, publicly distributable theme

## General idea

Woodev Base is intended to be a modern, accessible, customizable WordPress theme that can serve as a foundation for different types of websites.

The theme will initially be used for product demonstrations, but it must not be designed as a narrow product-demo theme or as a WooCommerce-only theme. The first layer should be a clean, independent WordPress theme. WooCommerce support should be implemented as a well-structured integration layer rather than as the theme's central identity.

The theme should be suitable for sites such as:

- standard WordPress content sites;
- blogs and documentation sites;
- marketing and landing pages;
- product and service sites;
- catalogs and directory-style sites;
- WooCommerce stores.

The project should follow established WordPress and WooCommerce best practices instead of inventing unnecessary custom conventions. Custom code is justified where it provides a clear product-level benefit, preserves maintainability, or is required by the selected UI architecture.

## Selected UI and frontend stack

### Basecoat UI

Basecoat is the primary UI component foundation because it provides a vanilla HTML/CSS/JavaScript implementation inspired by the shadcn/ui design system.

It is a good fit because the theme is intended to use server-rendered WordPress/PHP markup without introducing React or a React runtime. Basecoat provides reusable component patterns, shadcn-compatible design tokens, accessibility-oriented markup conventions, dark-mode support, and behavior for interactive components that need JavaScript.

Basecoat should be treated as a foundation to be integrated and adapted, not as a reason to make the entire theme dependent on a specific visual preset. The project must preserve the ability to customize the visual system and create project-specific components where needed.

### Tailwind CSS v4

Tailwind CSS v4 is the utility and design-token layer for layout, responsive behavior, spacing, typography, states, and project-specific composition.

The implementation should use Tailwind in a maintainable way:

- avoid unstructured utility duplication in PHP templates;
- keep reusable component patterns understandable;
- define a coherent token system;
- make content scanning/source detection explicit;
- ensure production builds contain only the required CSS;
- prevent conflicts with WordPress and WooCommerce markup;
- keep third-party and project CSS boundaries clear.

### Alpine.js

Alpine.js is the lightweight interaction and state layer for behavior that cannot be expressed with HTML and CSS alone.

It should be used selectively for things such as:

- menus and navigation state;
- drawers and dialogs where appropriate;
- tabs and disclosure patterns;
- filters and small interactive controls;
- asynchronous UI states;
- WooCommerce-related interface behavior.

Alpine.js should not turn the theme into a client-rendered application. The default approach should remain progressively enhanced, server-rendered HTML with JavaScript added where it improves the user experience.

### Build tooling

A modern asset build pipeline is expected. Vite is a likely candidate because it fits the surrounding project workflow, but the exact build configuration is not an approved decision yet and must be validated during the initial architecture phase.

## Core architectural principles

1. **WordPress-first:** follow WordPress template, hook, enqueue, translation, security, and customization conventions.
2. **Progressive enhancement:** the core page should remain meaningful and usable as server-rendered HTML.
3. **No React requirement:** the theme must not require React, JSX, or a React runtime for normal operation.
4. **WooCommerce as an integration:** WooCommerce support must be strong, but the base theme must remain useful without WooCommerce.
5. **Accessible by default:** keyboard navigation, focus management, semantics, labels, states, reduced motion, contrast, and screen-reader behavior must be considered component by component.
6. **Customizable without editing theme files:** users should be able to control the main visual and layout decisions through an intentional settings interface.
7. **Maintainable contracts:** theme functions, hooks, template parts, CSS classes, JavaScript behavior, and public filters must be documented before they become hard-to-change contracts.
8. **Performance-conscious:** avoid loading all assets and behavior on every page when they are not needed.
9. **Translation-ready:** all user-facing strings must use WordPress internationalization correctly.
10. **Safe updates:** customizations should not require editing parent/theme source files directly.

## Customization direction

The theme must provide a user-facing customization mechanism. The final implementation may use:

- the WordPress Customizer;
- a dedicated theme settings page;
- a carefully justified combination of both;
- or another WordPress-native mechanism if it provides a substantially better user experience.

This decision is intentionally open at draft stage and must be made after considering the theme architecture, classic versus block/hybrid theme direction, accessibility, discoverability, persistence, exportability, and long-term WordPress compatibility.

The customization model should eventually cover the important design-system and theme-level controls, potentially including:

- color tokens and light/dark mode values;
- typography and font choices;
- spacing and radius choices;
- container widths and layout options;
- header and footer variants;
- navigation behavior;
- button and form styles;
- WooCommerce presentation options when WooCommerce is active;
- optional custom CSS with appropriate safeguards;
- reset/defaults and preview behavior.

The project should avoid exposing every internal token as a confusing settings field. The interface should provide sensible presets and a small number of high-value controls, while still allowing advanced customization where appropriate.

## WordPress and WooCommerce direction

The implementation should use WordPress-native mechanisms wherever possible:

- proper theme setup and support declarations;
- correct asset enqueueing;
- template hierarchy and template parts;
- child-theme compatibility where relevant;
- standard navigation and widget/block areas where appropriate;
- hooks and filters with stable prefixes;
- translation and escaping conventions;
- nonce and capability checks for settings;
- WooCommerce template and hook conventions;
- compatibility with standard WooCommerce flows rather than replacing them unnecessarily.

WooCommerce support should include the important customer-facing areas, but the base theme must degrade gracefully when WooCommerce is not installed or activated.

The project should explicitly define which WooCommerce templates are overridden, which hooks are preserved, how updates are tracked, and how custom styling is applied without creating fragile dependencies on internal plugin markup.

## Autonomous implementation expectation

The eventual implementation agent should be able to work with a high degree of autonomy, but only after the initial requirements and architecture have been clarified.

Before substantial coding begins, the project should establish:

- the theme type: classic, block, or hybrid;
- supported WordPress and PHP versions;
- supported WooCommerce versions;
- the browser and accessibility baseline;
- the exact build and development workflow;
- source and distribution directory conventions;
- component and template naming conventions;
- CSS and JavaScript loading rules;
- customization storage and rendering rules;
- public hooks, filters, and compatibility contracts;
- testing and visual verification requirements;
- linting, formatting, build, and release checks;
- licensing and third-party asset requirements;
- migration and backwards-compatibility expectations.

The agent should inspect authoritative WordPress, WooCommerce, Basecoat, Tailwind, and Alpine.js documentation when making implementation decisions. It should not silently invent project-wide conventions when an established WordPress convention is appropriate.

When a requirement is ambiguous or affects a long-lived public contract, the agent should stop and surface the decision instead of making a hidden assumption. Once the decision is made, it should be recorded in the appropriate project documentation.

## Current directory intent

```text
woodev_base_theme/
├── PROJECT.md              # This working project brief
├── woodev-base-theme/      # Theme source directory
└── docs/                   # Detailed project documentation to be added later
```

The source directory is intentionally empty at this stage. This file is a starting context document, not a request to begin implementing the theme immediately.

## Open decisions for the next discussion

The following topics need to be discussed and documented before autonomous implementation:

1. Classic theme, block theme, or hybrid architecture.
2. Minimum supported WordPress, PHP, and WooCommerce versions.
3. Whether Vite is the final build tool and how production assets are packaged.
4. Basecoat import/customization strategy.
5. Design-token and theme-preset architecture.
6. Customizer versus a dedicated settings page.
7. Required templates and component inventory.
8. WooCommerce integration boundaries and template override policy.
9. Accessibility and browser support baseline.
10. Testing strategy, including PHP, JavaScript, integration, and visual checks.
11. Documentation structure and agent handoff protocol.
12. Licensing, third-party assets, fonts, icons, and distribution requirements.

These are planning topics, not final requirements. The project should evolve through explicit discussion and documented decisions rather than premature implementation.
