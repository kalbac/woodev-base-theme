# Woo front-page loops need an explicit Woo scope

WooCommerce's storefront stylesheet is intentionally scoped under `.woocommerce`
to beat the plugin's own un-layered rules. A product loop rendered directly by a
theme's ordinary front-page template is not automatically inside that scope: the
front page has no Woo body class, even though `woocommerce_product_loop_start()`
and `content-product.php` emit valid Woo markup.

The result is a particularly misleading failure. The four product cards render,
but the Woo card rules do not match: the list stays `display: block`, product
images keep their intrinsic dimensions, and the page grows thousands of pixels
tall. Add the standard `woocommerce` class to the theme-owned section wrapper (or
write a deliberate top-level mirror for every needed rule). The shipped front
section uses `<section class="wtb-front-products woocommerce">` and keeps only its
four-column home override outside the archive scope.

The browser measurement that exposed this was a 1280px home page: the picks list
was `display: block` and 2,827px tall before the scope fix; afterwards it was a
four-track grid and the full page fell from 7,421px to 2,498px.

## Related

- [[../CURRENT-STATE]] — current project status
- [[../plans/2026-08-18-front-page-sections]] — front-page implementation plan
- [[woo-css-cannot-reach-block-cart-checkout]] — the related block-surface scope trap
