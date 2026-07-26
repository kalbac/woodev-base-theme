# CSS/JS indent is 2 spaces (not tabs) and no gate catches a violation

**Area:** Tooling / QA
**First hit:** s9 (M2a Task 5, `src/css/woo.css` shipped tab-indented)

## What happens

`.editorconfig` sets **tabs for PHP** but **2 spaces for CSS/JS**:

```
[*.{js,mjs,css,json,yml,yaml}]
indent_style = space
indent_size = 2
```

A worker (or an agent) told "this repo uses tabs" applies tabs everywhere,
including new CSS. A tab-indented `.css` file:

- **builds fine** — Vite/Tailwind don't care about indentation;
- **passes `composer phpcs`** — PHPCS lints PHP, never CSS;
- **passes unit/e2e** — they test behaviour, not formatting;

so the drift ships silently. Nothing in the standard gate
(`phpcs` / `build` / tests) reads CSS whitespace. Only a human review — or
`cat -A file.css` showing `^I` — catches it. In s9 the whole `@layer adapter`
block of `woo.css` shipped tab-indented; the code-quality review caught it, no
tool did.

## The rule

- **PHP** (`inc/`, templates, tests): **tabs** (WPCS).
- **CSS, JS/MJS, JSON, YAML**: **2 spaces** (`.editorconfig`), matching the hand-authored `src/css/adapter/index.css`.

After writing or editing any CSS/JS file, confirm the indentation matches
`.editorconfig` — `grep -nP '^\t' file.css` (or `cat -A`) should find nothing.
When instructing a worker, say "tabs for PHP, 2 spaces for CSS/JS" explicitly;
a bare "this repo uses tabs" is wrong for half the file types.

## Related

- [[qa-gates-cover-less-than-they-claim]] — exit 0 only means "the files this gate looked at were clean"; no gate looks at CSS whitespace
- [[serena-writes-native-line-endings]] — the other formatting drift no behavioural test catches
