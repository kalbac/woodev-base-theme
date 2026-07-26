# Serena's `replace_symbol_body` can duplicate a class header and emit invalid PHP

**Area:** Tooling / Serena · **Found:** s12 (rewriting `inc/Woo/ProductPlaceholder.php`)

## What happened

A worker replaced a whole class body with `replace_symbol_body`, passing a replacement that began — as the tool's own contract says it should, since the body of a class symbol includes its signature line — with a fresh docblock and `final class ProductPlaceholder {`.

Serena kept the ORIGINAL docblock and `final class` keyword in place and spliced the replacement in after them, producing:

```php
final class /** … new docblock … */ final class ProductPlaceholder {
```

Invalid PHP. It was caught only because the worker read the file back before running the gate; `phpcs`/`phpstan` would have caught it too, but only after a confusing failure far from the cause.

The same worker edited `CardActionsWrapper.php` and `CtaAttribute.php` the same way in the same session and both came out correct — so the tool's idea of where a symbol's body starts is **not consistent across files**, which is worse than being consistently wrong.

## Why this matters more than it looks

`replace_symbol_body` is the tool AGENTS.md mandates precisely because a symbol-level edit "either resolves the symbol or fails loudly, whereas a text-level edit silently succeeds against the wrong occurrence". This failure mode is neither: it resolves the symbol and then writes something the author did not ask for. The guarantee that makes symbol editing preferable does not extend to the boundaries of the body it replaces.

## Rule

**Read the file back after every `replace_symbol_body` on a class or a large symbol.** Not the diff, the file. Then run the linter. For a whole-class rewrite, `Write` is the lower-risk tool — there is no boundary to get wrong.

This is the second known Serena defect in this project, after line endings ([[serena-writes-native-line-endings]]), and it has the same shape: the tool does its job and quietly damages the file on the way. Serena stays the default for navigation and for narrow, method-level edits; it is not trusted unverified for structural rewrites.

## Related

- [[serena-writes-native-line-endings]] — strip CRs and check `git ls-files --eol` after every Serena write
- [[qa-gates-cover-less-than-they-claim]]
