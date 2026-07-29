<?php
/**
 * My Account presentation: the status-tone mapping (M6) and the nav icon
 * lookup (M2) both live here rather than in the override templates that
 * consume them, so the three templates in woocommerce/myaccount/ (navigation,
 * dashboard, view-order) share one source of truth instead of three copies.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Woo;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Icons;
use WC_Order;

/**
 * `render_order_status_column()` narrows on `instanceof WC_Order`, a class the
 * unit suite has no source for — tests/php/Support/wc-order-double.php
 * supplies it and the unit bootstrap loads it, so it is available to every
 * test regardless of discovery order.
 */
final class Account {

	/**
	 * Every core WooCommerce order status (the seven `wc_get_order_statuses()`
	 * returns, unprefixed — `get_status()` never carries the `wc-` prefix),
	 * mapped onto one of the mockup's three badge tones.
	 *
	 * The mockup only draws three tones — `success` (completed), `accent`
	 * (processing) and `neutral` (pending) — so every status not explicitly
	 * one of those two positive states falls to `neutral`: it is the one tone
	 * in the palette that is not a claim of success or of active progress,
	 * which is what `failed` and `cancelled` most need to avoid (a fourth,
	 * "destructive" tone was considered and rejected — the mockup does not
	 * draw one, and inventing a colour the design never specified is a worse
	 * default than reusing the muted one it does).
	 *
	 *   - `on-hold`  → neutral: not yet moving, the same bucket as `pending`.
	 *   - `cancelled`, `refunded`, `failed` → neutral: terminal and NOT a
	 *     success, which is the one hard requirement; neutral satisfies it
	 *     without borrowing a tone the palette does not have.
	 *
	 * @var array<string, string>
	 */
	private const STATUS_TONES = [
		'pending'    => 'neutral',
		'on-hold'    => 'neutral',
		'processing' => 'accent',
		'completed'  => 'success',
		'cancelled'  => 'neutral',
		'refunded'   => 'neutral',
		'failed'     => 'neutral',
	];

	/**
	 * Tone for a status this map has never heard of — a custom status a
	 * plugin registers. Neutral for the same reason as above: an unknown
	 * status is not a known success, so it must not read as one.
	 */
	private const DEFAULT_TONE = 'neutral';

	/**
	 * Lucide icon slug per My Account navigation endpoint (M2), keyed by the
	 * endpoint id `wc_get_account_menu_items()` uses. An endpoint with no
	 * entry here — a plugin-added tab — renders no icon; see
	 * `woocommerce/myaccount/navigation.php`.
	 *
	 * @var array<string, string>
	 */
	private const NAV_ICONS = [
		'dashboard'       => 'house',
		'orders'          => 'file-text',
		'downloads'       => 'download',
		'edit-address'    => 'map-pin',
		'payment-methods' => 'credit-card',
		'edit-account'    => 'user',
		'customer-logout' => 'log-out',
	];

	/**
	 * Hook the My Account presentation filters into WordPress.
	 */
	public function register(): void {
		add_action( 'woocommerce_my_account_my_orders_column_order-status', [ $this, 'render_order_status_column' ] );
	}

	/**
	 * M6 — render the "Orders" table's status cell as a tone-coloured badge
	 * instead of Woo's plain localised text.
	 *
	 * Registering this callback makes `myaccount/orders.php`'s own
	 * `has_action( 'woocommerce_my_account_my_orders_column_' . $column_id )`
	 * guard true for the `order-status` column, so core calls this INSTEAD of
	 * printing `wc_get_order_status_name()` as bare text — no template
	 * override needed for this one cell.
	 *
	 * @param mixed $order The order whose status cell is being rendered.
	 *                      Typed `mixed`: this is a `do_action()` callback, so
	 *                      a plugin re-firing the hook by hand could hand it
	 *                      anything, and this file runs under strict_types.
	 */
	public function render_order_status_column( mixed $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		echo self::status_badge( $order->get_status() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status_badge() escapes its own output.
	}

	/**
	 * The badge tone for a Woo order status. Public and documented so the
	 * override templates (`dashboard.php`'s recent-orders table,
	 * `view-order.php`'s order head) can be told which tone a status carries
	 * without re-deriving the mapping — see `status_badge()`, which is what
	 * they actually call.
	 *
	 * @param string $status Order status slug WITHOUT the `wc-` prefix.
	 */
	public static function status_tone( string $status ): string {
		return self::STATUS_TONES[ $status ] ?? self::DEFAULT_TONE;
	}

	/**
	 * The badge markup for a Woo order status — the single place that builds
	 * `<span class="wtb-status-badge wtb-status-badge--{tone} is-{status}">{label}</span>`,
	 * reused by `render_order_status_column()` above and by the two override
	 * templates that draw the same badge (dashboard.php's recent orders,
	 * view-order.php's order head) rather than each re-building it.
	 *
	 * BOTH classes are emitted on purpose, and the tone one is the load-bearing
	 * half. The mockup draws three tones over Woo's seven-plus statuses
	 * (STATUS_TONES above), so a stylesheet given only `is-{status}` would have
	 * to re-implement that mapping in CSS — two copies of one decision, free to
	 * drift, and silently wrong for whatever status a plugin adds next (the CSS
	 * copy has no equivalent of DEFAULT_TONE). Emitting the resolved tone keeps
	 * the mapping in exactly one place: `src/css/woo/account.css` styles the
	 * three `--{tone}` classes and never names a status.
	 *
	 * `is-{status}` stays as well, because it costs nothing and it is the hook a
	 * child theme or a plugin needs to single out one specific status.
	 *
	 * `wc_get_order_status_name()` accepts the slug with or without the `wc-`
	 * prefix (it normalises internally), so the unprefixed slug this method
	 * takes is handed straight through.
	 *
	 * @param string $status Order status slug WITHOUT the `wc-` prefix.
	 */
	public static function status_badge( string $status ): string {
		return sprintf(
			'<span class="wtb-status-badge wtb-status-badge--%1$s is-%2$s">%3$s</span>',
			esc_attr( self::status_tone( $status ) ),
			esc_attr( $status ),
			esc_html( wc_get_order_status_name( $status ) )
		);
	}

	/**
	 * M2 — the icon markup for a My Account navigation endpoint, or '' when
	 * the endpoint has no mapping. '' rather than a fallback glyph: a
	 * plugin-added tab (no entry in NAV_ICONS) must still render its label,
	 * just without an icon in front of it, per the task's contract.
	 *
	 * @param string $endpoint Endpoint id, as `wc_get_account_menu_items()` keys it.
	 */
	public static function nav_icon( string $endpoint ): string {
		if ( ! isset( self::NAV_ICONS[ $endpoint ] ) ) {
			return '';
		}

		return Icons::get(
			self::NAV_ICONS[ $endpoint ],
			[
				'class' => 'wtb-account-nav__icon',
				'size'  => 18,
			]
		);
	}
}
