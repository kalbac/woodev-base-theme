# The Codex critic works here — but only inline on stdin, with a no-tools preamble and an explicit effort

**Area:** Tooling / Codex · **Found:** s14 (26.07.2026)

## What happens

Two independent failures make the gate look broken when it is not.

**1. Any route that asks Codex to READ the diff returns an empty pass.**
`/codex:adversarial-review --base <ref> --scope branch` came back in 49 seconds with
`verdict: approve` and this body:

> No defensible ship blocker established. The required diff inspection could not run
> because the read-only shell is denied by the sandbox (`CreateProcessAsUserW failed: 5`),
> so this is unverified rather than a positive safety assessment.

An `approve` that means "I read nothing". Exit code 0. Left unchallenged it waves work
through the merge gate. The cause is the sandbox limit already recorded in
[[codex-sandbox-blocks-shell-and-outside-files]]; what s14 adds is that the plugin route
does not work around it.

**2. Without an explicit effort, the critic does materially less work.**
Same chunk, same prompt, run twice: **4,605 tokens** at the default versus **16,767** with
`model_reasoning_effort="high"` — 3.6×. The verdict happened to agree both times, which is
exactly what makes this dangerous: a cheap `clean` is indistinguishable from a real one
unless you look at the token count.

## The recipe that works

Ported from `autodev-harness`'s `src/critic/codex-adapter.ts`, which has run this gate for
dozens of sessions on the same machine without hitting either problem — it never asks Codex
to read anything.

```bash
codex exec \
  -c 'mcp_servers={}' \
  -c 'approval_policy="never"' \
  -c 'model_reasoning_effort="high"' \
  -s read-only -C <repo-root> --skip-git-repo-check - < prompt.txt
```

- **The whole prompt, diff included, goes on stdin.** Build the file as header + `git diff`
  appended; never hand Codex a path. Chunks of 18–26 KB worked fine in s14 (the older
  "<15 KB" figure is conservative, not a hard limit).
- **Open with a NO-TOOLS preamble** — "Do NOT run any shell command, read any file, or
  invoke any skill/plugin/MCP tool; subprocess spawning is blocked by the sandbox; the
  COMPLETE diff is inline below; review it from the text alone, in one turn." Without it a
  newer Codex CLI tries to spawn its own installed skills at turn start and can loop on the
  sandbox failure until killed.
- **Foreground.** Background runs die on those same spawn failures.
- **State the guards that live outside the chunk** (lint, the other suites, architectural
  decisions like "this file is deliberately un-layered"), or the critic reports their
  absence as defects — see [[codex-split-diff-false-positives]].
- **Watch the token count in the output.** A verdict that cost ~5k tokens on a 20 KB diff
  did not do the work; re-run it with the effort raised.

## Smoke-test before concluding anything about the account

`codex exec … "Reply with exactly: CODEX_OK"` costs seconds. In s14 an empty `approve` was
first read as an expired subscription; the smoke test returned `CODEX_OK` with tokens
billed, which settled it in one command and pointed at the real cause. The reverse also
happened later the same day: five real review passes exhausted the quota and the next run
failed with `You've hit your usage limit`. Both states are one command away from being
distinguished — measure, do not infer.

The `HTTP 403` from the MCP transport and `DELETE returned HTTP 500` on session teardown
appear in every run and mean nothing.

## What this does not buy you

A `clean` from a high-effort pass is still not proof. In s14 the high-effort review of
`woo-blocks.css` returned clean, and the very next worker — reading the vendor stylesheet
for an unrelated rule — found that our resting `color` override tied WooCommerce's
`.has-error` rule and silently erased an invalid select's red text. Treat the critic as one
layer, not the layer.

## Related

- [[codex-sandbox-blocks-shell-and-outside-files]] — the sandbox limits underneath this
- [[codex-cli-dies-silently]] — older failure modes, all exiting 0
- [[codex-split-diff-false-positives]] — why chunked prompts must name out-of-chunk guards
