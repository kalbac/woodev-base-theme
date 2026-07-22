// tests/e2e-dev/global-setup.mjs
//
// Activates our theme on the permanently-dev wp-env environment
// (http://localhost:8892, .wp-env.dev-mode.json) before the dev-mode spec runs.
//
// WHY THIS EXISTS: wp-env's `themes` key INSTALLS a theme into the
// environment but does NOT ACTIVATE it — verified this session: right after
// `wp-env start --config=.wp-env.dev-mode.json`, `wp theme list` showed
// `woodev-base-theme  inactive`. The default :8888 environment gets activated
// by CI (.github/workflows/ci.yml), but that step does not cover this
// environment — nothing else does this for :8892. Without this step every
// assertion in dev-mode.spec.mjs would run against whatever theme wp-env
// ships by default, and would tell us nothing about our code.
//
// Deliberately tiny compared to tests/e2e/global-setup.mjs: that file seeds
// pages/menus/posts through the DEFAULT wp-env config (:8888) for the nav and
// template-hierarchy specs. This spec only reads computed style off the front
// page — no fixtures needed, and reusing that file would seed the wrong site.

import { execSync } from 'node:child_process';

/** wp-env config file for the permanently-dev environment (port 8892). */
const CONFIG = '.wp-env.dev-mode.json';
/** Theme slug — must match woodev-base-theme/style.css. */
const THEME_SLUG = 'woodev-base-theme';

/** Run a wp-cli command against the dev-mode environment, return trimmed stdout. */
function wp(command) {
  const full = `npx wp-env run cli --config=${CONFIG} wp ${command}`;
  return execSync(full, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

export default function globalSetup() {
  const log = (...args) => console.log('[e2e-dev:setup]', ...args);

  log(`activating ${THEME_SLUG} on http://localhost:8892 …`);
  // Idempotent: activating an already-active theme is a documented no-op.
  wp(`theme activate ${THEME_SLUG}`);

  // Assert it actually worked — a silent no-op here would make every
  // assertion in dev-mode.spec.mjs meaningless.
  const active = wp('theme list --status=active --field=name');
  if (active !== THEME_SLUG) {
    throw new Error(
      `[e2e-dev:setup] expected "${THEME_SLUG}" to be the active theme on :8892, got "${active}"`,
    );
  }

  log(`confirmed active: ${active}`);
}
