// tests/e2e/global-setup.mjs
//
// Seeds the wp-env dev site (http://localhost:8888) with the content the nav
// e2e needs, BEFORE any test runs. CI spins up a fresh wp-env with no menu, so
// the tests own their fixtures rather than assuming a hand-configured site.
//
// What it guarantees (idempotently — safe to re-run, never piles up duplicates):
//   - three published pages: About, Team, Contact
//   - a "primary" menu assigned to the `primary` location with:
//       About  (top level, has child)
//         └─ Team (child — exercises a submenu / .menu-item-has-children)
//       Contact (top level)
//   - a "footer" menu assigned to the `footer` location (one item)
//
// All wp-cli goes through `wp-env run cli wp ...` per the project convention
// (see package.json's integration scripts).

import { execSync } from 'node:child_process';

/** Run a wp-cli command in the cli container, return trimmed stdout. */
function wp(command) {
  const full = `npx wp-env run cli wp ${command}`;
  return execSync(full, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

/** Same, but swallow a non-zero exit (e.g. deleting something that isn't there). */
function wpTry(command) {
  try {
    return wp(command);
  } catch {
    return '';
  }
}

/** Delete every page with this slug, then create a fresh published one. Returns its ID. */
function reseedPage(title, slug) {
  const existing = wp(`post list --post_type=page --name=${slug} --field=ID --format=ids`);
  for (const id of existing.split(/\s+/).filter(Boolean)) {
    wpTry(`post delete ${id} --force`);
  }
  return wp(
    `post create --post_type=page --post_title="${title}" --post_name=${slug} --post_status=publish --porcelain`,
  );
}

/** Delete a menu by slug if it exists (also clears its location assignment). */
function deleteMenu(slug) {
  wpTry(`menu delete ${slug}`);
}

export default function globalSetup() {
  const log = (...args) => console.log('[e2e:seed]', ...args);

  log('seeding nav fixtures on http://localhost:8888 …');

  const aboutId = reseedPage('About', 'about');
  const teamId = reseedPage('Team', 'team');
  const contactId = reseedPage('Contact', 'contact');
  log(`pages: About=${aboutId} Team=${teamId} Contact=${contactId}`);

  // Fresh menus every run — the cheapest way to stay idempotent.
  deleteMenu('primary-nav');
  deleteMenu('footer-nav');

  const primaryId = wp('menu create "Primary Nav" --porcelain');
  const aboutItemId = wp(`menu item add-post ${primaryId} ${aboutId} --porcelain`);
  // Team nested under About → the parent gets `.menu-item-has-children` and a `.sub-menu`.
  wp(`menu item add-post ${primaryId} ${teamId} --parent-id=${aboutItemId} --porcelain`);
  wp(`menu item add-post ${primaryId} ${contactId} --porcelain`);
  wp(`menu location assign ${primaryId} primary`);
  log(`primary menu ${primaryId} assigned (About > Team, Contact)`);

  const footerId = wp('menu create "Footer Nav" --porcelain');
  wp(`menu item add-post ${footerId} ${contactId} --porcelain`);
  wp(`menu location assign ${footerId} footer`);
  log(`footer menu ${footerId} assigned`);

  log('done.');
}
