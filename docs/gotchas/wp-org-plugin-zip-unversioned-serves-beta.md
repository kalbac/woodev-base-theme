# wp.org's unversioned plugin zip can serve a pre-release, not the latest stable

**Area:** Tooling · **Found:** s8, standing up `.wp-env.woo.json`

## The trap

Two shapes of the wp.org download URL for the same plugin:

- `https://downloads.wordpress.org/plugin/<slug>.zip` — **whatever wp.org has
  most recently attached to that URL**. If the maintainer has an open pre-release
  channel, this can be a beta or RC, not the latest **stable**.
- `https://downloads.wordpress.org/plugin/<slug>.<version>.zip` — the specific
  version. Deterministic.

Measured 24.07.2026: the unversioned WooCommerce URL served `11.0.0-beta.2`
while `10.9.4` was the current stable on the plugin page. A test env put together
from the unversioned URL was quietly running a beta, and every downstream
assertion — including template `@version` numbers the plan was built against —
was talking about the wrong Woo.

## How to apply here

- **Every wp-env `plugins` entry that points at wp.org uses the versioned URL.**
  Never the unversioned form.
- When bumping the pin, look at the plugin's *stable* release (the WordPress.org
  plugin page's "Version" line, or the plugin's own tagged release), not just
  what `.zip` happens to serve today.
- When reading plan-time contracts (template `@version`, hook signatures, REST
  fields) against the running plugin, re-verify against the pinned version, not
  against a "same latest" fantasy.

## Related

- [[wp-env-installs-themes-without-activating-them]] — the other way a wp-env
  test environment ends up looking like it works but is testing the wrong thing
