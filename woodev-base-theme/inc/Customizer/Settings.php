<?php
/**
 * Validated access to the appearance settings that compile to CSS.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Customizer;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/**
 * One validator per setting, used twice: as the Customizer sanitize_callback and
 * as the front-end resolver. A value that never passed through here never
 * reaches a CSS custom property.
 */
final class Settings {

	public const CONTAINER_WIDTH_MIN     = 960;
	public const CONTAINER_WIDTH_MAX     = 1920;
	public const CONTAINER_WIDTH_DEFAULT = 1440;

	public const BASE_FONT_SIZE_MIN     = 14;
	public const BASE_FONT_SIZE_MAX     = 20;
	public const BASE_FONT_SIZE_DEFAULT = 16;

	/**
	 * ADR-008: `warm-clay` equals the :root defaults, so selecting it is the
	 * one palette that emits no override at all (InlineStyles::build_css()).
	 * Guaranteed resolvable even if inc/generated/palettes.php is missing or
	 * tampered with — see Palettes, which always synthesises this slug.
	 */
	public const PALETTE_DEFAULT = 'warm-clay';

	/**
	 * '' means "no override": the palette's own accent wins. A picked colour
	 * is stored as a normalized 6-digit hex (`#rrggbb`, lowercase) and
	 * converted to --accent-h/--accent-c by ColorConverter at render time —
	 * the theme's palette architecture (ADR-008) has no admin-facing
	 * lightness control, only hue and chroma.
	 */
	public const ACCENT_DEFAULT = '';

	/**
	 * `--radius` is a px BASE, not a step in a rem lookup table — see the
	 * docblock on sanitize_radius() for why this replaced radius_scale
	 * outright instead of reusing its theme_mod key.
	 */
	public const RADIUS_MIN     = 0;
	public const RADIUS_MAX     = 16;
	public const RADIUS_DEFAULT = 10;

	public const FONT_IDENTITY = 'identity';
	public const FONT_SYSTEM   = 'system';
	public const FONT_DEFAULT  = self::FONT_IDENTITY;

	public const FONTS = [ self::FONT_IDENTITY, self::FONT_SYSTEM ];

	/**
	 * The system fallback stack already baked into --font-display/-body/-mono
	 * as the tail of every value in src/tokens/tokens.mjs (ADR-007): picking
	 * `system` here reproduces that exact fallback, so the theme degrades to
	 * its own documented v1 look rather than to something new. Duplicated by
	 * hand rather than parsed from the generated CSS — Settings has no build
	 * dependency — so keep the two in sync if the fallback tail ever changes.
	 *
	 * @var array<string, string>
	 */
	public const FONT_SYSTEM_STACK = [
		'--font-display' => 'system-ui, "Segoe UI", Roboto, sans-serif',
		'--font-body'    => 'system-ui, "Segoe UI", Roboto, sans-serif',
		'--font-mono'    => 'ui-monospace, "SF Mono", Menlo, monospace',
	];

	public const CTA_REVEAL_HOVER   = 'hover';
	public const CTA_REVEAL_ALWAYS  = 'always';
	public const CTA_REVEAL_DEFAULT = self::CTA_REVEAL_HOVER;

	public const CTA_REVEALS = [ self::CTA_REVEAL_HOVER, self::CTA_REVEAL_ALWAYS ];

