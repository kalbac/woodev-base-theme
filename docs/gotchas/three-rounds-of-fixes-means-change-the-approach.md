# Three rounds of narrowing defects means the approach is wrong, not the code

**Area:** Process · **Found:** s6 (M1-05), across two Codex re-critic passes

## The case

`Scheme::add_html_class()` appends the scheme class to the `language_attributes`
filter output. Appending blindly produces a second `class` attribute when anyone
else already added one — invalid HTML, and **browsers keep the first**, so ours
was the one silently dropped. The obvious fix is to merge into the existing
attribute. Three rounds followed, each version looking correct and being wrong
somewhere new:

| Round | What the critic found |
|---|---|
| 1 | An `is_embed()` guard fixed only core's oEmbed template; any plugin filtering the same hook still produced the duplicate |
| 2 | `\bclass=` also matched `data-class=` (a word boundary sits between the hyphen and the `c`), corrupting a plugin's attribute; `class=no-js`, `class = "x"` and `CLASS=` are all legal HTML and all missed; `str_replace()` rewrote every identical substring, including inside another attribute's value |
| 3 | `(^|\s)` cannot prove a match is outside quotes, so ` class=bar` inside `data-note="foo class=bar"` won; a newline in a quoted value fell through to the unquoted branch and matched empty, mangling the string; a bare `class` attribute still produced a duplicate |

Each round's bug was **narrower** than the last. That is the signal: the
implementation was converging, the *approach* was not. Parsing HTML attributes
with a regex loses; the only question is where.

## The resolution

Stop parsing. If the attribute string mentions a class at all, leave it
untouched:

```php
if ( false !== stripos( $output, 'class' ) ) {
    return $output;
}
```

`stripos` rather than a pattern **on purpose** — it over-matches, and
over-matching is the safe direction here.

What made that acceptable was noticing the trade is asymmetric. The server-side
class exists **only** for the visitor with JavaScript disabled; with JS the head
script sets it on `documentElement`, where there is a real DOM and nothing to
misparse. Skipping costs that visitor the class in a rare case. Getting the
merge wrong costs another plugin a corrupted attribute on every page.

## The rule

When a review round finds a defect narrower than the previous round's in the
same function, **stop fixing and re-ask what the function is for**. Look for the
requirement that lets you delete the hard part instead of perfecting it. Note
also that all three rounds were caught by re-reviewing *the fixes themselves* —
never self-certify a fix written in response to a review.

## Second occurrence, s7 — and the rule held

`AssetMarkup`, the integration-test helper answering "was this asset printed",
went through the same shape:

| Round | Finding |
|---|---|
| 1 | A lookahead regex accepted `data-type="module"` / `data-src="…"` (`\b` matches after a hyphen) and matched `<scripture` |
| 2 | Replaced by DOMDocument, but the URL check matched *any* element pointing into `assets/dist`, so deleting the main stylesheet stayed green on the imported-CSS loop |
| 3 | Anchored on the element id, but the "exact **or** substring" URL parameter had silently downgraded the dev-mode assertion from an exact URL to `str_contains` — `…/vega.css.bak` would have passed |

Each round was narrower than the last and each fix introduced the next defect.
The requirement deleted in round 3 was **the dual URL semantics**: one optional
parameter serving both "exact" and "contains" because production could not name
an exact URL. It could — the Vite manifest holds the hashed file name, and the
manifest is what that test is about. Reading it in the test made the URL always
exact, removed the mode flag, and made the assertion *stronger* than any of the
three previous versions (mutation-verified: a real `assets/dist` file from the
wrong pack now fails).

Worth noting the rounds were only visible because each fix was re-reviewed. A
single review pass would have shipped round 1.

## Related

- [[codex-split-diff-false-positives]] — the other half of using the critic well
- [[qa-gates-cover-less-than-they-claim]] — why "the tests pass" did not settle any of these rounds
