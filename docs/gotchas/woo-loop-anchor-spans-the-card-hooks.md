# The WooCommerce shop-loop anchor spans the card hooks — a header/footer crossing it is invalid HTML

**Area:** WooCommerce / templates
**First hit:** s9 (M2a Task 5, the `content-product.php` override)

## What happens

WooCommerce's `content-product.php` loop item is hook-driven, and the default
callbacks open and close a single `<a>` **across** the hooks you want to wrap:

- `woocommerce_before_shop_loop_item` @10 → `woocommerce_template_loop_product_link_open` **opens** `<a class="woocommerce-loop-product__link">`
- `woocommerce_before_shop_loop_item_title` @10 → sale flash `<span class="onsale">`, thumbnail `<img>`
- `woocommerce_shop_loop_item_title` @10 → title
- `woocommerce_after_shop_loop_item_title` @5 rating, @10 price
- `woocommerce_after_shop_loop_item` @5 → `woocommerce_template_loop_product_link_close` **closes** `</a>`; @10 → `woocommerce_template_loop_add_to_cart` (the button, a sibling **after** the anchor)

So the anchor spans sale-flash + thumbnail + title + rating + price; the
add-to-cart button is emitted after it closes.

## The trap

Wrapping the loop item into a `.card` with `<header>`/`<footer>` (the obvious
Basecoat card shape) makes a wrapper element **cross the anchor boundary**: a
`<footer>` around the `after_shop_loop_item` call contains the `</a>` that closes
an anchor opened *before* the footer — `<a> … <footer> </a> … </footer>` —
overlapping, invalid HTML. Browsers auto-correct it, but it ships broken markup
and unpredictable styling.

## The fix

Any wrapper you add must sit **entirely inside** the anchor span
`[before_shop_loop_item … after_shop_loop_item@5]` **or entirely outside** it:

```php
<li <?php wc_product_class( 'wtb-product-card card', $product ); ?>>
	<?php do_action( 'woocommerce_before_shop_loop_item' ); // <a> opens
	do_action( 'woocommerce_before_shop_loop_item_title' ); // flash + thumbnail ?>
	<div class="wtb-product-card__body"><?php // fully INSIDE the anchor
	do_action( 'woocommerce_shop_loop_item_title' );
	do_action( 'woocommerce_after_shop_loop_item_title' ); ?></div>
	<?php do_action( 'woocommerce_after_shop_loop_item' ); // </a> @5, then button @10 ?>
</li>
```

The body `<div>` is nested inside the still-open anchor; the button lands as a
bare `<li>` child after `</a>`. No element crosses. The card look is then a CSS
job on `.wtb-product-card` / the anchor / the button — not a markup job.

The plan predicted this and said so: **do not fight it with a regex/string
rewrite**, and do not re-prioritise the link hooks to "make the whole card the
link" (add-to-cart is interactive content and cannot live inside an `<a>`).

## Related

- [[three-rounds-of-fixes-means-change-the-approach]] — same "stop fighting the shape" lesson
- `woodev-base-theme/woocommerce/content-product.php` — the override
- `docs/plans/2026-07-23-m2a-woo-storefront.md` — Task 5, verified hook contracts
