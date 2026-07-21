# Splitting a diff for the Codex critic manufactures false positives

> Discovered s4 (20.07.2026). The M1-02 diff was ~34 KB — over the Codex CLI's safe prompt size — so it was reviewed in three chunks. Two of the critic's findings were artefacts of the split.

## Why the split

`docs/gotchas/codex-cli-dies-silently.md`: keep the Codex prompt under ~15 KB or the run dies. A large change must be reviewed in pieces. M1-02 went out as three focused reviews (root templates + resolver; template parts; navigation + CSS/JS).

## The trap

A finding is only sound if the reviewer can see the code that would refute it. When a guard lives in a chunk the current prompt does **not** include, the critic reports the missing guard as a defect. Both false positives this session were exactly that:

- **"Empty sidebar isn't collapsed"** — `has_sidebar()` already calls `is_active_sidebar( 'sidebar-1' )`, but `Layout.php` was in a *different* chunk than `sidebar.php`.
- **"The menu/x SVGs lack `aria-hidden`"** — `woodev_base_icon()` emits `aria-hidden` for an unlabelled icon, but `Icons.php` (merged earlier, not in the diff at all) was invisible to the review.

Both read as plausible P2/P3s and would have sent you chasing non-bugs.

## How to run split reviews

- **Name the boundaries in the prompt.** State which helpers/guards live outside this chunk and are assumed correct ("`Layout::has_sidebar()` already guards `is_active_sidebar`; `woodev_base_icon()` self-hides decorative icons"). The critic stops flagging them.
- **Verify every split-review finding against the whole tree before acting.** A finding about a guard, escaping, or a helper is suspect until you confirm the guard really is absent *everywhere*, not just in the chunk shown.
- Prefer splitting by **concern** (escaping/i18n vs a11y) over by file, and keep a helper and its callers in the same chunk when you can.

## Related

- [[codex-cli-dies-silently]] — why the diff has to be split in the first place.
- [[qa-gates-cover-less-than-they-claim]] — the same lesson from the other side: a signal is only as good as what it actually looked at.
