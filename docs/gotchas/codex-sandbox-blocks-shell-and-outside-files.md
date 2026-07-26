# Codex in this sandbox: no shell, no files outside the workdir — and `mcp_servers={}` removes the fallback

**Area:** Tooling / Codex · **Found:** s13 (26.07.2026)

## What happens

Two separate limits compound, and the s12 recipe makes the second one fatal.

1. **Codex's shell is dead here.** Every `exec` fails with
   `windows sandbox: runner failed during SpawnChild: CreateProcessAsUserW failed: 5`.
   s12 recorded this as harmless ("it falls back to Serena/node and works fine").
2. **Its sandbox is `workspace-write [workdir, /tmp, $TMPDIR]`.** A diff written to the
   session scratchpad under `AppData/Local/Temp/claude/...` is **outside** that, so Codex
   cannot read it even when the shell works.
3. **`-c 'mcp_servers={}'` disables Serena** — which is the very fallback that made file
   reading possible. With the shell dead AND MCP off, Codex can read nothing at all and
   answers "не могу дать обоснованные findings".

The failure is silent in the sense that matters: the run exits fine, the model replies
politely, and you get a review-shaped answer containing no review.

## What to do

- **Inline the content in the prompt.** Do not point Codex at a path. Split into chunks
  under ~15 KB and paste the diff, the vendor rules, and the built output directly.
  This is the only approach that worked reliably.
- If you must use files, they have to live **inside the repo workdir** (e.g. a temporary
  untracked `.critic/`), never in the scratchpad — and even then the dead shell means
  Codex needs MCP to read them, so drop `mcp_servers={}`.
- ~~**Prefer the plugin over hand-rolled `codex exec`.**~~ **Corrected s14: the plugin
  does NOT handle this.** `/codex:adversarial-review` returned `verdict: approve` with
  the body "the required diff inspection could not run because the read-only shell is
  denied by the sandbox (`CreateProcessAsUserW failed: 5`)" — a review-shaped answer
  containing no review, and an `approve` that would have waved work through a merge
  gate. The plugin asks Codex to go and read the diff; that is precisely what the
  sandbox forbids. The working route is a hand-rolled call that hands Codex everything
  up front — see [[codex-critic-needs-inline-stdin-and-explicit-effort]].

## Two more things seen in the same session

- A **transient** startup failure claimed every model was unavailable
  (`The '<model>' model is not supported when using Codex with a ChatGPT account`, for six
  different model ids), alongside
  `failed to renew cache TTL: missing field 'supports_reasoning_summaries'`. It cleared on
  its own minutes later. Do not conclude the account lost access from one failed run —
  smoke-test again before declaring the gate broken.
- The `HTTP 403` / `DELETE returned HTTP 500` lines from the MCP worker are noise and do
  not affect the result.

## Related

- [[codex-cli-dies-silently]] — the older failure modes, all exiting 0
- [[codex-split-diff-false-positives]] — why chunked prompts must name out-of-chunk guards
