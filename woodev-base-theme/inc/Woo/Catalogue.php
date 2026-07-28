<?php
/**
 * Catalogue-side WooCommerce presentation hooks.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Icons;
use WC_Product;
use WP_Term;

/**
 * The catalogue nodes the approved mockup asks for that WooCommerce's default
 * markup does not supply — implemented as filters and one action, with no
 * template override.
 *
 * Scope, per docs/plans/2026-07-28-catalogue-and-product.md section A: the
 * breadcrumb separator (A1), the subcategory chip row (A3), the sale badge's
 * percentage (A11) and the pagination arrows (A13). The filter rail (A5–A9)
 * and the loop card's own markup are other classes; this one deliberately owns
 * nothing that needs a `woocommerce/` template file.
 *
 * Every callback takes `mixed` and returns the value untouched unless it is
 * exactly the shape it expected. All four hooks are third-party filterable, so
 * a plugin downstream of core can hand any of them a `null`, an array or an
 * object; this file runs under `strict_types=1`, where a typed parameter would
 * turn that into a TypeError on a front-end request. Failing closed is the
 * house rule after the s5 `(string) get_theme_mod()` fatal.
 */
final class Catalogue {

	/**
	 * Most subcategory chips to print in the archive header.
	 *
	 * The mockup draws four. A shop with sixty categories would otherwise turn
	 * the header into a wall of chips, and the row has no "more" affordance to
	 * fall back on; the category widget in the filter rail is the complete
	 * list, this row is an at-a-glance sample of it.
	 */
	private const SUBCATEGORY_LIMIT = 12;

	/**
	 * Hook the catalogue presentation filters into WordPress.
	 */
	public function register(): void {
		add_filter( 'woocommerce_breadcrumb_defaults', [ $this, 'breadcrumb_defaults' ] );
		add_filter( 'woocommerce_sale_flash', [ $this, 'sale_flash' ], 10, 3 );
		add_filter( 'woocommerce_pagination_args', [ $this, 'pagination_args' ] );
		add_action( 'woocommerce_archive_description', [ $this, 'render_subcategories' ], 20 );
	}

	/**
	 * Wrap the breadcrumb delimiter in an element the stylesheet can reach.
	 *
	 * Woo's default delimiter is the bare text `&nbsp;/&nbsp;`, and the mockup
	 * gives the separator its own quieter colour (`.breadcrumb .sep`, mockup
	 * CSS line 419) — a text node cannot carry that. Only `delimiter` is
	 * replaced: `wrap_before` already carries `class="woocommerce-breadcrumb"`
	 * and a translated `aria-label`, both of which the stylesheet and screen
	 * readers are happy with, and rewriting it here would re-declare that label
	 * in THIS theme's text domain — an English string on every site until
	 * someone translates it, replacing one WooCommerce already ships in every
	 * locale.
	 *
	 * `aria-hidden` on the separator because it is punctuation between the
	 * links, not content: a screen reader announcing "slash" between every
	 * crumb is noise.
	 *
	 * @param mixed $defaults The breadcrumb argument array being filtered.
	 */
	public function breadcrumb_defaults( mixed $defaults ): mixed {
		if ( ! \is_array( $defaults ) ) {
			return $defaults;
		}

		$defaults['delimiter'] = '<span class="wtb-breadcrumb__sep" aria-hidden="true">/</span>';

		return $defaults;
	}

	/**
	 * Replace WooCommerce's "Sale!" flash with the discount as a percentage.
	 *
	 * The mockup's badge reads `−24%`, not a word (mockup line 1985). When the
	 * percentage cannot be established the ORIGINAL markup is returned
	 * unchanged rather than a guess: that covers variable products (whose
	 * `get_regular_price()` is empty because the range has no single regular
	 * price), products on sale through a third-party rule rather than a sale
	 * price, and the rounding case below.
	 *
	 * @param mixed $html    Sale flash markup Woo is about to print.
	 * @param mixed $post    The product's post object (unused; part of the hook signature).
	 * @param mixed $product The product being displayed.
	 */
	public function sale_flash( mixed $html, mixed $post, mixed $product ): mixed {
		if ( ! \is_string( $html ) || ! $product instanceof WC_Product ) {
			return $html;
		}

		$percent = $this->discount_percent( $product );

		if ( null === $percent ) {
			return $html;
		}

		return sprintf(
			'<span class="onsale wtb-sale-flash">%s</span>',
			esc_html(
				sprintf(
					/* translators: %s: the discount as a whole number of percent, e.g. "24". The leading character is a minus sign, not a hyphen. */
					__( '−%s%%', 'woodev-base-theme' ),
					number_format_i18n( $percent )
				)
			)
		);
	}

