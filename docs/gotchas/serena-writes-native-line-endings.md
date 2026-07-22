# Serena writes native line endings — pin `line_ending: "lf"` or PHPCS dies

**Area:** Tooling · **Found:** s6, on the first symbol-level edit after adopting Serena

## The trap

`.serena/project.yml` ships `line_ending:` **unset**, which means *native* — CRLF
on Windows. The first `replace_symbol_body` on a PHP file therefore rewrote it
with CRLF, and `composer phpcs` failed on **line 1**:

```
Generic.Files.LineEndings.InvalidEOLChar — expected "\n" but found "\r\n"
```

The sniffs never reach the code, so the local QA gate is unrunnable while CI
(Linux, LF) stays green. This is the third distinct route this project has found
into the same failure — after `core.autocrlf` (fixed by `.gitattributes`) and a
Python helper writing in text mode.

**Fix:** `line_ending: "lf"` in `.serena/project.yml`, matching the repo's
`* text=auto eol=lf`.

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
