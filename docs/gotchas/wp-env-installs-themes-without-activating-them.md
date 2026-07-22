# wp-env installs the theme but never activates it

**Area:** Tooling · **Found:** s7, standing up the dev-mode e2e environment

## The trap

`.wp-env.json`'s `themes` array reads like "use this theme". It is not — it only
**copies the theme into the environment**. The active theme stays whichever
bundled default WordPress installed.

Measured right after `wp-env start --config=.wp-env.dev-mode.json`:

```
$ npx wp-env run cli --config=.wp-env.dev-mode.json wp theme list
...
woodev-base-theme   inactive   none    0.1.0
```

The failure it causes is silent and confusing: the site returns 200, renders a
complete page, and every assertion about *our* markup or styles fails for reasons
that look like bugs in our code. Nothing anywhere says "you are looking at a
different theme".

## Who does the activation, per environment

Each environment needs its own answer. There is no shared mechanism:

| Environment | Activated by |
|---|---|
| `:8888` main dev (`.wp-env.json`) | `.github/workflows/ci.yml` — an explicit `wp theme activate` step. Locally: by hand, once. |
| `:8890` integration (`.wp-env.test.json`) | `tests/integration/bootstrap.php` — a `switch_theme()` on the `setup_theme` filter. It has to be a filter: the core suite reinstalls the database each run and would undo a wp-cli activation. |
| `:8892` dev-mode e2e (`.wp-env.dev-mode.json`) | `tests/e2e-dev/global-setup.mjs` — activates, then **re-reads the active theme and throws** if it is not ours. |

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
- The `:8888` activation living only in CI means a fresh local checkout has an
  inactive theme until someone runs the command by hand. Known, tolerated: the
  main e2e fails loudly and immediately when it happens.

## Related

- [[wp-env-config-constants-persist]] — the other wp-env config key that does not mean what it looks like
- [[vite-css-entry-is-not-imported-by-the-js-entry]] — what the :8892 environment exists to guard
