# Open Design (OD) run pitfalls — agent billing, sticky plugins, project contamination, inline tokens

**Area:** Tooling / Open Design · **Found:** s10 (whole-theme visual-identity mockups via OD MCP)

OD (`open-design` MCP → local Electron daemon) commissions a subagent to generate a design. Four traps cost real time and money this session.

## 1. The `amr` agent spends a PAID AMR Cloud wallet — not your model subscription

`start_run({agent:"amr", model:"claude-opus-4.8"})` routes through **AMR Cloud**, a separate top-up wallet. It ran dry mid-run and failed with `AMR_INSUFFICIENT_BALANCE` — and it had silently burned the operator's credits on the earlier runs. The `amr` agent is attractive because it exposes "best_quality" model labels, but it is **not** the user's own Claude/Codex subscription.

- **Fix:** omit `agent` (OD uses the user's configured default — here Codex), or pass `agent:"claude"` (uses the local Claude Code / Claude subscription) or `agent:"codex"` (local Codex subscription). Never `amr` unless the operator has funded that wallet on purpose.
- The user's OD default agent was Codex the whole time; specifying `amr` overrode it.

## 2. A `fable-5` run can stall at `waiting_for_first_output` forever

The first run (`amr` + `claude-fable-5`) produced **zero** tokens for ~7 min, frozen at `waiting_for_first_output` (elapsedMs never advanced past ~4s). That is a stalled model endpoint, distinct from OD's "silence = the agent is thinking between writes" (which only applies **after** output has begun). Cancel + re-run on a proven model (`claude-opus-4.8` worked immediately).

## 3. Plugins stick to a project; a "refine" run gets hijacked; reused projects contaminate a fresh design

- `community-hallmark` (installed) **auto-attaches to every run in that project** (`appliedPluginSnapshotId` appears even when you don't pass `plugin`). A surgical "read the file and apply these 6 edits" prompt was **hijacked into hallmark's own critique second-lap** — it only fixed 3 AA-contrast issues and ignored the brief entirely (kept the petrol accent, black plates).
- Re-running in a project that already holds files + a captured native session (`nativeSessionRecovery.continuation:"native-resume-by-id"`) makes the agent **continue/rework the previous output**. Codex "reworked v2" because the v3 project still held a prior run's `build-on-v2` files and resumable session.
- **Fixes:** for a **fresh** design → a **brand-new empty project** and say "fresh, no reference to match". For a **surgical refine** → editing the artifact files **directly** (Read/Edit/sed on `…/data/projects/<id>/…`) is more reliable than another OD run, because you can't easily run plugin-less in a plugin-stuck project.

## 4. The artifact HTML is self-contained — it has its OWN inline copy of the tokens

The deliverable `woodev-base-identity.html` carries a full `:root{…}` token block **inline**; `tokens.css` is a separate *portable export*. Editing `tokens.css` does **nothing** to the rendered preview. Edit the inline `<style>` in the HTML (and mirror into `tokens.css` for the eventual theme port).

## Also useful
- Discovery `<question-form>`: answer it via `start_run` again in the **same** project (native-resume) — or have the user submit in the OD UI. Don't do both (double-submit).
- Preview a run's rendered output at its `previewUrl` (`http://127.0.0.1:<port>/api/projects/<id>/raw/<file>`); the daemon serves the file fresh on each request, so edit-on-disk → reload works.
- Tail `eventsLogPath` (`…/runs/<id>/events.jsonl`) for live progress: `Write`/`Edit`/`Bash` tool_use, `text_delta`. The `claude` agent builds via many `Edit`s; `codex` via `Bash`.

## Related
- [[svg-use-shadow-boundary-needs-custom-props]] — the plate-rendering bug fixed during the same refine
