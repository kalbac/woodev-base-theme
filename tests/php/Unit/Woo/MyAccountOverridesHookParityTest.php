<?php
/**
 * A plain source-level guard against a My Account override quietly dropping a
 * hook it copied from core.
 *
 * Reads the shipped file's TEXT rather than executing it — the same approach
 * `DirectAccessGuardTest` uses to walk the theme — cheap, and it does not need
 * WooCommerce loaded to catch the one failure mode this project keeps
 * meeting: an override that looks right and silently breaks a third-party
 * integration hooked into a core action or filter the override forgot to
 * keep calling.
 *
 * @package Woodev\Theme\Base\Tests\Unit\Woo
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Woo;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

final class MyAccountOverridesHookParityTest extends PHPUnitTestCase {

	private const THEME_DIR = __DIR__ . '/../../../../woodev-base-theme';

	/**
	 * Every core `do_action()` / `apply_filters()` name each override must
	 * still name, taken from reading the installed WooCommerce 10.9.4 source
	 * (`templates/myaccount/{navigation,dashboard,view-order}.php`) — the same
	 * files docs/plans/2026-07-28-cart-checkout-account.md's Overrides table
	 * points at.
	 *
	 * @return array<string, array{0: string, 1: list<string>}>
	 */
	public static function overrides_and_their_core_hooks(): array {
		return [
			'navigation.php' => [
				'woocommerce/myaccount/navigation.php',
				[ 'woocommerce_before_account_navigation', 'woocommerce_after_account_navigation' ],
			],
			'dashboard.php'  => [
				'woocommerce/myaccount/dashboard.php',
				[ 'woocommerce_account_dashboard', 'woocommerce_before_my_account', 'woocommerce_after_my_account' ],
			],
			'view-order.php' => [
				'woocommerce/myaccount/view-order.php',
				[ 'woocommerce_view_order' ],
			],
		];
	}

	/**
	 * Core hooks an override DELIBERATELY stops calling, and why.
	 *
	 * The list above is the "must keep" contract; this one is its counterpart,
	 * and it exists so a drop is a recorded decision rather than an omission
	 * nobody notices. Asserting ABSENCE looks strange until you consider what it
	 * buys: restoring one of these needs an edit here, which is where the reason
	 * it was dropped is written down — so the decision gets re-made, not
	 * accidentally reversed.
	 *
	 * `woocommerce_order_details_status` is a filter over a SENTENCE
	 * (`view-order.php`'s "Order #N was placed on D and is currently S.") that
	 * this override does not render at all — the order head, the status badge
	 * and the meta grid carry the same facts. Keeping the call with its return
	 * value thrown away was tried and rejected: it means reproducing core's
	 * whole sentence purely as filter input, which puts a translatable string
	 * that NEVER renders into this theme's hand-maintained `.pot` (ADR-006).
	 *
	 * @return array<string, array{0: string, 1: list<string>}>
	 */
	public static function overrides_and_their_dropped_hooks(): array {
		return [
			'view-order.php' => [
				'woocommerce/myaccount/view-order.php',
				[ 'woocommerce_order_details_status' ],
			],
		];
	}

	/**
	 * The file's PHP tokens with every comment and docblock DROPPED.
	 *
	 * Every override's header docblock, as the task requires, already names
	 * the hooks it preserves in prose — which means a naive substring search
	 * over the raw file can never fail: the header mention alone satisfies it
	 * even if the real `do_action()`/`apply_filters()` call is deleted
	 * outright. Stripping comments first (the same technique
	 * `I18nSourceTest` tokenises with, and the reason `DirectAccessGuardTest`
	 * strips them before measuring a guard's position) is what makes this
	 * assertion capable of failing at all — confirmed by mutation: deleting
	 * `do_action( 'woocommerce_view_order', $order_id )` from view-order.php
	 * passed this test outright before this fix, because the header
	 * docblock's own mention of the same hook name was enough.
	 */
	private function executable_source( string $source ): string {
		$code = '';

		foreach ( token_get_all( $source ) as $token ) {
			if ( \is_array( $token ) && \in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT ], true ) ) {
				continue;
			}

			$code .= \is_array( $token ) ? $token[1] : $token;
		}

		return $code;
	}

	/**
	 * @param list<string> $hooks
	 *
	 * @dataProvider overrides_and_their_core_hooks
	 */
	public function test_the_override_still_names_every_core_hook( string $relative_path, array $hooks ): void {
		$source = (string) file_get_contents( self::THEME_DIR . '/' . $relative_path );

		// Guards this test rather than the theme: a typo'd path would make
		// every assertion below run against an empty string and pass for the
		// wrong reason. Same failure mode DirectAccessGuardTest guards for.
		self::assertNotSame( '', $source, "Could not read $relative_path — is the path right?" );

		$executable = $this->executable_source( $source );

		foreach ( $hooks as $hook ) {
			self::assertStringContainsString(
				$hook,
				$executable,
				"$relative_path no longer CALLS the core hook '$hook' (a docblock mention alone does not satisfy this)"
			);
		}
	}

	/**
	 * @param list<string> $hooks
	 *
	 * @dataProvider overrides_and_their_dropped_hooks
	 */
	public function test_the_override_still_omits_every_deliberately_dropped_hook( string $relative_path, array $hooks ): void {
		$source = (string) file_get_contents( self::THEME_DIR . '/' . $relative_path );

		self::assertNotSame( '', $source, "Could not read $relative_path — is the path right?" );

		$executable = $this->executable_source( $source );

		foreach ( $hooks as $hook ) {
			self::assertStringNotContainsString(
				$hook,
				$executable,
				"$relative_path calls '$hook' again. That hook was dropped on purpose — see " .
				'overrides_and_their_dropped_hooks() for the reason, and either move it back to the ' .
				'"must keep" provider with a note, or drop the call again.'
			);
		}
	}
}
