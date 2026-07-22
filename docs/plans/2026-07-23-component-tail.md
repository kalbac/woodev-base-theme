# §7 component tail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire card, badge, alert and the comment-form controls into real templates, so §7's inventory is rendered rather than merely built.

**Architecture:** Basecoat's contracts are element-based, so the work is mostly markup: `content-excerpt.php` becomes a `.card` inside a new `.wtb-post-grid`, categories become `.badge` links through a new template tag, the empty/404/password states become `.alert`, and `comment_form()` gets its classes through its own argument array rather than an output filter.

**Tech Stack:** PHP 8.1, Basecoat CSS 1.0.2, Tailwind v4 (adapter layer), PHPUnit, Playwright.

**Spec:** `docs/specs/2026-07-23-component-tail-design.md` — read it first; it records why tabs/accordion are out and what Basecoat's contracts actually are.

---

## Facts established before writing this plan — do not re-derive

Read off `node_modules/basecoat-css@1.0.2` and the running site:

1. `.card` is `display: flex; flex-direction: column`. Its children are matched as **elements**: `> header`, `> section`, `> footer`. `> img:first-child` gets the card's top corner radius, so a featured image must be the **first child of `.card`**, not wrapped in a div.
2. `.card > header > :is(h2, h3, [data-title], .card-title)` is the title rule; `> header > :is(p, [data-description])` is the muted description.
3. `.badge` takes `data-variant`: absent or `primary` (accent), `secondary`, `outline`, `destructive`, `ghost`, `link`. Hover rules are written `[a]:hover:…`, i.e. it expects to be a link.
4. `.alert` is a grid: an optional `> svg` leading icon, `> :is(h2..h6, strong, [data-title])` title, `> section` body, `data-variant="destructive"` for the error tone.
5. `woodev_base_icon()` (`inc/template-tags.php`) echoes an inline Lucide SVG. Available icons are in `woodev-base-theme/assets/static/icons/` — check the directory before naming one; `npm run icons` copies them from the package.
6. The layout wrapper adds `.wtb-layout--has-sidebar` when a sidebar is shown (`src/css/adapter/index.css`), which is the hook for the column cap.
7. `tests/e2e/theme-mods.spec.mjs` is the ONLY spec allowed to change site-global theme_mods; Playwright parallelises by file and a second mutating spec would race it. Anything needing a sidebar toggle goes there.
8. The e2e site is seeded with 12 posts (`tests/e2e/global-setup.mjs`), all in category `wtb-posts` — enough for a 3-column row, and every post has that category so badges are guaranteed to render.

---

## Task 1: Adapter CSS

**Files:**
- Modify: `src/css/adapter/index.css`

- [ ] **Step 1: Append the new rules**

Add at the end of the file:

```css
/*
 * Post grid (§7 card tail). Explicit breakpoints rather than
 * `repeat(auto-fill, minmax(…))`: an intrinsic grid adapts to the sidebar for
 * free, but its column count at a given width becomes an emergent property of
 * the container, which an unrelated padding change can silently alter. The
 * counts below are the contract and e2e asserts them.
 */
.wtb-post-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}

@media (min-width: 48rem) {
  .wtb-post-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 80rem) {
  .wtb-post-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  /* The content column is ~18rem narrower with a sidebar, so cap at 2. This is
   * (0,2,0) against the rule above's (0,1,0), so it wins on specificity and
   * does not depend on source order — but it must live INSIDE this query, or
   * it would also cap the 2-column band above at 2 (harmless) and, more to the
   * point, would not be scoped to the case it is about. */
  .wtb-layout--has-sidebar .wtb-post-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

/* Cards are flex columns and grid items stretch to equal height, so giving the
 * body the free space is all that is needed to align footers across a row of
 * posts with excerpts of different lengths. */
.wtb-entry-card > section {
  flex: 1;
}

/* Featured images arrive at whatever aspect ratio the author uploaded. Without
 * a fixed ratio a row of cards is ragged; `cover` crops rather than distorts. */
.wtb-entry-card > img:first-child {
  aspect-ratio: 16 / 9;
  width: 100%;
  object-fit: cover;
}

/*
 * Core's wp_list_comments() walker emits <p class="comment-awaiting-moderation">
 * for a held comment. Mapping it onto the alert look here is the adapter layer
 * doing its job — styling what a third party rendered. Replacing the walker to
 * change one paragraph would be a much larger surface to maintain across WP
 * releases for the same result.
 */
.comment-awaiting-moderation {
  display: block;
  margin-top: 0.5rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg, 0.5rem);
  padding: 0.75rem 1rem;
  background-color: var(--card);
  color: var(--muted-foreground);
  font-size: 0.875rem;
}

/* The comment form's cookie-consent row: core emits the checkbox and its label
 * as siblings inside .comment-form-cookies-consent. */
.comment-form-cookies-consent {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
```

