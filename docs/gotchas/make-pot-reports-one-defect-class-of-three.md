# `wp i18n make-pot` warns about one i18n defect class of three — the other two delete the string silently

> Discovered s16 (27.07.2026) while auditing R3. Measured against WP-CLI's own `make-pot` inside the wp-env container, not reasoned about.

## The trap

A clean `make-pot` run reads as "the theme's strings are fine". It is not that. Run on this theme it reported **100 strings, zero warnings** — and a mutated copy proved that verdict covers exactly one of the three ways a translatable string goes wrong.

| Defect | What make-pot does |
|---|---|
| A placeholder with no `translators:` comment | **Warns**, with file and line. |
| A wrong text domain — `esc_html_e( 'Out of stock', 'wrong-domain' )` | **Silent.** The string is extracted into *somebody else's* domain and simply is not in your POT. |
| A non-literal first argument — `esc_html_e( $label, 'woodev-base-theme' )` | **Silent.** A static extractor cannot evaluate it, so it is skipped. |

The measurement: a copy of the theme carrying one wrong-domain call and one variable call generated **99 msgids instead of 101, and printed no warning at all**. Both defects present as an *absence*, and an absence is not something a generator can report. The string is gone from the POT, the translator never sees it, and the site ships untranslated text with a green tool run behind it.

```
$ wp i18n make-pot <theme> /tmp/out.pot
Theme stylesheet detected.
Success: POT file successfully generated.
```

That output is byte-identical whether the theme has 101 extractable strings or 99.

## What to do instead

- **Assert the two silent rules over the source**, since the generator will not: every i18n call names the theme's own text domain, and every translatable argument is a literal string. `tests/php/Unit/I18nSourceTest.php` does this at token level and fails the build.
- **A scanner that stops scanning is the same failure one level up.** That test is therefore proved against a deliberately broken fixture as well as against the theme — otherwise a regression that made it match nothing would certify the theme clean.
- Two shapes that scanner had to learn the hard way, both found by the critic rather than by writing it:
  - PHP 8 tokenises `\__()` as a **single `T_NAME_FULLY_QUALIFIED`** token carrying the separator, not `T_NS_SEPARATOR` + `T_STRING`. A scanner gated on `T_STRING` walks past every root-namespaced call — and calling a WordPress function that way from inside a namespace is normal.
  - `trim( $raw, "'\"" )` strips a character **set**, so it also eats a quote belonging to the *value*: the literal `'woodev-base-theme"'` names a different domain and came back matching ours. A `T_CONSTANT_ENCAPSED_STRING` is always properly delimited, so removing exactly one character from each end is exact — after accounting for a `b`/`B` binary prefix.

## Also measured, so nobody re-derives it

- `theme.json`'s `settings.color.palette[].name` and `settings.typography.fontFamilies[].name` **are** extracted, with `msgctxt "Color name"` / `"Font family name"` — 18 strings here, needing no code of ours.
- The `style.css` header contributes 5 (theme name, description, tags…).
- `Domain Path` is absent from `style.css` and does not need to be: `WP_Theme::load_textdomain()` falls back to `/languages`, which is exactly where `load_theme_textdomain()` points.

## Related

- [[qa-gates-cover-less-than-they-claim]] — the parent pattern: a gate's verdict is only as good as what it actually observed
- [[codex-cli-dies-silently]] — the other tool whose failure modes all exit 0
