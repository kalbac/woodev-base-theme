# The Codex critic fails in four different ways, and every one of them exits 0

> Discovered s3 (17.07.2026): six runs to get one review out of the mandatory critic gate. Supersedes the s2 note that `-c 'mcp_servers={}'` works — it does not.

## Why this matters

`AGENTS.md` makes a Codex review a merge gate and forbids self-certifying fixes made in response to one. So a critic that *looks* like it ran but didn't is worse than one that plainly errors: **every failure below returns exit code 0**, prints something, and reads like success at a glance. Always confirm the log actually contains a verdict before believing the gate passed.

## The four failure modes

| # | Symptom | Cause | Fix |
|---|---|---|---|
| 1 | Job "running" for 15 min, log dead | MCP servers load and hang/403 (`supermemory`) | Clean `CODEX_HOME` (below) |
| 2 | Prints the prompt, then `ERROR rmcp::transport… HTTP 403: forbidden`, exits 0 | same | same |
| 3 | `node: Argument list too long`, exits 0 | prompt >~32 KB in argv (Windows limit) | keep the prompt small |
| 4 | Prints the prompt, exits 0, **no verdict at all** | run in background via `nohup`/`&` | run in the **foreground** |

**`-c 'mcp_servers={}'` does not disable MCP.** The s2 CURRENT-STATE note claiming it works was wrong: the servers still load and the run still dies. The flag was never the thing that helped.

**`codex exec -` (prompt on stdin) silently no-ops** — it echoes the prompt and exits 0.

**Codex dies after a large tool output too.** Runs where it was asked to read `AGENTS.md` + two SKILL.md files, or a 34 KB brief, ended mid-stream right after the read. Keep what it must read small; prefer inlining a trimmed diff over making it read files.

**The `codex:codex-rescue` subagent can't run it at all** on this machine: process spawning inside the agent sandbox fails with `CreateProcessAsUserW failed: 5`. To its credit it refused to fabricate findings and reported every verdict as UNVERIFIED — but you must invoke Codex yourself.

## The working recipe

```sh
# One-time: a home with auth but no MCP servers and no hooks.
export CODEX_HOME=/c/Users/maksi/.codex-review-clean
mkdir -p "$CODEX_HOME"
cp ~/.codex/auth.json "$CODEX_HOME/"
cat > "$CODEX_HOME/config.toml" <<'TOML'
model = "gpt-5.6-sol"
model_reasoning_effort = "medium"
personality = "pragmatic"
service_tier = "default"
TOML

# Per review: foreground, prompt inline and under ~15 KB, stdin closed.
timeout 570 codex exec --sandbox read-only --skip-git-repo-check "$(cat prompt.md)" < /dev/null 2>&1 \
  | sed -n '/^codex$/,/^tokens used/p'
```

Build the prompt as: project canon (paste the relevant AGENTS.md rules — do **not** make it read the file), the specific claims to challenge, and `git show <sha> --unified=15 -- <code paths only>` (excluding docs keeps it under the size limit). A ~10–15 KB prompt works; both reviews this session landed at ~10 k tokens.

Verify before trusting: the log must contain a `tokens used` marker **and** an actual verdict. Anything else is one of the four failures above.

## How to apply here

- Sanity-check the recipe with a smoke prompt (`"Reply with exactly: CODEX_OK"`) before a long review — it costs ~4 k tokens and distinguishes "environment broken" from "review found nothing".
- The critic is worth the trouble: in s3 it caught a real incomplete guard (`is_file()` passes an unreadable file) and a self-contradiction in freshly written docs. Do not skip the gate because the tooling is annoying.

## Related

- [[wp-json-file-decode-warns-on-missing-file]] — the finding this critic produced once it ran
