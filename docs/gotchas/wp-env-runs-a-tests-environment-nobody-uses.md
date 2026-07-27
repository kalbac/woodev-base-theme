# wp-env starts a tests environment nobody asked for — and the fix upstream wants is not the obvious one

> Discovered s16 (27–28.07.2026), from [#33](https://github.com/kalbac/woodev-base-theme/issues/33).

## The trap

`wp-env start` brings up **two** WordPress installations, not one. Alongside the development
site on `:8888` it starts a tests site on `:8889` with its own `tests-cli`, `tests-wordpress`
and a whole second MySQL. That is the documented default, and it costs three containers
whether or not anything ever connects to them.

In this project nothing did. Integration tests have always run against their own config
(`.wp-env.test.json`, `:8890`), so the `:8889` trio existed purely because `testsEnvironment`
was left unset. Measured 27.07.2026: 15 containers when every config was up, and wp-cli calls
that take 15s alone stretching past 90s under that load.

```json
// .wp-env.json — one line, three fewer containers and one fewer MySQL
{ "testsEnvironment": false }
```

## The part that is not obvious

The tempting simplification is the opposite one: keep the built-in tests environment, move the
`wp-content/woodev-tests` mapping into `.wp-env.json`, run integration on `tests-cli`, and
delete the fourth config entirely. **It works** — measured, integration 50/50 and
integration-dev 4/4 on `tests-cli`, identical counts to the dedicated config.

Do not do it. `wp-env` prints this on every start:

> wp-env starts both development and tests environments by default. This behavior is
> deprecated and will be removed in a future version. […] Use the `--config` option with a
> separate config file for test environments instead.

Upstream is removing the thing that route depends on, and pointing at the separate-config
layout this project already has. The green run measures today, not the next `@wordpress/env`
major. **A deprecation notice in a tool's own startup output is a measurement too** — it was
sitting above every `wp-env start` in this repo's history, unread, while the issue proposing
the opposite change was being written.

## The topology to keep

| Config | Port | Containers | What it is for |
|---|---|---|---|
| `.wp-env.json` | `:8888` | 3 | base e2e, manual looking-at-the-theme |
| `.wp-env.test.json` | `:8890` | 3 | integration + integration-dev (PHPUnit 9.6, WP core suite) |
| `.wp-env.woo.json` | `:8891` | 3 | `e2e:woo` — separate because spec §8 requires proving the theme is useful *without* WooCommerce |
| `.wp-env.dev-mode.json` | `:8892` | 3 | `e2e-dev` — separate because [`wp-env-config-constants-persist`](wp-env-config-constants-persist.md) makes `WOODEV_BASE_DEV` unswitchable inside one environment |

Base and integration can stay up together — that is 6 containers and it covers everything CI
runs. Woo and dev-mode are **on demand**: start, run their suite, stop. Four environments live
at once is not a supported way to work here, it is just something the configs allow.

## Related

- [[wp-env-config-constants-persist]] — why dev-mode cannot share an environment
- [[wp-env-installs-themes-without-activating-them]] — each environment needs its own activation
- [[qa-gates-cover-less-than-they-claim]] — the same shape: output that was there to be read, and was not