- [ ] **Step 2: Build and confirm the CSS compiles**

Run: `npm run build`
Expected: exit 0. Tailwind fails loudly on a bad `@apply` or unknown utility, so a clean build is the check here.

- [ ] **Step 3: Commit**

```bash
git add src/css/adapter/index.css
git commit -m "feat(css): post grid, card and comment-form adapter rules"
```

---

## Task 2: The category-badge template tag

**Files:**
- Modify: `woodev-base-theme/inc/template-tags.php`
- Test: `tests/php/Unit/TemplateTagsTest.php` (create if absent — check first)

- [ ] **Step 1: Write the failing unit test**

Check whether `tests/php/Unit/` already has a template-tags test and follow its
conventions (Brain\Monkey, `TestCase` base class in that directory). The
function must:

- return early (print nothing) when the post has no categories;
- print one `<a class="badge" data-variant="secondary">` per category, with
  `esc_url( get_category_link() )` and `esc_html( $category->name )`;
- wrap the set in a `<div class="wtb-entry-categories">` only when there is at
  least one, so an empty wrapper never ships.

Write tests for all three, mocking `get_the_category`, `get_category_link`,
`esc_url`, `esc_html`, `esc_attr` with Brain\Monkey the way the neighbouring
unit tests do.

- [ ] **Step 2: Run and watch it fail**

Run: `composer test:unit`
Expected: FAIL — `woodev_base_category_badges()` is undefined.

- [ ] **Step 3: Implement**

Append to `inc/template-tags.php`:

```php
/**
 * Echo the current post's categories as Basecoat badge links.
 *
 * Built from get_the_category() rather than get_the_category_list(), which
 * returns finished markup with no way to put a class on the anchors.
 *
 * `secondary` rather than the default accent variant on purpose: a row of
 * accent-coloured chips under every title competes with the accent's actual
 * job on the page, which is the call to action.
 */
function woodev_base_category_badges(): void {
	$categories = get_the_category();

	if ( empty( $categories ) ) {
		return;
	}

	echo '<div class="wtb-entry-categories">';

	foreach ( $categories as $category ) {
		printf(
			'<a class="badge" data-variant="secondary" href="%1$s">%2$s</a>',
			esc_url( get_category_link( $category->term_id ) ),
			esc_html( $category->name )
		);
	}

	echo '</div>';
}
```

- [ ] **Step 4: Run the tests**

Run: `composer test:unit`
Expected: PASS.

- [ ] **Step 5: Mutation-test the escaping guard**

Temporarily change `esc_html( $category->name )` to `$category->name` and
confirm a test fails. If none does, the tests do not pin escaping — add one that
feeds a name containing `<script>` and asserts the escaped form. Revert, re-run.

- [ ] **Step 6: Lint and commit**

```bash
composer phpcs
git add woodev-base-theme/inc/template-tags.php tests/php/Unit/TemplateTagsTest.php
git commit -m "feat: category badge template tag"
```

---

## Task 3: The post card

**Files:**
- Modify: `woodev-base-theme/template-parts/content/content-excerpt.php`
- Modify: `woodev-base-theme/template-parts/content/loop.php`

- [ ] **Step 1: Rewrite the excerpt part as a card**

Replace the whole of `content-excerpt.php`'s markup with:

