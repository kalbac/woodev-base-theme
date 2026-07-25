<?php
/**
 * Emits the add-to-cart reveal behaviour switch as a `data-cta` attribute.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

use Woodev\Theme\Base\Customizer\Settings;

/**
 * Exposes the product-card actions reveal mode to CSS via a `data-cta`
 * attribute, so `src/css/woo.css` can select `[data-cta="always"]` to switch
 * the hover-reveal add-to-cart to a static, always-visible button.
 *
 * The plan (docs/plans/2026-07-25-visual-identity.md) called for this switch to
 * live on `<body>`. It lands on `<html>` instead: `body_class()` can only add
 * CLASSES, never new attributes, and the `language_attributes` filter already
 * renders into header.php's `<html>` tag without that file having to change.
 * `[data-cta="value"] descendant` selectors match identically regardless of
 * which ancestor carries the attribute, so woo.css neither knows nor cares.
 *
 * Note what this attribute does NOT decide: a touch device cannot fire :hover
 * at all, so woo.css forces the static treatment under `@media (hover: none)`
 * regardless of the value here. This setting is the admin's preference for
 * pointer devices, not a switch that can hide the button from a phone.
 *
 * Guarded on `is_admin()` AND `is_login()` (see `add_attribute()`) rather than
 * emitted on every request that reaches `language_attributes()`:
 * `language_attributes()` also runs on `/wp-login.php`, where `is_admin()` is
 * FALSE (that check only detects `/wp-admin/`), so without the extra guard a
 * WooCommerce-only attribute would land on the login screen for no reader.
 * `is_login()` (WP 6.1+, this theme's floor is 6.8) closes that gap. Not
 * every non-Woo document is excluded, though — see `add_attribute()` for the
 * `wp_die()` case that still gets it.
 */
final class CtaAttribute {

	/**
	 * Hook the attribute injection into WordPress.
	 */
	public function register(): void {
		add_filter( 'language_attributes', [ $this, 'add_attribute' ] );
	}

	/**
	 * Append the `data-cta` attribute outside wp-admin and the login screen.
	 *
	 * Two guards: `is_admin()` — `language_attributes()` is also called by
	 * `_wp_admin_html_begin()` in wp-admin, where the attribute is
	 * meaningless — and `is_login()`, because `is_admin()` alone does NOT
	 * cover `/wp-login.php`: that screen calls `language_attributes()` via
	 * `login_header()` but is not inside `/wp-admin/`, so `is_admin()`
	 * returns false there. Without `is_login()` a WooCommerce-only attribute
	 * would print on the login page and read a theme_mod for a document
	 * that never has a product loop on it.
	 *
	 * This is not exhaustive: `wp_die()`-generated HTML still calls
	 * `language_attributes()` (via `_default_wp_die_handler()`'s header) and
	 * will still receive the attribute. That is left as-is deliberately —
	 * it is harmless (an inert `data-cta` on a document with no
	 * `.card-actions` selector to match it does nothing) and `wp_die()` can
	 * fire from front-end request paths that legitimately want the
	 * attribute up to the point of failure, so excluding it would need a
	 * broader, riskier check than this class exists to make.
	 *
	 * There is deliberately no Woo-context check beyond the two guards above
	 * — see inc/Woo/Assets.php's docblock for why a Woo product loop
	 * (shortcode or block) can render on any page, so `[data-cta]` needs to
	 * be present wherever `.card-actions` might be, not only on
	 * `is_woocommerce()`/`is_cart()`/`is_checkout()`/`is_account_page()`
	 * pages.
	 *
	 * The value comes from the Customizer through the same resolver that
	 * sanitises the setting, so a tampered theme_mod cannot reach the markup.
	 *
	 * @param string $output The filtered `language_attributes()` output.
	 */
	public function add_attribute( string $output ): string {
		if ( is_admin() || is_login() ) {
			return $output;
		}

		return $output . ' data-cta="' . esc_attr( Settings::cta_reveal() ) . '"';
	}
}
