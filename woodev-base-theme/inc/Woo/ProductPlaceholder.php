<?php
/**
 * Token-themed SVG placeholder for products without a photo.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

/**
 * Swaps WooCommerce's raster placeholder image for an inline SVG "plate"
 * themed with the identity's tokens, matching the treatment a real product
 * photo gets: docs/design/v2-mockup/woodev-base-identity.html §17
 * "Изображения" — `svg.plate` is the one swap point for real photography,
 * same markup and size as the real `<img>`. Real product photos need no
 * change at all; only the no-image fallback is replaced here.
 *
 * `<use>` clones into a shadow tree that document CSS selectors cannot reach
 * (docs/gotchas/svg-use-shadow-boundary-needs-custom-props.md), so the shapes
 * read their fills from CSS custom properties set on `.plate` in woo.css,
 * passed to the shapes as presentation attributes rather than styled by
 * class.
 */
final class ProductPlaceholder {

	/** Symbol id for the shared sprite definition. */
	private const SYMBOL_ID = 'wtb-plate-placeholder';

	/**
	 * Whether the placeholder was rendered at least once this request.
	 *
	 * @var bool
	 */
	private bool $used = false;

	/**
	 * Hook the placeholder swap and its sprite definition into WordPress.
	 */
	public function register(): void {
		add_filter( 'woocommerce_placeholder_img', [ $this, 'render' ] );
		add_action( 'wp_footer', [ $this, 'print_sprite' ] );
	}

	/**
	 * Replace WooCommerce's placeholder `<img>` with the themed SVG plate.
	 *
	 * `wc_placeholder_img()` filters its whole return value through
	 * `woocommerce_placeholder_img`, so this fully replaces the raster image
	 * rather than editing it — same swap point real product photography does
	 * not use, since a product with an uploaded image never calls this.
	 */
	public function render(): string {
		$this->used = true;

		return '<svg class="plate wtb-plate--placeholder" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><use href="#' . self::SYMBOL_ID . '"></use></svg>';
	}

	/**
	 * Print the shared sprite definition once, only if it was actually used.
	 */
	public function print_sprite(): void {
		if ( ! $this->used ) {
			return;
		}
		?>
		<svg aria-hidden="true" focusable="false" style="position:absolute;width:0;height:0;overflow:hidden">
			<symbol id="<?php echo esc_attr( self::SYMBOL_ID ); ?>" viewBox="0 0 400 400">
				<rect width="400" height="400" fill="var(--c-bg)" />
				<path d="M96 280 L176 176 L232 224 L288 128 L352 280 Z" fill="var(--c-obj)" opacity=".55" />
				<circle cx="152" cy="144" r="28" fill="var(--c-obj)" opacity=".55" />
			</symbol>
		</svg>
		<?php
	}
}