```php
<article id="post-<?php the_ID(); ?>" <?php post_class( 'wtb-entry wtb-entry--excerpt wtb-entry-card card' ); ?>>
	<?php
	// FIRST child on purpose: Basecoat rounds `.card > img:first-child` to the
	// card's top corners. A wrapping <div> would break that contract.
	if ( has_post_thumbnail() ) {
		the_post_thumbnail( 'medium_large', [ 'alt' => '' ] );
	}
	?>

	<header>
		<h2 class="wtb-entry-title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>

		<p class="wtb-entry-meta">
			<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
				<?php echo esc_html( get_the_date() ); ?>
			</time>
		</p>

		<?php woodev_base_category_badges(); ?>
	</header>

	<section class="wtb-entry-summary">
		<?php the_excerpt(); ?>
	</section>

	<footer>
		<a class="wtb-entry-more btn" href="<?php the_permalink(); ?>">
			<?php esc_html_e( 'Read more', 'woodev-base-theme' ); ?>
			<span class="sr-only">
				<?php
				printf(
					/* translators: %s: post title. */
					esc_html__( ' about "%s"', 'woodev-base-theme' ),
					esc_html( get_the_title() )
				);
				?>
			</span>
		</a>
	</footer>
</article>
```

Notes for whoever writes this:
- `mb-8` is gone — the grid's `gap` owns that spacing now.
- The title/meta utility classes are gone too: `.card > header > h2` and
  `> header > p` are already styled by the pack, and duplicating that in
  utilities would fight the pack rather than use it. Keep the `wtb-` hooks.
- `alt=''` on the thumbnail is deliberate: the image links nowhere and the title
  next to it carries the meaning, so an empty alt keeps it out of the a11y tree
  rather than making a screen reader read the filename.

- [ ] **Step 2: Wrap the loop in the grid**

In `loop.php`, wrap only the `while` loop's output — **not** the pagination and
**not** the empty state:

```php
if ( have_posts() ) {
	echo '<div class="wtb-post-grid">';

	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/content/content-excerpt' );
	}

	echo '</div>';

	get_template_part( 'template-parts/content/pagination' );
} else {
	get_template_part( 'template-parts/content/content-none' );
}
```

- [ ] **Step 3: Look at it in a browser**

`npm run wp:start` if needed, then open `http://localhost:8888/`. Confirm by eye:
cards in a grid, footers aligned across a row, badges under each title, and the
pagination full-width below the grid rather than inside it. **A screenshot or a
description of what you actually saw is required in your report — "it should
work" is not acceptable for a visual change** (AGENTS.md).

- [ ] **Step 4: Lint and commit**

```bash
composer phpcs
git add woodev-base-theme/template-parts/content/
git commit -m "feat: render post excerpts as cards in a grid"
```

---

## Task 4: Alerts for the empty, 404 and password-protected states

**Files:**
- Modify: `woodev-base-theme/template-parts/content/content-none.php`
- Modify: `woodev-base-theme/404.php`
- Modify: `woodev-base-theme/inc/Setup.php`

- [ ] **Step 1: `content-none.php`**

Turn the section into an alert, keeping the existing copy, the `is_search()`
branch and the search form. Shape:

```php
<div class="wtb-no-results alert">
	<?php woodev_base_icon( 'search' ); ?>
	<h2><?php esc_html_e( 'Nothing found', 'woodev-base-theme' ); ?></h2>
	<section>
		… existing copy, branch preserved …
	</section>
</div>
```

The search form stays **outside** the alert — it is an action, not part of the
message, and `.alert > section` styles its contents as muted body text.

Check `assets/static/icons/` for the icon name before using it; `search` is
present, but confirm rather than assume.

- [ ] **Step 2: `404.php`**

Same treatment for the "Page not found" block. Keep the `<h1>` as the page's
heading — do **not** demote it to fit the alert's title rule, since it is the
document's only h1 and a11y outranks a component's default styling. `.alert >
:is(h2,h3,h4,h5,h6,strong,[data-title])` will not match an `<h1>`, so add
`data-title` to it, which the same rule does match.

- [ ] **Step 3: The password form**

In `Setup.php`, register a filter on `the_password_form` that wraps core's form
in an alert explaining the post is protected. Follow the class's existing
registration style (see how its other hooks are added in `register()`).

The filter receives the complete form HTML and must return HTML; do not rebuild
core's form, wrap it. Escape nothing that core produced (it is already escaped);
escape only our own strings.

- [ ] **Step 4: Verify all three in a browser**