	/**
	 * Swap the pagination's textual arrows for the theme's chevron icons.
	 *
	 * Woo's defaults are the bare entities `&larr;` / `&rarr;`, already
	 * direction-swapped for RTL (`templates/loop/pagination.php`); this mirrors
	 * that swap rather than assuming LTR. The visible glyph becomes an icon, so
	 * each link keeps a screen-reader-only name — an anchor whose only content
	 * is an `aria-hidden` SVG has no accessible name at all.
	 *
	 * @param mixed $args The `paginate_links()` argument array being filtered.
	 */
	public function pagination_args( mixed $args ): mixed {
		if ( ! \is_array( $args ) ) {
			return $args;
		}

		$previous = $this->pagination_arrow( 'chevron-left', __( 'Previous page', 'woodev-base-theme' ) );
		$next     = $this->pagination_arrow( 'chevron-right', __( 'Next page', 'woodev-base-theme' ) );

		$args['prev_text'] = is_rtl() ? $next : $previous;
		$args['next_text'] = is_rtl() ? $previous : $next;

		return $args;
	}

	/**
	 * Print the archive header's subcategory chip row.
	 *
	 * Runs on `woocommerce_archive_description` at priority 20, i.e. inside
	 * `<header class="woocommerce-products-header">` and after the archive
	 * description core prints at 10 — the position the mockup draws it in
	 * (mockup line 1916). On a category archive the chips are that category's
	 * children; on the shop page they are the top-level product categories.
	 * Anywhere else — a tag archive, an attribute archive — nothing prints,
	 * because "children of the thing you are looking at" has no useful meaning
	 * there.
	 */
	public function render_subcategories(): void {
		$terms = $this->subcategory_terms();

		if ( [] === $terms ) {
			return;
		}

		echo '<ul class="wtb-subcats">';

		foreach ( $terms as $term ) {
			$link = get_term_link( $term );

			// get_term_link() returns WP_Error for a term whose taxonomy is not
			// registered. Casting that to string is a fatal on PHP 8 — the exact
			// defect the Codex critic caught on the front page in s16.
			if ( is_wp_error( $link ) ) {
				continue;
			}

			// `tag` is the theme's own chip component (src/css/adapter/feedback.css,
			// ported from the mockup in T5 and until now unused by any template) —
			// this row is its first consumer, so the chips reuse it rather than
			// re-declaring the same six properties in the storefront bundle.
			// `wtb-subcat` alongside it is the hook for tests and for any rule
			// that must apply HERE and not to every chip in the theme.
			printf(
				'<li><a class="wtb-subcat tag" href="%1$s">%2$s</a></li>',
				esc_url( $link ),
				esc_html( $term->name )
			);
		}//end foreach

		echo '</ul>';
	}

	/**
	 * The product categories whose chips belong in the current archive header.
	 *
	 * @return array<int, WP_Term>
	 */
	private function subcategory_terms(): array {
		if ( is_product_category() ) {
			$current = get_queried_object();

			if ( ! $current instanceof WP_Term ) {
				return [];
			}

			$parent = $current->term_id;
		} elseif ( is_shop() ) {
			$parent = 0;
		} else {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'parent'     => $parent,
				'hide_empty' => true,
				'number'     => self::SUBCATEGORY_LIMIT,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		return array_values( array_filter( $terms, static fn ( mixed $term ): bool => $term instanceof WP_Term ) );
	}

	/**
	 * One pagination arrow: an icon plus a screen-reader-only name.
	 *
	 * Deliberately identical in shape to the post pagination the theme already
	 * ships (`template-parts/content/pagination.php`) — same icons, same
	 * `sr-only` span, same default icon size — so the two paginations are one
	 * component with two call sites rather than two that merely resemble each
	 * other.
	 *
	 * @param string $icon  Lucide icon slug.
	 * @param string $label Already-translated accessible name.
	 */
	private function pagination_arrow( string $icon, string $label ): string {
		return Icons::get( $icon ) . '<span class="sr-only">' . esc_html( $label ) . '</span>';
	}

	/**
	 * The whole-number discount on a product, or null when there is not one.
	 *
	 * Null rather than zero on every failure path, so `sale_flash()` can tell
	 * "no percentage to show" from "zero percent off" without a sentinel.
	 *
	 * "Has a price at all" is decided on the RAW strings, not on the floats.
	 * WooCommerce returns `''` for a price that is unset and `'0'` for one that
	 * is genuinely zero, and both cast to `0.0` — so a `$sale <= 0.0` guard
	 * (which is what this method had until the s18 critic pass) silently
	 * rejected the legitimate case of a product marked down to free. Regular
	 * 100, sale 0 is `is_on_sale()`, is exactly −100%, and used to fall back to
	 * WooCommerce's word instead.
	 *
	 * @param WC_Product $product The product whose sale is being measured.
	 */
	private function discount_percent( WC_Product $product ): ?int {
		if ( ! $product->is_on_sale() ) {
			return null;
		}

		$regular_price = $product->get_regular_price();
		$sale_price    = $product->get_sale_price();

		// Variable products report '' for both — there is no single regular
		// price across the range, so there is no single percentage either.
		if ( '' === $regular_price || '' === $sale_price ) {
			return null;
		}

		$regular = (float) $regular_price;
		$sale    = (float) $sale_price;

		if ( $regular <= 0.0 || $sale < 0.0 || $sale >= $regular ) {
			return null;
		}

		$percent = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );

		// A saving under half a percent rounds to zero, and "−0%" reads as a
		// bug rather than a bargain. Fall back to Woo's own wording.
		return $percent > 0 ? $percent : null;
	}
}
