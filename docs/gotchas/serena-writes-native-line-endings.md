# Serena writes CRLF on Windows — and `line_ending: "lf"` does NOT stop it

**Area:** Tooling · **Found:** s6, on the first symbol-level edit after adopting Serena · **Corrected s7**

## The trap

Every Serena write to a file rewrites the **whole file** with native line endings
— CRLF on Windows — and `composer phpcs` then fails on **line 1**:

```
Generic.Files.LineEndings.InvalidEOLChar — expected "\n" but found "\r\n"
```

The sniffs never reach the code, so the local QA gate is unrunnable while CI
(Linux, LF) stays green. This is the third distinct route this project has found
into the same failure — after `core.autocrlf` (fixed by `.gitattributes`) and a
Python helper writing in text mode.

## The correction: the config key does not work

s6 recorded the fix as "set `line_ending: "lf"` in `.serena/project.yml`" and
closed the matter. **That is wrong, and this file asserted it for a whole
session.** The key has been set since s6 and Serena still writes CRLF. Measured
s7, both write paths, on a repo whose `.serena/project.yml` contains
`line_ending: "lf"`:

| Write path | Result |
|---|---|
| `create_text_file` (new file) | CRLF |
| `replace_symbol_body` (existing PHP file) | CRLF — and it converts the **entire file**, not just the edited symbol |

The `replace_symbol_body` probe replaced a method body with a byte-identical
copy: `git diff` reported no content change, while `file` reported
`with CRLF line terminators` and 172 of the file's lines had grown a `\r`.

**What actually works** — do this after *any* Serena write, every time:

```sh
sed -i 's/\r$//' <file>
git ls-files --eol <file>   # must read: i/lf  w/lf
```

Two workers hit this in s7 and both worked around it by hand. Until Serena fixes
the key, treat CR-stripping as part of the edit, not as a troubleshooting step —
and note that a clean `git diff` does **not** mean the working copy is clean,
because git normalizes on read while PHPCS reads the real bytes.

## Two tools also want to fight Serena over its own files

Serena rewrites `.serena/*.yml` with native endings *by design*, and that is not
configurable. So:

- **`.gitattributes`** marks `.serena/** -text` — otherwise git checks the file
  out as LF, Serena rewrites it as CRLF, and it is permanently dirty in
  `git status`.
- **`.prettierignore`** excludes `.serena/` — `npm run format` is part of the
  gate and would fail on every Serena edit.

Neither path is linted, so the reason `eol=lf` exists (PHPCS on Windows) does not
apply inside `.serena/`.

## Also worth knowing about the config

- `ls_workspace_folders` is scoped to `./woodev-base-theme`. `find_symbol` and
  `find_referencing_symbols` therefore **do not see `tests/`** — a symbol's test
  usages will not appear in a reference search. `search_for_pattern`,
  `read_file` and `list_dir` still work project-wide.
- `.serena/.gitignore` (Serena's own) excludes `cache/` and `project.local.yml`,
  which means `project.yml` **is** meant to be versioned. Do not add `.serena/`
  to the project `.gitignore` wholesale.

## Related

- [[qa-gates-cover-less-than-they-claim]] — a gate that cannot run proves nothing