- 404: visit `http://localhost:8888/no-such-page/`
- empty search: `http://localhost:8888/?s=zzzzzznothing`
- password form: create a password-protected post via
  `npx wp-env run cli wp post create --post_title="Protected" --post_password=secret --post_status=publish --porcelain`, then visit it. Delete it afterwards.

Report what you actually saw for each.

- [ ] **Step 5: Lint and commit**

```bash
composer phpcs
git add woodev-base-theme/template-parts/content/content-none.php woodev-base-theme/404.php woodev-base-theme/inc/Setup.php
git commit -m "feat: alert treatment for empty, 404 and password-protected states"
```

---

## Task 5: Comment-form controls

**Files:**
- Modify: `woodev-base-theme/comments.php`
- Test: `tests/integration/Integration/CommentFormTest.php` (create)

- [ ] **Step 1: Write the integration test first**

A real WordPress renders the form; assert the **markup**, not the args array —
the args are our input, the markup is the contract, and core rewrites parts of
it between releases.

```php
<?php
/**
 * The comment form renders Basecoat form-control classes.
 *
 * Asserts the rendered markup rather than the argument array passed to
 * comment_form(): the array is our input, the HTML is the contract, and core
 * reshapes parts of that HTML between releases.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_UnitTestCase;

final class CommentFormTest extends WP_UnitTestCase {

	private static function render_comment_form(): string {
		$post_id = self::factory()->post->create();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		ob_start();
		comment_form( [], $post_id );
		$html = (string) ob_get_clean();

		wp_reset_postdata();

		return $html;
	}

	public function test_the_comment_textarea_carries_the_basecoat_class(): void {
		self::assertMatchesRegularExpression( '#<textarea[^>]*class="[^"]*\btextarea\b#', self::render_comment_form() );
	}

	public function test_the_submit_button_carries_the_basecoat_class(): void {
		self::assertMatchesRegularExpression( '#<(input|button)[^>]*class="[^"]*\bbtn\b#', self::render_comment_form() );
	}
}
```

Add a third test for the author/email text inputs carrying `.input`. Note the
author fields only render for logged-out visitors — check what the suite's
default user state is and, if the form comes back without them, log out
explicitly (`wp_set_current_user( 0 )`) rather than deleting the assertion.

- [ ] **Step 2: Run and watch them fail**

Run: `npm run test:integration`
Expected: FAIL — no `textarea`/`btn` classes yet.

- [ ] **Step 3: Pass the args in `comments.php`**

Replace the bare `comment_form();` with an argument array supplying
`comment_field` (a textarea carrying `class="textarea"`), `fields` (author,
email, url inputs carrying `class="input"`) and `class_submit => 'btn'`.

Build the fields with `sprintf()` and escape every attribute; copy core's
`id`/`name`/`aria-required` attributes exactly, because comment submission and
the `comment_form_default_fields` contract depend on them. Read core's
`comment_form()` in the container for the current default markup rather than
copying an example off the web:

```bash
MSYS_NO_PATHCONV=1 npx wp-env run cli --config=.wp-env.test.json bash -c "sed -n '/function comment_form/,/^}/p' /var/www/html/wp-includes/comment-template.php" | head -120
```

- [ ] **Step 4: Run the tests**

Run: `npm run test:integration`
Expected: PASS.

- [ ] **Step 5: Mutation-test**

Remove `class="textarea"` from the `comment_field` arg; the textarea test must go
red. Revert, confirm green.

- [ ] **Step 6: Verify a real comment still submits**

Classes are cosmetic, but rebuilding core's fields can break submission if an
`id` or `name` drifts. Post a comment through the form in a browser at
`http://localhost:8888/?p=<some post id>` and confirm it is accepted. Report
what happened.

- [ ] **Step 7: Lint and commit**

```bash
composer phpcs
git add woodev-base-theme/comments.php tests/integration/Integration/CommentFormTest.php
git commit -m "feat: Basecoat classes on the comment form controls"
```

---

## Task 6: e2e

**Files:**
- Create: `tests/e2e/components.spec.mjs`
- Modify: `tests/e2e/theme-mods.spec.mjs`

- [ ] **Step 1: Write `components.spec.mjs` — READ-ONLY**

