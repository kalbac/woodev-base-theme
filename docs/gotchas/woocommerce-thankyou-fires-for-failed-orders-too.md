# `woocommerce_thankyou` fires for a FAILED order too — it is not the success hook

> Found s19 by the Codex critic, which pointed at the wrong half of it; reading
> `templates/checkout/thankyou.php` settled the rest. A real, user-visible defect was one guard away
> from shipping.

## The trap

The order-received template branches on payment status, and the two hooks a theme reaches it through
sit on **different sides of that branch**. From WooCommerce 10.9.4's
`templates/checkout/thankyou.php`:

```php
if ( $order ) :
    do_action( 'woocommerce_before_thankyou', $order->get_id() );   // line 28

    if ( $order->has_status( 'failed' ) ) :                          // line 31
        …payment declined notice, "pay again" button…
    else :                                                           // line 42
        …order-received.php, the order-overview <ul>…
    endif;                                                           // line 79

    do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
    do_action( 'woocommerce_thankyou', $order->get_id() );            // line 82
else :
    …order-received.php with $order = false…
endif;
```

Three facts, none of them the intuitive one:

1. **`woocommerce_before_thankyou` fires BEFORE the status branch.** Anything it prints appears above
   the declined-payment notice. An unguarded success hero therefore renders "Thank you, your order is
   in" with a check mark directly on top of "your payment was declined".
2. **`woocommerce_thankyou` fires AFTER the `endif`, outside both branches** — so it runs on a failed
   order as well. The name says thank-you; the placement says "any order that resolved". This is the
   one that is easy to get wrong, because guarding the *first* hook feels like it covers the page.
3. **Neither fires when the id resolves to no order** (a bad id, an expired key). That branch prints
   only core's bare paragraph, which is the correct degraded state and needs nothing from a theme.

## What it cost

`Woo\Receipt::hero()` was guarded on `has_status( 'failed' )` from the start. `Woo\Receipt::actions()`
— the "Track order" / "Back to shop" cluster on `woocommerce_thankyou` — was not, because
`woocommerce_thankyou` reads like the success path. A failed payment would have rendered:

> your payment was declined, please try again
> **[ Track order ]  [ Back to shop ]**

i.e. the theme telling the buyer to walk away from a payment they still owe, next to core's own
correct "pay again" control.

## What to do

- **Guard every `woocommerce_thankyou` callback on the order status yourself.** The hook is not a
  success signal.
- Guard `woocommerce_before_thankyou` too, separately — one guard does not cover the other, they are
  different code paths and a reviewer will assume otherwise.
- Type the `$order_id` parameter `mixed` and resolve it through `wc_get_order()` with an
  `instanceof WC_Order` check: both are `do_action()`s, so a third party can re-fire them with
  anything, and a `WC_Order_Refund` is a sibling class rather than a subtype.
- Unit-test the failed branch with the collaborators declared `->never()`. That turns "the guard
  returned early" into an assertion rather than an absence:

```php
$order->shouldReceive( 'has_status' )->with( 'failed' )->andReturn( true );
$order->shouldReceive( 'get_customer_id' )->never();
$order->shouldReceive( 'get_view_order_url' )->never();
```

## Related

- [[qa-gates-cover-less-than-they-claim]] — the failed-order path had no test at all, which is why nothing said so
- [[commerce-pages-inherit-the-prose-reading-measure]] — the other s19 defect on the same screen
