# PHPCS misses unescaped output when the string passes through a variable

**Area:** Tooling/QA · **Found:** s7, component-tail Codex review

## The trap

`WordPress.Security.EscapeOutput` flags `echo __( 'x' )` — a translation function
whose result is echoed directly. It does **not** flag a translation that is
assigned to a variable, and the variable is later printed by something the sniff
cannot follow. `comment_form()`'s field args are exactly that shape:

```php
$label = sprintf( '<label>%s</label>', __( 'Name', 'woodev-base-theme' ) ); // phpcs: silent
comment_form( [ 'fields' => [ 'author' => $label ] ] );                       // core echoes $label
```

`composer phpcs` was green. The string still reached the page unescaped: a
tampered or malicious translation carrying markup would render. The sniff is
syntactic — it sees `__()` feeding a variable, not `__()` feeding output — so a
clean phpcs run is **no proof** a translated string was escaped when it travels
through a variable, an array, or a callback before printing.

## Where it bites in this codebase

Any WordPress API that takes markup as an argument and echoes it for you:
`comment_form()` (`comment_field`, `fields`, `class_*`), `wp_list_comments()`
callbacks, `the_password_form` and similar output filters, widget `before/after`
args. The value looks like input to the function, but from the page's point of
view it is output, and output-escaping is ours.

## The rule

- Translations destined for HTML output go through `esc_html__()` /
  `esc_html_x()` (or `esc_attr__()` for attribute context), **at the point the
  string is built**, not only where phpcs happens to look.
- Leave a value raw only when it is *known* safe HTML and escaping would corrupt
  it — e.g. `wp_required_field_indicator()` returns a `<span>` and must stay
  unescaped. Say so in a comment so the next reader does not "fix" it.
- **A green `WordPress.Security.EscapeOutput` run does not mean output is
  escaped.** It means the sniff found nothing it can trace. Trace the data-flow
  yourself for anything handed to a WP function that echoes.

Related to [[qa-gates-cover-less-than-they-claim]] — a gate proving less than its
name suggests, the recurring theme of this project's QA.

## Related

- [[qa-gates-cover-less-than-they-claim]] — the general form of "the gate passed" ≠ "the code is right"