This file must not change a single theme_mod: `theme-mods.spec.mjs` owns those
and Playwright parallelises by file. Use the `{ page }` fixture only, never
`browser.newPage()` (`docs/gotchas/playwright-browser-newpage-skips-config.md`).

Cover:

1. **Grid column count at three viewports** — 375px → 1 track, 800px → 2, 1400px
   → 3. Assert from computed style:
   ```js
   const tracks = await page.evaluate(() =>
     getComputedStyle(document.querySelector('.wtb-post-grid')).gridTemplateColumns.split(' ').length,
   );
   ```
   `grid-template-columns` resolves to concrete pixel tracks, so the number of
   tracks is the column count. Counting visible cards instead would pass
   silently with too few posts.
2. **Cards render as cards** — `.wtb-entry-card.card` count > 1 on the front
   page, each with a `header`, a `section` and a `footer` child.
3. **Badges** — at least one `.badge[data-variant="secondary"]` inside a card
   header, and its `href` points at a category archive that returns 200.
4. **404 alert** — `/no-such-page/` renders `.alert` containing an `h1[data-title]`.
5. **Empty-search alert** — `/?s=zzzzzznothing` renders `.alert` and no
   `.wtb-post-grid`.
6. **Comment form controls** — on a single post, `textarea.textarea` and the
   submit carrying `.btn` are present and visible.
7. **Dark mode** — repeat the card assertion with the dark scheme active, using
   the same runtime-toggle approach the existing specs use (read
   `smoke.spec.mjs` / `theme-mods.spec.mjs` for the established pattern rather
   than inventing one).

- [ ] **Step 2: Add the sidebar cap case to `theme-mods.spec.mjs`**

Inside that file's existing serial structure and using its `theme-mod.mjs`
helper, add: with the sidebar enabled and a 1400px viewport, `.wtb-post-grid`
has **2** tracks, not 3. Restore the theme_mod afterwards exactly as the
neighbouring tests do.

- [ ] **Step 3: Run**

Run: `npm run e2e`
Expected: all green. Note this suite takes ~25 minutes.

- [ ] **Step 4: Mutation-test the two load-bearing guards**

- Remove the `@media (min-width: 80rem)` block from the adapter → the 1400px
  three-column assertion must go red.
- Remove `.wtb-layout--has-sidebar .wtb-post-grid` → the sidebar-cap test must go
  red.

Rebuild (`npm run build`) between the mutation and the run — the CSS is compiled.
Revert each, rebuild, confirm green. Report real output.

- [ ] **Step 5: Commit**

```bash
git add tests/e2e/
git commit -m "test: e2e for the card grid, badges, alerts and comment controls"
```

---

## Task 7: Gate, critic, PR

- [ ] **Step 1: Full gate**

```bash
composer phpcs
composer phpstan
composer test:unit
npm run test:js
npm run build
npm run test:integration
npm run test:integration:dev
npm run e2e
npm run e2e:dev
```

Record every count.

- [ ] **Step 2: Codex critic**

Two focused chunks (PHP/templates, then CSS/e2e), per
`docs/CURRENT-STATE.md`'s "Open items": default profile,
`-c 'mcp_servers={}'`, foreground, prompt under ~15 KB, stdin closed, smoke-test
`"Reply with exactly: CODEX_OK"` first, tell it not to read `.claude/skills/**`,
and name the out-of-chunk guards in each prompt.

Then **re-critic the fixes** — never self-certify. If a third round finds
narrower defects in the same unit, stop and change the approach
(`docs/gotchas/three-rounds-of-fixes-means-change-the-approach.md`).

- [ ] **Step 3: PR**

Push, open the PR with the gate counts and the critic findings, and stop.
**Merging is Maksim's call.**

---

## Definition of done

1. Cards, badges and alerts render on real pages; verified in a browser, not asserted.
2. Unit, integration and e2e green; the grid breakpoints and the sidebar cap mutation-verified.
3. phpcs, phpstan L8, build green.
4. i18n and escaping correct on every new user-facing string.
5. A11y: the card's heading level is unchanged (h2 in lists), the featured image is decorative (`alt=''`), the 404 keeps its h1, badges are real links.
6. Codex critic passed, with a re-critic on the fixes.