	/**
	 * Customizer sanitize callback for `container_width`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_container_width( mixed $value ): int {
		return self::clamp( $value, self::CONTAINER_WIDTH_MIN, self::CONTAINER_WIDTH_MAX, self::CONTAINER_WIDTH_DEFAULT );
	}

	/**
	 * Content container cap, in pixels.
	 */
	public static function container_width(): int {
		return self::sanitize_container_width( get_theme_mod( 'container_width', self::CONTAINER_WIDTH_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `base_font_size`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_base_font_size( mixed $value ): int {
		return self::clamp( $value, self::BASE_FONT_SIZE_MIN, self::BASE_FONT_SIZE_MAX, self::BASE_FONT_SIZE_DEFAULT );
	}

	/**
	 * Root font size, in pixels.
	 */
	public static function base_font_size(): int {
		return self::sanitize_base_font_size( get_theme_mod( 'base_font_size', self::BASE_FONT_SIZE_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `palette`.
	 *
	 * The closed set is Palettes::slugs(), not a hardcoded list of seven: a
	 * tampered or absent inc/generated/palettes.php still resolves (Palettes
	 * always synthesises PALETTE_DEFAULT), so a stored slug that the current
	 * file no longer supports falls back here exactly like any other invalid
	 * value, rather than the request fataling.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_palette( mixed $value ): string {
		return self::closed_set( $value, Palettes::slugs(), self::PALETTE_DEFAULT );
	}

	/**
	 * The admin's chosen palette slug.
	 */
	public static function palette(): string {
		return self::sanitize_palette( get_theme_mod( 'palette', self::PALETTE_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `accent`.
	 *
	 * Accepts a 3- or 6-digit hex colour, with or without a leading '#',
	 * case-insensitively, and normalizes it to lowercase `#rrggbb`. Anything
	 * else — including a non-string, an out-of-shape string, or a value that
	 * merely LOOKS like CSS ('red', 'rgb(0,0,0)', a `sanitize_hex_color()`-
	 * style breakout attempt) — falls back to '' (ACCENT_DEFAULT), which
	 * InlineStyles reads as "no override, the palette's accent wins".
	 *
	 * A dedicated pattern rather than WordPress core's own
	 * sanitize_hex_color(): core returns null on rejection (not the empty
	 * string this class's fail-closed convention needs everywhere else) and
	 * accepts only a leading '#', where a colour picker's raw POST value is
	 * worth normalizing either way.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_accent( mixed $value ): string {
		if ( ! \is_string( $value ) ) {
			return self::ACCENT_DEFAULT;
		}

		$value = \trim( $value );

		if ( 1 !== \preg_match( '/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value, $matches ) ) {
			return self::ACCENT_DEFAULT;
		}

		$hex = \strtolower( $matches[1] );

		if ( 3 === \strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return '#' . $hex;
	}

	/**
	 * The admin's accent override, or '' when the palette's own accent applies.
	 */
	public static function accent(): string {
		return self::sanitize_accent( get_theme_mod( 'accent', self::ACCENT_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `radius`.
	 *
	 * Replaces the retired `radius_scale` theme_mod under a NEW key rather
	 * than reusing it. radius_scale stored one of four STRING steps
	 * ('none'…'lg') mapped to rem lengths; `radius` stores a PX INTEGER
	 * 0–16 directly, so reusing the old key would feed a site's stored
	 * string ('lg') into clamp()'s is_numeric() check, silently fail it, and
	 * collapse to the new default (10px) — reinterpreting a real admin
	 * choice as if it had never been made, with nothing in the UI or the log
	 * to say so. A new key makes the same outcome (old choice not carried
	 * forward) visible instead: `radius_scale` is simply orphaned, and
	 * `radius` starts fresh at its own documented default — which, because
	 * 10px is what `radius_scale = md` used to resolve to (0.625rem at the
	 * 16px root), is visually a no-op for the common case anyway. There is
	 * no shipped release of the pre-identity Customizer to migrate away
	 * from (this theme has not reached v1), so the honest reset costs
	 * nothing today that reuse-and-corrupt would not have cost silently
	 * later.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_radius( mixed $value ): int {
		return self::clamp( $value, self::RADIUS_MIN, self::RADIUS_MAX, self::RADIUS_DEFAULT );
	}

	/**
	 * The chosen radius base, in pixels.
	 */
	public static function radius(): int {
		return self::sanitize_radius( get_theme_mod( 'radius', self::RADIUS_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `font`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_font( mixed $value ): string {
		return self::closed_set( $value, self::FONTS, self::FONT_DEFAULT );
	}

	/**
	 * The admin's chosen font mode: FONT_IDENTITY (Golos Text / IBM Plex, the
	 * default) or FONT_SYSTEM (the OS stack, zero webfont bytes fetched).
	 */
	public static function font(): string {
		return self::sanitize_font( get_theme_mod( 'font', self::FONT_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `cta_reveal`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_cta_reveal( mixed $value ): string {
		return self::closed_set( $value, self::CTA_REVEALS, self::CTA_REVEAL_DEFAULT );
	}

	/**
	 * The admin's chosen add-to-cart reveal mode: CTA_REVEAL_HOVER (default)
	 * or CTA_REVEAL_ALWAYS.
	 *
	 * Consumer: inc/Woo/CtaAttribute.php renders `data-cta="…"` on `<html>`
	 * via the `language_attributes` filter, on every front-end request
	 * except wp-admin and `/wp-login.php` (see that file for the precise
	 * guard and why `is_admin()` alone does not cover the login screen; a
	 * product loop can render on any front-end page through a shortcode or
	 * block, which is why the guard stops at those two exclusions rather
	 * than narrowing to WooCommerce-specific contexts), and calls this
	 * method for the value — so a tampered theme_mod is sanitised before it
	 * can reach markup. Nothing here touches CSS output:
	 * `[data-cta="always"]` is the escape-hatch selector the shipped
	 * stylesheet already keys off.
	 *
	 * This setting governs POINTER devices only. woo.css forces the static,
	 * always-visible treatment under `@media (hover: none)` regardless of what
	 * is stored here, because a touchscreen cannot fire :hover and the default
	 * would otherwise ship an unreachable button to every phone visitor.
	 */
	public static function cta_reveal(): string {
		return self::sanitize_cta_reveal( get_theme_mod( 'cta_reveal', self::CTA_REVEAL_DEFAULT ) );
	}

	// --- front page (F2, docs/plans/2026-07-28-front-page-completion.md) ---

	public const FRONT_HERO_EYEBROW_DEFAULT = '';

	public const FRONT_HERO_LEDE_DEFAULT = '';

	/**
	 * Hero trust badges: one `Text | icon` line each, icon optional. Capped
	 * at the CSS grid's three-badge layout.
	 */
	public const FRONT_HERO_TRUST_DEFAULT   = '';
	public const FRONT_HERO_TRUST_MAX_ITEMS = 3;

	public const FRONT_HERO_ART_AUTO    = 'auto';
	public const FRONT_HERO_ART_OFF     = 'off';
	public const FRONT_HERO_ART_DEFAULT = self::FRONT_HERO_ART_AUTO;

	public const FRONT_HERO_ARTS = [ self::FRONT_HERO_ART_AUTO, self::FRONT_HERO_ART_OFF ];

	/**
	 * Value band items: one `Title | Text | icon` line each. Capped at the
	 * CSS grid's four-item layout.
	 */
	public const FRONT_VALUE_ITEMS_DEFAULT   = '';
	public const FRONT_VALUE_ITEMS_MAX_ITEMS = 4;

	public const FRONT_PROMO_TITLE_DEFAULT     = '';
	public const FRONT_PROMO_TEXT_DEFAULT      = '';
	public const FRONT_PROMO_CTA_LABEL_DEFAULT = '';
	public const FRONT_PROMO_CTA_URL_DEFAULT   = '';
	public const FRONT_PROMO_IMAGE_DEFAULT     = 0;

	public const FRONT_ICON_DEFAULT = 'check';

	/**
	 * The closed set of icon slugs a `… | icon` badge line may name. A line
	 * naming an icon outside this set falls back to FRONT_ICON_DEFAULT — never
	 * a fatal, never a reference to an SVG the theme did not ship.
	 *
	 * The name is historical: this began (s17) as the front page's own icon
	 * set, and it is now the shared whitelist for every badge-shaped setting —
	 * the front hero's trust lines, the value band, the two product-page
	 * badges, and s19's cart/checkout secure notes. It is deliberately NOT the
	 * full vendored icon list (`scripts/copy-icons.mjs`): most of those are
	 * chrome (`menu`, `chevron-*`, `log-out`) that would be meaningless next to
	 * a sentence, and offering them in a Customizer description is worse than
	 * not offering them at all.
	 *
	 * @var array<int, string>
	 */
	public const FRONT_ICONS = [
		'check',
		'truck',
		'shield-check',
		'refresh-cw',
		'leaf',
		'package',
		'credit-card',
		'headphones',
		'lock',
	];

	/**
	 * Customizer sanitize callback for `front_hero_eyebrow`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_hero_eyebrow( mixed $value ): string {
		return \is_string( $value ) ? \sanitize_text_field( $value ) : self::FRONT_HERO_EYEBROW_DEFAULT;
	}

	/**
	 * One line shown above the hero headline; empty hides it.
	 */
	public static function front_hero_eyebrow(): string {
		return self::sanitize_front_hero_eyebrow( get_theme_mod( 'front_hero_eyebrow', self::FRONT_HERO_EYEBROW_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `front_hero_lede`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_hero_lede( mixed $value ): string {
		return \is_string( $value ) ? \sanitize_text_field( $value ) : self::FRONT_HERO_LEDE_DEFAULT;
	}

	/**
	 * Hero subtitle; empty falls back to the site tagline.
	 */
	public static function front_hero_lede(): string {
		return self::sanitize_front_hero_lede( get_theme_mod( 'front_hero_lede', self::FRONT_HERO_LEDE_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `front_hero_trust`.
	 *
	 * Input is the raw textarea: one `Text | icon` line per badge.
	 * Non-string input falls back to the default; blank lines and lines with
	 * an empty label are dropped; the icon is optional and validated against
	 * FRONT_ICONS. The result is capped to FRONT_HERO_TRUST_MAX_ITEMS lines
	 * and re-encoded to the same canonical `Text | icon` shape, so
	 * sanitising an already-sanitized value is a no-op.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_hero_trust( mixed $value ): string {
		if ( ! \is_string( $value ) ) {
			return self::FRONT_HERO_TRUST_DEFAULT;
		}

		return self::encode_lines(
			\array_map(
				static fn( array $item ): string => "{$item['label']} | {$item['icon']}",
				self::parse_trust_items( $value )
			)
		);
	}

	/**
	 * Hero trust badges, parsed and validated.
	 *
	 * @return array<int, array{label: string, icon: string}>
	 */
	public static function front_hero_trust(): array {
		$raw = get_theme_mod( 'front_hero_trust', self::FRONT_HERO_TRUST_DEFAULT );

		return self::parse_trust_items( self::sanitize_front_hero_trust( $raw ) );
	}

	/**
	 * Customizer sanitize callback for `front_hero_art`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_hero_art( mixed $value ): string {
		return self::closed_set( $value, self::FRONT_HERO_ARTS, self::FRONT_HERO_ART_DEFAULT );
	}

	/**
	 * The hero art column mode: FRONT_HERO_ART_AUTO (the front page's
	 * featured image, or a themed plate when there isn't one) or
	 * FRONT_HERO_ART_OFF (no art column; the hero renders single-column).
	 */
	public static function front_hero_art(): string {
		return self::sanitize_front_hero_art( get_theme_mod( 'front_hero_art', self::FRONT_HERO_ART_DEFAULT ) );
	}


	/**
	 * Product page trust badges (B9, docs/plans/2026-07-28-catalogue-and-product.md):
	 * one `Text | icon` line each, icon optional. Two independently named
	 * settings rather than front_hero_trust()'s repeater — the mockup shows
	 * exactly two fixed-purpose badges (a delivery cutoff, a warranty), not
	 * an admin-sized list.
	 */
	public const PRODUCT_TRUST_BADGE_ONE_DEFAULT = '';
	public const PRODUCT_TRUST_BADGE_TWO_DEFAULT = '';

	/**
	 * Customizer sanitize callback for `product_trust_badge_one`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_product_trust_badge_one( mixed $value ): string {
		return self::sanitize_badge_line( $value );
	}

	/**
	 * Product page trust badge #1 (e.g. a delivery-cutoff line), parsed and
	 * validated; null when unset.
	 *
	 * @return array{label: string, icon: string}|null
	 */
	public static function product_trust_badge_one(): ?array {
		$raw = get_theme_mod( 'product_trust_badge_one', self::PRODUCT_TRUST_BADGE_ONE_DEFAULT );

		return self::parse_badge_line( self::sanitize_product_trust_badge_one( $raw ) );
	}

	/**
	 * Customizer sanitize callback for `product_trust_badge_two`. See
	 * sanitize_product_trust_badge_one() — identical shape, second badge slot.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_product_trust_badge_two( mixed $value ): string {
		return self::sanitize_badge_line( $value );
	}

	/**
	 * Product page trust badge #2 (e.g. a warranty line), parsed and
	 * validated; null when unset.
	 *
	 * @return array{label: string, icon: string}|null
	 */
	public static function product_trust_badge_two(): ?array {
		$raw = get_theme_mod( 'product_trust_badge_two', self::PRODUCT_TRUST_BADGE_TWO_DEFAULT );

		return self::parse_badge_line( self::sanitize_product_trust_badge_two( $raw ) );
	}

	/**
	 * The reassurance line under the cart's and the checkout's place-order
	 * button (#42, plan rows C10 and K9): one `Text | icon` line each, icon
	 * optional, defaulting to `lock`.
	 *
	 * BOTH DEFAULT TO EMPTY, and that is the whole design. The mockup writes
	 * "Payment happens on the bank's secure page" on the cart and "Card details
	 * never reach the shop" on the checkout — but whether either sentence is
	 * TRUE depends on the payment gateway the store owner installs, and a theme
	 * cannot know. Shipping a default would have the theme make a payment-
	 * security claim on the store's behalf. An empty value renders nothing at
	 * all, exactly like the product-page badges above.
	 *
	 * Two settings rather than one shared line, because the mockup's two
	 * sentences differ and they sit on different pages: an owner may well want
	 * the delivery-side reassurance on the cart and the card-side one at
	 * payment.
	 */
	public const CART_SECURE_NOTE_DEFAULT     = '';
	public const CHECKOUT_SECURE_NOTE_DEFAULT = '';

	/**
	 * Icon used when a secure-note line names none. Not FRONT_ICON_DEFAULT
	 * (`check`): a padlock is what the mockup draws and what the sentence is
	 * about, and a tick next to "payment is secure" reads as a confirmation
	 * that something already happened.
	 */
	public const SECURE_NOTE_ICON_DEFAULT = 'lock';

	/**
	 * Customizer sanitize callback for `cart_secure_note`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_cart_secure_note( mixed $value ): string {
		return self::sanitize_badge_line( $value, self::SECURE_NOTE_ICON_DEFAULT );
	}

	/**
	 * The cart panel's secure-payment note, parsed and validated; null when
	 * unset (the default), which is what suppresses the whole line.
	 *
	 * @return array{label: string, icon: string}|null
	 */
	public static function cart_secure_note(): ?array {
		return self::secure_note( 'cart_secure_note', self::CART_SECURE_NOTE_DEFAULT );
	}

	/**
	 * Customizer sanitize callback for `checkout_secure_note`. See
	 * sanitize_cart_secure_note() — identical shape, checkout panel.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_checkout_secure_note( mixed $value ): string {
		return self::sanitize_badge_line( $value, self::SECURE_NOTE_ICON_DEFAULT );
	}

	/**
	 * The checkout panel's secure-payment note, parsed and validated; null when
	 * unset (the default).
	 *
	 * @return array{label: string, icon: string}|null
	 */
	public static function checkout_secure_note(): ?array {
		return self::secure_note( 'checkout_secure_note', self::CHECKOUT_SECURE_NOTE_DEFAULT );
	}

	/**
	 * Shared reader for the two secure-note settings.
	 *
	 * Differs from product_trust_badge_one()/-two() in exactly one place: the
	 * icon a line that names none falls back to is SECURE_NOTE_ICON_DEFAULT,
	 * not FRONT_ICON_DEFAULT. That default is threaded all the way down to
	 * sanitize_icon() rather than substituted afterwards, which is not a
	 * refinement — substituting afterwards cannot tell "no icon given" from
	 * "`check` given explicitly", so an admin who typed `Text | check` would
	 * silently get a padlock.
	 *
	 * @param string $mod           Theme-mod name.
	 * @param string $default_value That setting's documented default.
	 * @return array{label: string, icon: string}|null
	 */
	private static function secure_note( string $mod, string $default_value ): ?array {
		$raw = get_theme_mod( $mod, $default_value );

		return self::parse_badge_line(
			self::sanitize_badge_line( $raw, self::SECURE_NOTE_ICON_DEFAULT ),
			self::SECURE_NOTE_ICON_DEFAULT
		);
	}

	/**
	 * Shared sanitizer for a single `Text | icon` badge setting. Non-string
	 * input, a blank value, or a value whose label is empty all sanitize to
	 * '' — the same "unset" state as the default.
	 *
	 * Shared by four settings, not two: the two product-page trust badges
	 * (s18) and the cart and checkout secure notes (s19). The name says
	 * "badge line" rather than "product trust badge" for that reason.
	 *
	 * Note this WRITES the resolved icon back into the stored value, so
	 * `$default_icon` has to match the reader's — an icon-less "Delivery" is
	 * stored as "Delivery | check" for a product badge and "Delivery | lock"
	 * for a secure note, and a mismatch here would make the stored value
	 * disagree with what the page renders.
	 *
	 * @param mixed  $value        Raw value.
	 * @param string $default_icon Icon for a line that names none.
	 */
	private static function sanitize_badge_line( mixed $value, string $default_icon = self::FRONT_ICON_DEFAULT ): string {
		if ( ! \is_string( $value ) ) {
			return '';
		}

		$item = self::parse_badge_line( $value, $default_icon );

		return null === $item ? '' : "{$item['label']} | {$item['icon']}";
	}

	/**
	 * Parse an already-sanitized-or-raw `Text | icon` value into a validated
	 * badge item. Only the FIRST line is considered: this is a plain text
	 * control, not a textarea, so a stray newline (however it got there) is
	 * not a second badge.
	 *
	 * @param string $raw          Raw or already-canonical `Text | icon` value.
	 * @param string $default_icon Icon for a line that names none — see
	 *                             sanitize_icon().
	 * @return array{label: string, icon: string}|null
	 */
	private static function parse_badge_line( string $raw, string $default_icon = self::FRONT_ICON_DEFAULT ): ?array {
		$lines = self::split_lines( $raw, 1 );

		if ( [] === $lines ) {
			return null;
		}

		$fields = \array_pad( \array_map( 'trim', \explode( '|', $lines[0], 2 ) ), 2, '' );
		$label  = \sanitize_text_field( $fields[0] );

		if ( '' === $label ) {
			return null;
		}

		return [
			'label' => $label,
			'icon'  => self::sanitize_icon( $fields[1], $default_icon ),
		];
	}

	/**
	 * Customizer sanitize callback for `front_value_items`.
	 *
	 * Same shape as sanitize_front_hero_trust(), one field wider: a
	 * `Title | Text | icon` line per item, capped to
	 * FRONT_VALUE_ITEMS_MAX_ITEMS, an empty title drops the whole line, an
	 * empty or unrecognised icon falls back to FRONT_ICON_DEFAULT.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_value_items( mixed $value ): string {
		if ( ! \is_string( $value ) ) {
			return self::FRONT_VALUE_ITEMS_DEFAULT;
		}

		return self::encode_lines(
			\array_map(
				static fn( array $item ): string => "{$item['title']} | {$item['text']} | {$item['icon']}",
				self::parse_value_items( $value )
			)
		);
	}

	/**
	 * Value band items, parsed and validated.
	 *
	 * @return array<int, array{title: string, text: string, icon: string}>
	 */
	public static function front_value_items(): array {
		$raw = get_theme_mod( 'front_value_items', self::FRONT_VALUE_ITEMS_DEFAULT );

		return self::parse_value_items( self::sanitize_front_value_items( $raw ) );
	}

	/**
	 * Customizer sanitize callback for `front_promo_title`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_promo_title( mixed $value ): string {
		return \is_string( $value ) ? \sanitize_text_field( $value ) : self::FRONT_PROMO_TITLE_DEFAULT;
	}

	/**
	 * Promo section heading; empty suppresses the whole section.
	 */
	public static function front_promo_title(): string {
		return self::sanitize_front_promo_title( get_theme_mod( 'front_promo_title', self::FRONT_PROMO_TITLE_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `front_promo_text`.
	 *
	 * Uses sanitize_textarea_field(), not sanitize_text_field(): the promo
	 * body is a short paragraph, not a one-line field, so its line breaks
	 * must survive. Still plain text — no wp_kses_post(), no markup allowed.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_promo_text( mixed $value ): string {
		return \is_string( $value ) ? \sanitize_textarea_field( $value ) : self::FRONT_PROMO_TEXT_DEFAULT;
	}

	/**
	 * Promo section body copy, plain text.
	 */
	public static function front_promo_text(): string {
		return self::sanitize_front_promo_text( get_theme_mod( 'front_promo_text', self::FRONT_PROMO_TEXT_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `front_promo_cta_label`.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_promo_cta_label( mixed $value ): string {
		return \is_string( $value ) ? \sanitize_text_field( $value ) : self::FRONT_PROMO_CTA_LABEL_DEFAULT;
	}

	/**
	 * Promo call-to-action button label; the button only renders with both a
	 * label and a URL.
	 */
	public static function front_promo_cta_label(): string {
		return self::sanitize_front_promo_cta_label( get_theme_mod( 'front_promo_cta_label', self::FRONT_PROMO_CTA_LABEL_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `front_promo_cta_url`.
	 *
	 * The esc_url_raw() call already refuses a `javascript:` (or any other
	 * disallowed) scheme and returns ''; pinned by a dedicated test because
	 * this is the one setting here that lands directly in an href.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_promo_cta_url( mixed $value ): string {
		return \is_string( $value ) ? \esc_url_raw( $value ) : self::FRONT_PROMO_CTA_URL_DEFAULT;
	}

	/**
	 * Promo call-to-action URL.
	 */
	public static function front_promo_cta_url(): string {
		return self::sanitize_front_promo_cta_url( get_theme_mod( 'front_promo_cta_url', self::FRONT_PROMO_CTA_URL_DEFAULT ) );
	}

	/**
	 * Customizer sanitize callback for `front_promo_image`.
	 *
	 * Non-numeric input (array, object, bool, a non-numeric string) falls
	 * back rather than casting, for the same reason clamp() does: an
	 * uncontrolled (int) cast on an array or object is not the fail-closed
	 * behaviour this class promises everywhere else.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_front_promo_image( mixed $value ): int {
		return \is_numeric( $value ) ? \absint( $value ) : self::FRONT_PROMO_IMAGE_DEFAULT;
	}

	/**
	 * Promo image attachment ID; 0 means none was chosen. The caller is
	 * responsible for verifying the ID still resolves to a real attachment at
	 * render time (a stored id can be valid Customizer state and a deleted
	 * attachment at once).
	 */
	public static function front_promo_image(): int {
		return self::sanitize_front_promo_image( get_theme_mod( 'front_promo_image', self::FRONT_PROMO_IMAGE_DEFAULT ) );
	}

	/**
	 * Shared shape for validating an icon slug against the closed FRONT_ICONS
	 * set.
	 *
	 * `$default_icon` exists because not every consumer wants `check` back for
	 * an icon-less or unrecognised line: the cart/checkout secure notes want a
	 * padlock (Settings::SECURE_NOTE_ICON_DEFAULT). It must itself be a member
	 * of FRONT_ICONS — this is the one place that guarantees the returned slug
	 * names a vendored SVG, so a caller passing a slug the theme does not ship
	 * would defeat the whole check. Anything outside the set falls back to
	 * FRONT_ICON_DEFAULT rather than being trusted.
	 *
	 * @param string $value        Raw icon slug, already trimmed.
	 * @param string $default_icon Slug to use when `$value` is empty or unknown.
	 */
	private static function sanitize_icon( string $value, string $default_icon = self::FRONT_ICON_DEFAULT ): string {
		if ( \in_array( $value, self::FRONT_ICONS, true ) ) {
			return $value;
		}

		return \in_array( $default_icon, self::FRONT_ICONS, true ) ? $default_icon : self::FRONT_ICON_DEFAULT;
	}

	/**
	 * Split raw textarea input into trimmed, non-empty lines, capped to
	 * $cap. The cap applies here, to the raw line count, BEFORE any
	 * per-field validation drops individual items — matching the documented
	 * parsing order (split, trim, drop empty lines, cap, then validate
	 * fields).
	 *
	 * @param string $raw Raw textarea value.
	 * @param int    $cap Maximum number of lines to keep.
	 *
	 * @return array<int, string>
	 */
	private static function split_lines( string $raw, int $cap ): array {
		$lines = \preg_split( '/\r\n|\r|\n/', $raw );

		if ( false === $lines ) {
			return [];
		}

		$lines = \array_map( 'trim', $lines );
		$lines = \array_values( \array_filter( $lines, static fn( string $line ): bool => '' !== $line ) );

		return \array_slice( $lines, 0, $cap );
	}

	/**
	 * Re-join canonical lines with newlines; an empty list yields ''.
	 *
	 * @param array<int, string> $lines Canonical lines.
	 */
	private static function encode_lines( array $lines ): string {
		return \implode( "\n", $lines );
	}

	/**
	 * Parse and validate `front_hero_trust` lines.
	 *
	 * @param string $raw Raw (already sanitized) `Text | icon` lines, newline-separated.
	 *
	 * @return array<int, array{label: string, icon: string}>
	 */
	private static function parse_trust_items( string $raw ): array {
		$items = [];

		foreach ( self::split_lines( $raw, self::FRONT_HERO_TRUST_MAX_ITEMS ) as $line ) {
			$fields = \array_pad( \array_map( 'trim', \explode( '|', $line, 2 ) ), 2, '' );
			$label  = \sanitize_text_field( $fields[0] );

			if ( '' === $label ) {
				continue;
			}

			$items[] = [
				'label' => $label,
				'icon'  => self::sanitize_icon( $fields[1] ),
			];
		}

		return $items;
	}

	/**
	 * Parse and validate `front_value_items` lines.
	 *
	 * @param string $raw Raw (already sanitized) `Title | Text | icon` lines, newline-separated.
	 *
	 * @return array<int, array{title: string, text: string, icon: string}>
	 */
	private static function parse_value_items( string $raw ): array {
		$items = [];

		foreach ( self::split_lines( $raw, self::FRONT_VALUE_ITEMS_MAX_ITEMS ) as $line ) {
			$fields = \array_pad( \array_map( 'trim', \explode( '|', $line, 3 ) ), 3, '' );
			$title  = \sanitize_text_field( $fields[0] );

			if ( '' === $title ) {
				continue;
			}

			$items[] = [
				'title' => $title,
				'text'  => \sanitize_text_field( $fields[1] ),
				'icon'  => self::sanitize_icon( $fields[2] ),
			];
		}

		return $items;
	}

	/**
	 * Shared shape for a setting whose valid values are a closed set of
	 * strings: a non-string, or a string outside the set, both fall back.
	 *
	 * @param mixed         $value    Raw value.
	 * @param array<string> $set      Valid values.
	 * @param string        $fallback Value for anything outside the set.
	 */
	private static function closed_set( mixed $value, array $set, string $fallback ): string {
		return \is_string( $value ) && \in_array( $value, $set, true )
			? $value
			: $fallback;
	}

	/**
	 * Numeric setting reduced to an int inside [min, max].
	 *
	 * Non-numeric input (array, object, "wide") falls back rather than casting:
	 * (int) on an object throws, and (int) 'wide' is a silent 0 that would
	 * collapse the layout.
	 *
	 * is_numeric() is necessary but NOT sufficient: it accepts overflowing
	 * literals like '1e309', which become INF as a float. Casting a float
	 * outside the integer range is undefined in PHP and yields 0 in practice,
	 * so an absurdly LARGE value would clamp to the MINIMUM. is_finite() is what
	 * turns that into the documented fallback; NAN takes the same path.
	 *
	 * Version note, because it decides how visible the bug is: PHP 8.5 warns
	 * ("The float 1.0E+100 is not representable as an int, cast occurred"), but
	 * PHP 8.1 — this theme's declared floor, and what the test containers run —
	 * is SILENT. On the floor it is a wrong layout with nothing in the log.
	 *
	 * @param mixed $value    Raw value.
	 * @param int   $min      Lower bound.
	 * @param int   $max      Upper bound.
	 * @param int   $fallback Value for non-numeric or non-finite input.
	 */
	private static function clamp( mixed $value, int $min, int $max, int $fallback ): int {
		if ( ! \is_numeric( $value ) ) {
			return $fallback;
		}

		$number = (float) $value;

		if ( ! \is_finite( $number ) ) {
			return $fallback;
		}

		// Clamp as a FLOAT, then cast. The other order looks equivalent and is
		// not: casting a float outside the integer range is undefined in PHP —
		// '1e100' passes is_numeric() and is_finite(), then (int) emits "The
		// float 1.0E+100 is not representable as an int" and yields 0, so the
		// largest possible input would clamp to the MINIMUM. Bounding first
		// means the cast only ever sees a value inside [min, max].
		return (int) round( max( (float) $min, min( (float) $max, $number ) ) );
	}
}
