<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\CtaAttribute;

final class CtaAttributeTest extends TestCase {

	/**
	 * Put every Woo conditional tag in a known state, with exactly one of them
	 * true, so a test asserts about the context it names rather than about
	 * whichever tag happened to be stubbed truthy.
	 */
	private function on_context( string $context ): void {
		Functions\when( 'esc_attr' )->returnArg();

		foreach ( [ 'is_woocommerce', 'is_cart', 'is_checkout', 'is_account_page' ] as $tag ) {
			Functions\when( $tag )->justReturn( $tag === $context );
		}
	}

	public function test_register_hooks_language_attributes(): void {
		$attribute = new CtaAttribute();
		$attribute->register();

		self::assertNotFalse( \has_filter( 'language_attributes', [ $attribute, 'add_attribute' ] ) );
	}

	public function test_appends_the_default_reveal_mode_on_the_shop(): void {
		$this->on_context( 'is_woocommerce' );
		Functions\when( 'get_theme_mod' )->alias( static fn( $name, $default = false ) => $default );

		$result = ( new CtaAttribute() )->add_attribute( 'lang="en-US"' );

		self::assertSame( 'lang="en-US" data-cta="hover"', $result );
	}

	public function test_appends_the_attribute_on_the_cart_alone(): void {
		$this->on_context( 'is_cart' );
		Functions\when( 'get_theme_mod' )->alias( static fn( $name, $default = false ) => $default );

		$result = ( new CtaAttribute() )->add_attribute( 'lang="en-US"' );

		self::assertSame( 'lang="en-US" data-cta="hover"', $result );
	}

	/**
	 * The whole point of the attribute: it must FOLLOW the Customizer setting.
	 * An earlier version emitted a hard-coded literal, which no assertion here
	 * could tell apart from a correctly-wired default — so this test is the one
	 * that proves the wiring exists at all.
	 */
	public function test_the_attribute_follows_the_customizer_setting(): void {
		$this->on_context( 'is_woocommerce' );
		Functions\when( 'get_theme_mod' )->justReturn( 'always' );

		$result = ( new CtaAttribute() )->add_attribute( 'lang="en-US"' );

		self::assertSame( 'lang="en-US" data-cta="always"', $result );
	}

	/**
	 * The value reaches markup, so it goes through the same resolver that
	 * sanitises the setting — a tampered theme_mod falls back rather than being
	 * printed. `esc_attr` is stubbed to return its argument here precisely so a
	 * failure of this guard would be visible instead of being escaped away.
	 */
	public function test_a_tampered_theme_mod_falls_back_to_the_default(): void {
		$this->on_context( 'is_woocommerce' );
		Functions\when( 'get_theme_mod' )->justReturn( '" onload="alert(1)' );

		$result = ( new CtaAttribute() )->add_attribute( 'lang="en-US"' );

		self::assertSame( 'lang="en-US" data-cta="hover"', $result );
	}

	public function test_leaves_output_untouched_off_every_woo_context(): void {
		$this->on_context( 'none' );

		$result = ( new CtaAttribute() )->add_attribute( 'lang="en-US"' );

		self::assertSame( 'lang="en-US"', $result );
	}
}
