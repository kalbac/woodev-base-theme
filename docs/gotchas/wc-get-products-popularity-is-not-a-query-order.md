# `wc_get_products()` does not translate catalogue popularity

`WC_Query::get_catalog_ordering_args()` translates the visitor-facing
`popularity` catalogue choice into a lookup-table order for the main shop loop.
`wc_get_products()` does not call that path. Its CPT data store maps `include`
to `post__in`, then passes an `orderby` value through to `WP_Query`; the latter
does not recognise `popularity` and falls back to post date.

An isolated theme query that must use product sales needs an explicit order:

```php
'orderby'  => [
	'meta_value_num' => 'DESC',
	'ID'             => 'DESC',
],
'meta_key' => 'total_sales',
```

`total_sales` is WooCommerce's native product meta counter. Seed its values
only after any order fixtures, because WooCommerce order transitions may update
the same counter. Use distinct values in e2e fixtures and assert product IDs,
not creation order or a visual card position alone.

## Related

- [[../plans/2026-08-18-front-page-sections]] — the affected front-page query
- [[../CURRENT-STATE]] — current project status
- [[qa-gates-cover-less-than-they-claim]] — assertion precision
