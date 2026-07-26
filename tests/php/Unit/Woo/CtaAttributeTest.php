<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Tests\Unit\TestCase;
use Woodev\Theme\Base\Woo\CtaAttribute;

final class CtaAttributeTest extends TestCase {

	public function test_register_hooks_language_attributes(): void {
		$attribute = new CtaAttribute();
		$attribute->register();

		self::assertNotFalse( \has_filter( 'language_attributes', [ $attribute, 'add_attribute' ] ) );
	}

	/**
	 * The Woo-context conditional (`is_woocommerce()`/`is_cart()`/
	 * `is_checkout()`/`is_account_page()`) is gone: a Woo product loop
	 * (shortcode or block) can render on any front-end page, so `[data-cta]`
	 * has to be there too — see inc/Woo/Assets.php's docblock for the same
	 * reasoning applied to the stylesheet. This test proves the attribute is
	 * appended on a page carrying none of those Woo context tags.
	 */
	public function test_appends_the_default_reveal_mode_on_a_non_woo_front_end_page(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_login' )->justReturn( false );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'get_theme_mod' )->alias( static fn( $name, $default = false ) => $default );

		$result = ( new CtaAttribute() )->add_attribute( 'lang="en-US"' );

		self::assertSame( 'lang="en-US" data-cta="hover"', $result );
	}

	public function test_not_appended_when_is_admin_is_true(): void {
		Functions\when( 'is_admin' )->justReturn( true );

		$result = ( new CtaAttribute() )->add_attribute( 'lang="en-US"' );

		self::assertSame( 'lang="en-US"', $result );
	}

	/**
	 * `language_attributes()` also runs on `/wp-login.php` via
	 * `login_header()`, where `is_admin()` is FALSE — that check only
	 * detects `/wp-admin/`, not the login screen. Without the `is_login()`
	 * guard a WooCommerce-only attribute would print there for a document
	 * that never carries a product loop. This is the test that would have
	 * caught the original defect: `is_admin()` alone lets this case through.
	 */
	public function test_not_appended_on_the_login_screen(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_login' )->justReturn( true );

		$result = ( new CtaAttribute() )->add_attribute( 'lang="en-US"' );

		self::assertSame( 'lang="en-US"', $result );
	}

	/**
	 * The whole point of the attribute: it must FOLLOW the Customizer setting.
	 * An earlier version emitted a hard-coded literal, which no assertion here
	 * could tell apart from a correctly-wired default — so this test is the one
	 * that proves the wiring exists at all.
	 */
	public function test_the_attribute_follows_the_customizer_setting(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_login' )->justReturn( false );
		Functions\when( 'esc_attr' )->returnArg();
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
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_login' )->justReturn( false );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'get_theme_mod' )->justReturn( '" onload="alert(1)' );

		$result = ( new CtaAttribute() )->add_attribute( 'lang="en-US"' );

		self::assertSame( 'lang="en-US" data-cta="hover"', $result );
	}
}
