<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\ProductPlaceholder;

final class ProductPlaceholderTest extends TestCase {

	public function test_register_hooks_the_placeholder_filter_and_the_footer_print(): void {
		$placeholder = new ProductPlaceholder();
		$placeholder->register();

		self::assertNotFalse( \has_filter( 'woocommerce_placeholder_img', [ $placeholder, 'render' ] ) );
		self::assertNotFalse( \has_action( 'wp_footer', [ $placeholder, 'print_sprite' ] ) );
	}

	public function test_render_returns_a_use_reference_to_the_shared_symbol(): void {
		$html = ( new ProductPlaceholder() )->render();

		self::assertStringContainsString( 'class="plate wtb-plate--placeholder"', $html );
		self::assertStringContainsString( '<use href="#wtb-plate-placeholder">', $html );
		// aria-hidden: this is a decorative fallback, not photography — the
		// product title elsewhere in the card carries the accessible name.
		self::assertStringContainsString( 'aria-hidden="true"', $html );
	}

	public function test_print_sprite_emits_nothing_when_the_placeholder_was_never_used(): void {
		ob_start();
		( new ProductPlaceholder() )->print_sprite();
		$output = ob_get_clean();

		self::assertSame( '', $output );
	}

	public function test_print_sprite_emits_the_symbol_once_rendered_was_called(): void {
		Functions\when( 'esc_attr' )->returnArg();

		$placeholder = new ProductPlaceholder();
		$placeholder->render();

		ob_start();
		$placeholder->print_sprite();
		$output = ob_get_clean();

		self::assertStringContainsString( '<symbol id="wtb-plate-placeholder"', $output );
	}
}
