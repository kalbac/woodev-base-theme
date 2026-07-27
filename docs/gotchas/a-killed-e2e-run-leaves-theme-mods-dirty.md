# A killed e2e run leaves theme_mods dirty, and the next run blames the product

**Area:** Testing / e2e · **Found:** s15 (27.07.2026), twice in one session

## What happens

`tests/e2e/theme-mods.spec.mjs` owns every `theme_mod` mutation and restores each one
after the test that made it. That works — right up until the run is killed. A timeout, a
`TaskStop`, a Ctrl-C: the restore never executes, and the mod stays in the database.

The next run then fails somewhere else entirely:

```
tests\e2e\components.spec.mjs:33 › the post grid resolves 3 track(s) at 1400px
  Expected: 0
  Received: 1
  Locator: locator('.wtb-layout--has-sidebar')
```

Nothing is wrong with the post grid. A leftover `sidebar_position=right` put a sidebar on
the page, and a sidebar legitimately caps the grid at two tracks. It happened twice in one
session, with two different mods — `sidebar_position` first, then `base_font_size` after a
second killed run, which failed *"an untouched site emits no inline style block"* instead.

## Why it is worth writing down

The failure appears in a **different spec file** from the one that caused it, and it looks
exactly like a product defect. Both times the instinct was to debug the breakpoint.

What saved it both times was that the specs assert their own preconditions. `components.spec.mjs`
does not merely measure tracks — it asserts `.wtb-layout--has-sidebar` has count 0 first,
with a comment naming this exact scenario. That guard turned a confusing red into a
one-line diagnosis. **Assertions about the world your test assumes are worth as much as
assertions about the thing it measures.**

## What to do

- **Before diagnosing an e2e failure, read the mods**, especially after any interrupted run:
  ```
  npx wp-env run cli wp option get theme_mods_woodev-base-theme --format=json
  ```
  A clean tree has `nav_menu_locations`, `custom_css_post_id` and the two `color_scheme_*`
  entries. Anything else is residue.
- **Clear it, do not "restore" it:** `wp theme mod remove <name>` returns the setting to its
  documented default. Setting it back to a value you guessed is how a wrong default gets
  pinned by a passing test.
- **Do not kill a `theme-mods` run** if it can be avoided. The full base suite is ~10 min
  serial; run it split (`--grep-invert "theme_mods"`, then `--grep "theme_mods"`) rather
  than starting something that will be cut off at a timeout.
- When adding a spec that depends on site state, **assert the precondition explicitly**.
  The cost is one line; the alternative is a future session debugging the wrong file.

## Related

- [[qa-gates-cover-less-than-they-claim]] — the sibling failure mode: a result that looks like something it is not
- [[parallel-agents-share-one-worktree-and-one-wp-env]] — the other way one environment's state leaks between runs
- [[wp-env-mounts-the-theme-live]] — another symptom that points at the wrong file
