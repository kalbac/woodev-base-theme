# `wp-env run cli` fails intermittently under load, and a failed RESTORE poisons the environment

> Found s17 (28.07.2026) — three failures in one session, two of them inside a teardown.

## The trap

`npx wp-env run cli wp …` is not reliable while a Playwright suite is running. Three separate
failures in one session, on healthy containers:

| Where | Command | What it looked like |
|---|---|---|
| Suite start | `wp menu item add-post …` | `Environment not initialized. Run 'wp-env start' first.` — while the environment was up |
| Mid-suite | `wp theme mod set color_scheme_default system` | `Command failed`, no further detail |
| **Teardown** | `wp option update show_on_front posts` | `Command failed` — and the restore never happened |

Probed on the same containers immediately afterwards, three consecutive `wp option get siteurl`
calls all succeeded, and `docker ps` showed every container up with MySQL healthy. So the failure
is **load-related and intermittent**, not a broken environment — which is what makes it easy to
misread as a product defect on the test that happened to be running.

## Why the teardown case is the expensive one

`tests/e2e/theme-mods.spec.mjs` is the one file allowed to mutate site-global state, and it
restores what it touched. When the restore itself fails:

1. the test reports **failed**, pointing at an assertion that in fact passed;
2. the site is left with `show_on_front = page` and `page_on_front` set to a fixture page;
3. the same teardown's next step **deletes that page**;
4. every later run — this session's and the next session's — starts on a front page that does
   not exist.

That is what s17 left behind, measured after the fact:

```
$ wp eval 'echo get_option("show_on_front"), " / ", (int) get_option("page_on_front");'
page / 686
$ wp eval 'echo get_post(686)->post_title;'
E2E Static Front      # published, orphaned, and about to be deleted
```

A poisoned environment does not announce itself. The next run just behaves oddly.

## What to do

- **`wp()` in `tests/e2e/lib/theme-mod.mjs` retries once** (s17). A second failure rethrows the
  first error untouched, so the file's own rule — never swallow a read error, never round-trip a
  value you could not read — still holds. One retry is not a fix for a broken container; it is a
  fix for a container that is momentarily busy.
- **Read the error, not the test name.** `Command failed: npx wp-env run cli …` in an
  `error-context.md` is an infrastructure failure regardless of which test reported it. Check
  whether it came from the assertion or from `beforeAll`/`afterEach` before believing the test
  found something.
- **After any run that failed in teardown, check the site state by hand** —
  `show_on_front`, `page_on_front`, and `wp theme mod list` — and restore it before the next
  run. A leftover `color_scheme_default` also silently changes what the next run measures.
- Do not run two suites, or a suite and a `codex exec` pass, against the same machine at once.
  Every observed failure happened with something else running.

## Related

- [[qa-gates-cover-less-than-they-claim]] — the parent pattern: a red result that is not about the code, and a green one that measured nothing
- [[codex-cli-dies-silently]] — the other tool here whose failure modes are indistinguishable from success
- [[wp-env-mounts-the-theme-live]] — the other wp-env failure whose symptom points at the wrong file
- [[wp-env-runs-a-tests-environment-nobody-uses]] — wp-env's environments and which one owns what
