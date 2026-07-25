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
 */
final class CtaAttribute {

	/**
	 * Hook the attribute injection into WordPress.
	 */
	public function register(): void {
		add_filter( 'language_attributes', [ $this, 'add_attribute' ] );
	}

	/**
	 * Append the `data-cta` attribute, on WooCommerce contexts only.
	 *
	 * The value comes from the Customizer through the same resolver that
	 * sanitises the setting, so a tampered theme_mod cannot reach the markup.
	 *
	 * @param string $output The filtered `language_attributes()` output.
	 */
	public function add_attribute( string $output ): string {
		if ( ! ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
			return $output;
		}

		return $output . ' data-cta="' . esc_attr( Settings::cta_reveal() ) . '"';
	}
}
