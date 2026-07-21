// tests/e2e/global-setup.mjs
//
// Seeds the wp-env dev site (http://localhost:8888) with the content the nav
// and template-hierarchy e2e need, BEFORE any test runs. CI spins up a fresh
// wp-env with no content, so the tests own their fixtures rather than
// assuming a hand-configured site.
//
// What it guarantees (idempotently — safe to re-run, never piles up duplicates):
//   - three published pages: About, Team, Contact
//   - a "primary" menu assigned to the `primary` location with:
//       About  (top level, has child)
//         └─ Team (child — exercises a submenu / .menu-item-has-children)
//       Contact (top level)
//   - a "footer" menu assigned to the `footer` location (one item)
//   - 12 published posts (slugs `wtb-post-1` … `wtb-post-12`) so the blog
//     index/archive pagination (`the_posts_pagination`, default 10/page)
//     actually renders — an archive with one post proves nothing (M1-02
//     Task 7). All 12 are filed under a known category (slug `wtb-posts`)
//     so the category-archive view has content too.
//
// All wp-cli goes through `wp-env run cli wp ...` per the project convention
// (see package.json's integration scripts).

import { execSync } from 'node:child_process';

/** Number of posts to seed — comfortably over the default 10/page. */
const POST_COUNT = 12;
/** Slug of the category every seeded post is filed under. */
export const SEEDED_CATEGORY_SLUG = 'wtb-posts';

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

/** Delete every post with this slug, then create a fresh published one in `categoryId`. Returns its ID. */
function reseedPost(title, slug, categoryId) {
  const existing = wp(`post list --post_type=post --name=${slug} --field=ID --format=ids`);
  for (const id of existing.split(/\s+/).filter(Boolean)) {
    wpTry(`post delete ${id} --force`);
  }
  return wp(
    `post create --post_type=post --post_title="${title}" --post_name=${slug} --post_status=publish --post_category=${categoryId} --porcelain`,
  );
}

/** Delete a category by slug if it exists, then create a fresh one. Returns its term ID. */
function reseedCategory(name, slug) {
  const existing = wp(`term list category --slug=${slug} --field=term_id --format=ids`);
  for (const id of existing.split(/\s+/).filter(Boolean)) {
    wpTry(`term delete category ${id}`);
  }
  return wp(`term create category "${name}" --slug=${slug} --porcelain`);
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

  const categoryId = reseedCategory('E2E Posts', SEEDED_CATEGORY_SLUG);
  log(`category "${SEEDED_CATEGORY_SLUG}" recreated as term ${categoryId}`);

  const postIds = [];
  for (let n = 1; n <= POST_COUNT; n += 1) {
    postIds.push(reseedPost(`WTB Post ${n}`, `wtb-post-${n}`, categoryId));
  }
  log(
    `posts: ${postIds.length} seeded (wtb-post-1 … wtb-post-${postIds.length}), all in category ${categoryId}`,
  );

  log('done.');
}
