# wp-env installs the theme but never activates it

**Area:** Tooling · **Found:** s7, standing up the dev-mode e2e environment

## The trap

`.wp-env.json`'s `themes` array reads like "use this theme". It is not — it only
**copies the theme into the environment**. The active theme stays whichever
bundled default WordPress installed. The `plugins` array behaves the same way
(confirmed s8 on `.wp-env.woo.json`: right after `wp-env start`, `wp plugin
list` reported `woocommerce inactive` even though it was in the config).

Measured right after `wp-env start --config=.wp-env.dev-mode.json`:

```
$ npx wp-env run cli --config=.wp-env.dev-mode.json wp theme list
...
woodev-base-theme   inactive   none    0.1.0
```

The failure it causes is silent and confusing: the site returns 200, renders a
complete page, and every assertion about *our* markup or styles fails for reasons
that look like bugs in our code. Nothing anywhere says "you are looking at a
different theme" (or "the plugin you thought was live is off").

## Who does the activation, per environment

Each environment needs its own answer. There is no shared mechanism:

| Environment | Activated by |
|---|---|
| `:8888` main dev (`.wp-env.json`) | `.github/workflows/ci.yml` — an explicit `wp theme activate` step. Locally: by hand, once. |
| `:8890` integration (`.wp-env.test.json`) | `tests/integration/bootstrap.php` — a `switch_theme()` on the `setup_theme` filter. It has to be a filter: the core suite reinstalls the database each run and would undo a wp-cli activation. |
| `:8892` dev-mode e2e (`.wp-env.dev-mode.json`) | `tests/e2e-dev/global-setup.mjs` — activates, then **re-reads the active theme and throws** if it is not ours. |
| `:8891` Woo e2e (`.wp-env.woo.json`) | `tests/e2e-woo/global-setup.mjs` — activates the theme AND WooCommerce, then re-reads both and throws if either did not take. Same assert-it-took pattern applied to two things. |
| `:8888` base e2e (`.wp-env.json`) | `tests/e2e/global-setup.mjs` — activates and asserts, **since s11**. Before that it activated nowhere but CI, and see "How to apply here" for why the gap was worse than it looked. |

The third is the pattern to copy for any new environment: activate, then assert
the activation took. An activation that silently no-ops makes every downstream
assertion meaningless, and that is exactly the case a `globalSetup` is there to
prevent.

## How to apply here

- Standing up a new wp-env environment? Assume the theme is inactive until you
  have seen `wp theme list` say otherwise. Do not trust `themes` in the config.
- Never verify this with `wp eval` — wp-env echoes the command before its output,
  so a grep for a probe string matches the echo, see
  [[wp-env-config-constants-persist]].
- ~~The `:8888` activation living only in CI means a fresh local checkout has an
  inactive theme until someone runs the command by hand. Known, tolerated: the
  main e2e fails loudly and immediately when it happens.~~ **Fixed s11
  (25.07.2026).** The tolerance rested on "fails loudly", and loudly turned out
  not to mean *usefully*: on a freshly created `:8888` the suite dies in
  `global-setup.mjs` with `Error: Invalid location primary` — because the still-
  active Twenty Twenty-Five registers no `primary` menu location. The message
  names the menu, so the search starts at `register_nav_menus()` and the theme
  that is actually wrong never comes up. `tests/e2e/global-setup.mjs` now
  activates and asserts first, like the other three environments always did.
  **A loud failure that points at the wrong thing is not a working guard** —
  when tolerating a known gap because "it will fail obviously", check what the
  failure actually says.

## Related

- [[wp-env-config-constants-persist]] — the other wp-env config key that does not mean what it looks like
- [[vite-css-entry-is-not-imported-by-the-js-entry]] — what the :8892 environment exists to guard
- [[wp-org-plugin-zip-unversioned-serves-beta]] — the OTHER trap when a `plugins` array points at wp.org: the wrong VERSION
