# Parallel agents share one worktree and one wp-env — `git stash` and a second e2e run both destroy work

**Area:** Process / Tooling · **Found:** s12 (six workers running in parallel on the identity fixes)

## What happened, twice each

**`git stash` on a shared worktree.** Two separate workers, wanting to compare their changes against the baseline, ran `git stash` — which stashes the **entire working tree**, including four other agents' uncommitted, unfinished work — then `git stash pop`. Both times everything came back. Both times that was luck: a `pop` conflict, or another agent writing a file between the stash and the pop, would have destroyed work with no commit to recover it from.

**One wp-env, two Playwright runs.** Two workers ran `tests/e2e/*` against the same `:8888` environment at the same time. `tests/e2e/global-setup.mjs` is destructive by design — it *reseeds* fixtures: delete every page/post/menu/category with the known slug, then recreate it. Run two of those concurrently and each deletes the other's freshly created rows mid-run. The failures do not look like a race:

```
Error: Command failed: wp menu item add-post 27 118 --porcelain
Error: Invalid post.
```
```
Error: Command failed: wp post create … --post_category=36 --porcelain
Error: No such post category '36'.
```

Both read as "the seed script is broken" — the post/term was created moments earlier and the ID is right there in the log. `global-setup.mjs` had not changed and worked in the previous session. After the second collision the `:8888` database was left in a state where even a serial run failed the same way; `npx wp-env clean all` (now `reset`) fixed it.

## Why

Subagents do not get their own copy of the repository. They share the orchestrator's worktree, its `node_modules`, and every wp-env container. Nothing in git or wp-env is aware that six writers exist, so any whole-tree or whole-database operation is a broadcast.

## Rules

**In every parallel worker prompt, forbid whole-tree git commands explicitly.** `git stash`, `git checkout`, `git reset`, `git clean`. To compare against baseline, use what is scoped:

```bash
git diff -- <path>        # only my files
git show HEAD:<path>      # the committed version of one file
```

**Serialise every suite that owns a mutable environment through the orchestrator.** Tell workers to write the tests, run `npm run build` / lint / unit suites (which are read-only and safe in parallel), and report *which spec to run and what they expect*. The orchestrator runs the e2e suites one at a time and reports results back for mutation verification. Different config = different port = safe to overlap (`:8888` base, `:8889` integration, `:8891` woo, `:8892` dev-mode), but two runs against the *same* config never are.

**When a seed script fails on data it just created, suspect the environment before the script.** Check for a concurrent run first; then `npx wp-env reset` and re-run serially before debugging a single line of the setup.

## Related

- [[wp-env-installs-themes-without-activating-them]] — the other case where a wp-env state problem masqueraded as a product bug
- [[playwright-browser-newpage-skips-config]] — same lesson at test level: a green run alone proves nothing about a run in company
- [[qa-gates-cover-less-than-they-claim]]
