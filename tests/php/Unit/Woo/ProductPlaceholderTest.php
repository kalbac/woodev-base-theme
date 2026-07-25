<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\ProductPlaceholder;

final class ProductPlaceholderTest extends TestCase {

	/**
	 * `register()` used to also hook a `wp_footer` sprite-print action
	 * (`print_sprite`). That machinery — and the method itself — is gone:
	 * `ProductImage.php` (WooCommerce Blocks) can call `wc_placeholder_img()`
	 * while server-rendering a REST response, a document that never fires
	 * `wp_footer` at all, so a shared `<symbol>` sprite could never be
	 * guaranteed to reach it. `render()` is now fully self-contained, so
	 * nothing needs to hook `wp_footer` any more. Asserting `has_action()`
	 * is false for ANY callback proves no sprite hook survived, not just
	 * that a specific one didn't.
	 */
	public function test_register_hooks_only_the_placeholder_filter_no_sprite_action(): void {
		$placeholder = new ProductPlaceholder();
		$placeholder->register();

		self::assertNotFalse( \has_filter( 'woocommerce_placeholder_img', [ $placeholder, 'render' ] ) );
		self::assertFalse( \has_action( 'wp_footer' ) );
	}

	/**
	 * The placeholder must be resolvable wherever it lands, including a
	 * document that never printed a shared sprite (REST responses, block
	 * editor previews — see the class docblock). That is only true if the
	 * shapes are inlined directly rather than referenced through
	 * `<use href="#…">` against a `<symbol>` defined elsewhere.
	 */
	public function test_render_returns_a_self_contained_svg_with_no_use_reference(): void {
		$html = ( new ProductPlaceholder() )->render();

		self::assertStringContainsString( 'class="plate wtb-plate--placeholder"', $html );
		self::assertStringNotContainsString( '<use href="#', $html );
		self::assertStringContainsString( '<rect width="400" height="400" fill="var(--c-bg)" />', $html );
		self::assertStringContainsString( 'fill="var(--c-obj)"', $html );
		// aria-hidden: this is a decorative fallback, not photography — the
		// product title elsewhere in the card carries the accessible name.
		self::assertStringContainsString( 'aria-hidden="true"', $html );
	}
}
