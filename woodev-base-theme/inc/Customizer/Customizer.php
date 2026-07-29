<?php
/**
 * Customizer registration (spec §6, extended by ADR-008's identity controls).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Customizer;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Scheme;
use Woodev\Theme\Base\Templates\Layout;

/**
 * Registers the v1 sections, settings and controls.
 *
 * Every sanitize_callback here is the same validator the front end resolves
 * with (Layout, Scheme, Settings), so the Customizer cannot store a value a
 * template or the inline stylesheet would then reject.
 */
final class Customizer {

	/**
	 * Hook registration into WordPress.
	 */
	public function register(): void {
		add_action( 'customize_register', [ $this, 'configure' ] );
	}

	/**
	 * Declare the sections, settings and controls.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer manager.
	 */
	public function configure( \WP_Customize_Manager $wp_customize ): void {
		$this->add_section( $wp_customize, 'woodev_base_colors', __( 'Colors', 'woodev-base-theme' ), 30 );
		$this->add_section( $wp_customize, 'woodev_base_typography', __( 'Typography', 'woodev-base-theme' ), 40 );
		$this->add_section( $wp_customize, 'woodev_base_layout', __( 'Layout', 'woodev-base-theme' ), 50 );
		$this->add_section( $wp_customize, 'woodev_base_header', __( 'Header', 'woodev-base-theme' ), 60 );
		$this->add_section( $wp_customize, 'woodev_base_footer', __( 'Footer', 'woodev-base-theme' ), 70 );
		$this->add_section( $wp_customize, 'woodev_base_shop', __( 'Shop', 'woodev-base-theme' ), 80 );

		$this->add_select(
			$wp_customize,
			'color_scheme_default',
			'woodev_base_colors',
			__( 'Colour scheme', 'woodev-base-theme' ),
			[
				'system' => __( 'Follow system', 'woodev-base-theme' ),
				'light'  => __( 'Light', 'woodev-base-theme' ),
				'dark'   => __( 'Dark', 'woodev-base-theme' ),
			],
			Scheme::DEFAULT_SCHEME,
			Scheme::sanitize_default( ... ),
			__( 'The scheme a visitor sees before making their own choice.', 'woodev-base-theme' )
		);

		/*
		 * Spec §6 ships the switcher ON, and that is what is registered here.
		 *
		 * Note that this is the ONE setting whose default and whose fail-closed
		 * value differ: Scheme::sanitize_toggle() returns false for anything that
		 * is not `true` or the string '1', because a switcher whose stored state
		 * cannot be read is worse than no switcher. CustomizerTest documents that
		 * exception explicitly rather than letting the generic
		 * default-equals-junk-fallback assertion quietly dictate the product
		 * default — which is what happened on the first pass through this task.
		 */
		$this->add_checkbox(
			$wp_customize,
			'color_scheme_toggle',
			'woodev_base_colors',
			__( 'Show the colour-scheme switcher', 'woodev-base-theme' ),
			true,
			Scheme::sanitize_toggle( ... ),
			__( 'Lets a visitor override the default and remembers their choice.', 'woodev-base-theme' )
		);

		$this->add_select(
			$wp_customize,
			'palette',
			'woodev_base_colors',
			__( 'Colour palette', 'woodev-base-theme' ),
			$this->palette_choices(),
			Settings::PALETTE_DEFAULT,
			Settings::sanitize_palette( ... ),
			__( 'The neutral temperature and accent that drive every surface, border and wash in the theme.', 'woodev-base-theme' )
		);

		$this->add_color(
			$wp_customize,
			'accent',
			'woodev_base_colors',
			__( 'Accent colour', 'woodev-base-theme' ),
			Settings::ACCENT_DEFAULT,
			Settings::sanitize_accent( ... ),
			__( 'Overrides the palette\'s accent. Leave empty to use the palette\'s own accent.', 'woodev-base-theme' )
		);

		$this->add_number(
			$wp_customize,
			'base_font_size',
			'woodev_base_typography',
			__( 'Base font size (px)', 'woodev-base-theme' ),
			Settings::BASE_FONT_SIZE_DEFAULT,
			Settings::BASE_FONT_SIZE_MIN,
			Settings::BASE_FONT_SIZE_MAX,
			Settings::sanitize_base_font_size( ... )
		);

		$this->add_select(
			$wp_customize,
			'font',
			'woodev_base_typography',
			__( 'Font', 'woodev-base-theme' ),
			[
				Settings::FONT_IDENTITY => __( 'Golos Text + IBM Plex (default)', 'woodev-base-theme' ),
				Settings::FONT_SYSTEM   => __( 'System font (no download)', 'woodev-base-theme' ),
			],
			Settings::FONT_DEFAULT,
			Settings::sanitize_font( ... ),
			__( 'System font fetches nothing: no request for the self-hosted webfont files is ever made.', 'woodev-base-theme' )
		);

		$this->add_number(
			$wp_customize,
			'container_width',
			'woodev_base_layout',
			__( 'Content width (px)', 'woodev-base-theme' ),
			Settings::CONTAINER_WIDTH_DEFAULT,
			Settings::CONTAINER_WIDTH_MIN,
			Settings::CONTAINER_WIDTH_MAX,
			Settings::sanitize_container_width( ... )
		);

		$this->add_number(
			$wp_customize,
			'radius',
			'woodev_base_layout',
			__( 'Corner rounding (px)', 'woodev-base-theme' ),
			Settings::RADIUS_DEFAULT,
			Settings::RADIUS_MIN,
			Settings::RADIUS_MAX,
			Settings::sanitize_radius( ... )
		);

		$this->add_select(
			$wp_customize,
			'sidebar_position',
			'woodev_base_layout',
			__( 'Sidebar', 'woodev-base-theme' ),
			[
				'none'  => __( 'No sidebar', 'woodev-base-theme' ),
				'right' => __( 'Right sidebar', 'woodev-base-theme' ),
			],
			'none',
			Layout::sanitize_sidebar_position( ... ),
			__( 'Shown on the blog, archives, search results and single posts, when the Sidebar widget area has widgets.', 'woodev-base-theme' )
		);

		$this->add_select(
			$wp_customize,
			'header_variant',
			'woodev_base_header',
			__( 'Header layout', 'woodev-base-theme' ),
			[
				'inline'   => __( 'Inline navigation', 'woodev-base-theme' ),
				'centered' => __( 'Centered', 'woodev-base-theme' ),
			],
			'inline',
			Layout::sanitize_header_variant( ... )
		);

		$this->add_select(
			$wp_customize,
			'footer_variant',
			'woodev_base_footer',
			__( 'Footer layout', 'woodev-base-theme' ),
			[
				'simple'  => __( 'Simple', 'woodev-base-theme' ),
				'columns' => __( 'Widget columns', 'woodev-base-theme' ),
			],
			'simple',
			Layout::sanitize_footer_variant( ... )
		);

		$this->add_select(
			$wp_customize,
			'cta_reveal',
			'woodev_base_shop',
			__( 'Add-to-cart reveal', 'woodev-base-theme' ),
			[
				Settings::CTA_REVEAL_HOVER  => __( 'On hover (default)', 'woodev-base-theme' ),
				Settings::CTA_REVEAL_ALWAYS => __( 'Always visible', 'woodev-base-theme' ),
			],
			Settings::CTA_REVEAL_DEFAULT,
			Settings::sanitize_cta_reveal( ... ),
			__( 'Whether the add-to-cart button on a product card appears only on hover/focus, or is always shown.', 'woodev-base-theme' )
		);

		// --- Product page (B9, docs/plans/2026-07-28-catalogue-and-product.md) ---

		$this->add_text(
			$wp_customize,
			'product_trust_badge_one',
			'woodev_base_shop',
			__( 'Product page trust badge 1', 'woodev-base-theme' ),
			Settings::PRODUCT_TRUST_BADGE_ONE_DEFAULT,
			Settings::sanitize_product_trust_badge_one( ... ),
			sprintf(
				/* translators: %s: comma-separated list of allowed icon slugs. */
				__( 'Shown on every product page. Formatted as "Text | icon" — the icon is optional and must be one of: %s. Leave empty to hide this badge.', 'woodev-base-theme' ),
				implode( ', ', Settings::FRONT_ICONS )
			)
		);

		$this->add_text(
			$wp_customize,
			'product_trust_badge_two',
			'woodev_base_shop',
			__( 'Product page trust badge 2', 'woodev-base-theme' ),
			Settings::PRODUCT_TRUST_BADGE_TWO_DEFAULT,
			Settings::sanitize_product_trust_badge_two( ... ),
			sprintf(
				/* translators: %s: comma-separated list of allowed icon slugs. */
				__( 'A second badge next to the first (e.g. a warranty line). Same "Text | icon" format. Leave empty to hide this badge.', 'woodev-base-theme' ),
				implode( ', ', Settings::FRONT_ICONS )
			)
		);

		// --- Cart & checkout (C10/K9, docs/plans/2026-07-28-cart-checkout-account.md) ---
		//
		// Both default to EMPTY and render nothing until an owner fills them in.
		// Settings::CART_SECURE_NOTE_DEFAULT's docblock has the reason: the
		// sentence the mockup writes is only true for some payment gateways, so
		// the theme must not make the claim on the store's behalf.

		$this->add_text(
			$wp_customize,
			'cart_secure_note',
			'woodev_base_shop',
			__( 'Cart reassurance note', 'woodev-base-theme' ),
			Settings::CART_SECURE_NOTE_DEFAULT,
			Settings::sanitize_cart_secure_note( ... ),
			sprintf(
				/* translators: %s: comma-separated list of allowed icon slugs. */
				__( 'Shown under the cart\'s checkout button. Formatted as "Text | icon" — the icon is optional and defaults to a padlock; it must be one of: %s. Leave empty to hide the line.', 'woodev-base-theme' ),
				implode( ', ', Settings::FRONT_ICONS )
			)
		);

		$this->add_text(
			$wp_customize,
			'checkout_secure_note',
			'woodev_base_shop',
			__( 'Checkout reassurance note', 'woodev-base-theme' ),
			Settings::CHECKOUT_SECURE_NOTE_DEFAULT,
			Settings::sanitize_checkout_secure_note( ... ),
			sprintf(
				/* translators: %s: comma-separated list of allowed icon slugs. */
				__( 'Shown under the checkout\'s place-order button. Same "Text | icon" format, same padlock default. Leave empty to hide the line.', 'woodev-base-theme' ),
				implode( ', ', Settings::FRONT_ICONS )
			)
		);

		// --- Front page (F2, docs/plans/2026-07-28-front-page-completion.md) ---

		$this->add_section( $wp_customize, 'woodev_base_front', __( 'Front page', 'woodev-base-theme' ), 45 );

		$this->add_text(
			$wp_customize,
			'front_hero_eyebrow',
			'woodev_base_front',
			__( 'Hero eyebrow', 'woodev-base-theme' ),
			Settings::FRONT_HERO_EYEBROW_DEFAULT,
			Settings::sanitize_front_hero_eyebrow( ... ),
			__( 'One short line shown above the hero headline. Leave empty to hide it.', 'woodev-base-theme' )
		);

		$this->add_textarea(
			$wp_customize,
			'front_hero_lede',
			'woodev_base_front',
			__( 'Hero subtitle', 'woodev-base-theme' ),
			Settings::FRONT_HERO_LEDE_DEFAULT,
			Settings::sanitize_front_hero_lede( ... ),
			__( 'Shown under the hero headline. Leave empty to use the site tagline instead.', 'woodev-base-theme' )
		);

		$this->add_textarea(
			$wp_customize,
			'front_hero_trust',
			'woodev_base_front',
			__( 'Hero trust badges', 'woodev-base-theme' ),
			Settings::FRONT_HERO_TRUST_DEFAULT,
			Settings::sanitize_front_hero_trust( ... ),
			sprintf(
				/* translators: %s: comma-separated list of allowed icon slugs. */
				__( 'Up to three badges, one per line, formatted as "Text | icon". The icon is optional and must be one of: %s. Any other value, or a missing icon, falls back to "check".', 'woodev-base-theme' ),
				implode( ', ', Settings::FRONT_ICONS )
			)
		);

		$this->add_select(
			$wp_customize,
			'front_hero_art',
			'woodev_base_front',
			__( 'Hero art column', 'woodev-base-theme' ),
			[
				Settings::FRONT_HERO_ART_AUTO => __( 'Auto (featured image, or a themed illustration)', 'woodev-base-theme' ),
				Settings::FRONT_HERO_ART_OFF  => __( 'Off (no art column)', 'woodev-base-theme' ),
			],
			Settings::FRONT_HERO_ART_DEFAULT,
			Settings::sanitize_front_hero_art( ... ),
			__( 'Auto shows the front page\'s featured image, or a themed illustration when it has none. Off removes the art column entirely and the hero renders single-column.', 'woodev-base-theme' )
		);

		$this->add_textarea(
			$wp_customize,
			'front_value_items',
			'woodev_base_front',
			__( 'Value band items', 'woodev-base-theme' ),
			Settings::FRONT_VALUE_ITEMS_DEFAULT,
			Settings::sanitize_front_value_items( ... ),
			sprintf(
				/* translators: %s: comma-separated list of allowed icon slugs. */
				__( 'Up to four items, one per line, formatted as "Title | Text | icon". The icon is optional and must be one of: %s. Any other value, or a missing icon, falls back to "check". A line with no title is skipped.', 'woodev-base-theme' ),
				implode( ', ', Settings::FRONT_ICONS )
			)
		);

		$this->add_text(
			$wp_customize,
			'front_promo_title',
			'woodev_base_front',
			__( 'Promo heading', 'woodev-base-theme' ),
			Settings::FRONT_PROMO_TITLE_DEFAULT,
			Settings::sanitize_front_promo_title( ... ),
			__( 'Heading for the promo section. Leave empty to hide the whole section.', 'woodev-base-theme' )
		);

		$this->add_textarea(
			$wp_customize,
			'front_promo_text',
			'woodev_base_front',
			__( 'Promo text', 'woodev-base-theme' ),
			Settings::FRONT_PROMO_TEXT_DEFAULT,
			Settings::sanitize_front_promo_text( ... ),
			__( 'Body copy for the promo section. Plain text only, no HTML.', 'woodev-base-theme' )
		);

		$this->add_text(
			$wp_customize,
			'front_promo_cta_label',
			'woodev_base_front',
			__( 'Promo button label', 'woodev-base-theme' ),
			Settings::FRONT_PROMO_CTA_LABEL_DEFAULT,
			Settings::sanitize_front_promo_cta_label( ... ),
			__( 'Text for the promo button. The button only renders when both a label and a URL are set.', 'woodev-base-theme' )
		);

		$this->add_text(
			$wp_customize,
			'front_promo_cta_url',
			'woodev_base_front',
			__( 'Promo button URL', 'woodev-base-theme' ),
			Settings::FRONT_PROMO_CTA_URL_DEFAULT,
			Settings::sanitize_front_promo_cta_url( ... ),
			__( 'Where the promo button links to. The button only renders when both a label and a URL are set.', 'woodev-base-theme' ),
			'url'
		);

		$this->add_media(
			$wp_customize,
			'front_promo_image',
			'woodev_base_front',
			__( 'Promo image', 'woodev-base-theme' ),
			Settings::FRONT_PROMO_IMAGE_DEFAULT,
			Settings::sanitize_front_promo_image( ... ),
			__( 'Image shown beside the promo text. Leave empty to use a themed illustration instead.', 'woodev-base-theme' )
		);
	}

	/**
	 * The translated label for every palette this theme ships.
	 *
	 * Public and static so the contract "every shipped palette has an explicit,
	 * translatable label" can be asserted directly. It cannot be asserted
	 * through palette_choices(): the human labels are, by design, exactly the
	 * title-cased slugs, so a derived fallback and a hand-written label are
	 * indistinguishable in the rendered output — a test comparing the two
	 * strings proves nothing.
	 *
	 * @return array<string, string>
	 */
	public static function palette_labels(): array {
		return [
			'warm-clay'    => __( 'Warm Clay', 'woodev-base-theme' ),
			'cold-petrol'  => __( 'Cold Petrol', 'woodev-base-theme' ),
			'graphite'     => __( 'Graphite', 'woodev-base-theme' ),
			'forest'       => __( 'Forest', 'woodev-base-theme' ),
			'sand'         => __( 'Sand', 'woodev-base-theme' ),
			'wine'         => __( 'Wine', 'woodev-base-theme' ),
			'night-indigo' => __( 'Night Indigo', 'woodev-base-theme' ),
		];
	}

	/**
	 * Palette choices for the `palette` control: slug => translated label,
	 * restricted to whatever Palettes::slugs() actually returns.
	 *
	 * A malformed inc/generated/palettes.php degrades Palettes::slugs() down
	 * to just PALETTE_DEFAULT (see Palettes) — the control must offer exactly
	 * that narrowed set, or it would let the admin pick a slug the renderer
	 * has already stopped supporting.
	 *
	 * @return array<string, string>
	 */
	private function palette_choices(): array {
		$labels  = self::palette_labels();
		$choices = [];

		foreach ( Palettes::slugs() as $slug ) {
			// A runtime safety net, not an accepted state: it fires only when
			// src/tokens/tokens.mjs grew a palette that palette_labels() has
			// never heard of, and a unit test goes red the moment that is true.
			// Without that test the fallback is silent — nothing throws, nothing
			// logs, and a Russian-locale admin just sees one English word among
			// seven translated ones.
			$choices[ $slug ] = $labels[ $slug ] ?? ucwords( str_replace( '-', ' ', $slug ) );
		}

		return $choices;
	}

	/**
	 * Register one Customizer section.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer manager.
	 * @param string                $id           Section id.
	 * @param string                $title        Section title.
	 * @param int                   $priority     Section priority.
	 */
	private function add_section( \WP_Customize_Manager $wp_customize, string $id, string $title, int $priority ): void {
		$wp_customize->add_section(
			$id,
			[
				'title'    => $title,
				'priority' => $priority,
			]
		);
	}

	/**
	 * Register a select-type setting and its control.
	 *
	 * @param \WP_Customize_Manager $wp_customize  Customizer manager.
	 * @param string                $id            Setting id.
	 * @param string                $section       Section id.
	 * @param string                $label         Control label.
	 * @param array<string, string> $choices       Value => label.
	 * @param string                $default_value Default value.
	 * @param callable              $sanitize      Sanitize callback.
	 * @param string                $description   Optional control description.
	 */
	private function add_select( \WP_Customize_Manager $wp_customize, string $id, string $section, string $label, array $choices, string $default_value, callable $sanitize, string $description = '' ): void {
		$wp_customize->add_setting(
			$id,
			[
				'default'           => $default_value,
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			]
		);

		$wp_customize->add_control(
			$id,
			[
				'label'       => $label,
				'description' => $description,
				'section'     => $section,
				'type'        => 'select',
				'choices'     => $choices,
			]
		);
	}

	/**
	 * Register a checkbox-type setting and its control.
	 *
	 * @param \WP_Customize_Manager $wp_customize  Customizer manager.
	 * @param string                $id            Setting id.
	 * @param string                $section       Section id.
	 * @param string                $label         Control label.
	 * @param bool                  $default_value Default value.
	 * @param callable              $sanitize      Sanitize callback.
	 * @param string                $description   Optional control description.
	 */
	private function add_checkbox( \WP_Customize_Manager $wp_customize, string $id, string $section, string $label, bool $default_value, callable $sanitize, string $description = '' ): void {
		$wp_customize->add_setting(
			$id,
			[
				'default'           => $default_value,
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			]
		);

		$wp_customize->add_control(
			$id,
			[
				'label'       => $label,
				'description' => $description,
				'section'     => $section,
				'type'        => 'checkbox',
			]
		);
	}

	/**
	 * Register a number-type setting and its control.
	 *
	 * @param \WP_Customize_Manager $wp_customize  Customizer manager.
	 * @param string                $id            Setting id.
	 * @param string                $section       Section id.
	 * @param string                $label         Control label.
	 * @param int                   $default_value Default value.
	 * @param int                   $min           Lower bound.
	 * @param int                   $max           Upper bound.
	 * @param callable              $sanitize      Sanitize callback.
	 */
	private function add_number( \WP_Customize_Manager $wp_customize, string $id, string $section, string $label, int $default_value, int $min, int $max, callable $sanitize ): void {
		$wp_customize->add_setting(
			$id,
			[
				'default'           => $default_value,
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			]
		);

		$wp_customize->add_control(
			$id,
			[
				'label'       => $label,
				'section'     => $section,
				'type'        => 'number',
				'input_attrs' => [
					'min'  => $min,
					'max'  => $max,
					'step' => 1,
				],
			]
		);
	}

	/**
	 * Register a colour-picker setting and its control.
	 *
	 * WP_Customize_Color_Control is the real class WordPress core registers
	 * for its colour-picker JS/UI; it is only autoloadable when WordPress'
	 * own customize-controls machinery has loaded, which the Brain\Monkey
	 * unit suite never does (no WP code runs there at all — see
	 * CustomizerTest's own note on why WP_Customize_Manager itself is a
	 * Mockery double in that suite). class_exists() picks a plain control as
	 * a fallback there; the integration suite runs under real WordPress,
	 * where the class always exists and the picker renders for real.
	 *
	 * @param \WP_Customize_Manager $wp_customize  Customizer manager.
	 * @param string                $id            Setting id.
	 * @param string                $section       Section id.
	 * @param string                $label         Control label.
	 * @param string                $default_value Default value.
	 * @param callable              $sanitize      Sanitize callback.
	 * @param string                $description   Optional control description.
	 */
	private function add_color( \WP_Customize_Manager $wp_customize, string $id, string $section, string $label, string $default_value, callable $sanitize, string $description = '' ): void {
		$wp_customize->add_setting(
			$id,
			[
				'default'           => $default_value,
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			]
		);

		if ( class_exists( \WP_Customize_Color_Control::class ) ) {
			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					$id,
					[
						'label'       => $label,
						'description' => $description,
						'section'     => $section,
					]
				)
			);

			return;
		}

		$wp_customize->add_control(
			$id,
			[
				'label'       => $label,
				'description' => $description,
				'section'     => $section,
				'type'        => 'color',
			]
		);
	}

	/**
	 * Register a textarea-type setting and its control.
	 *
	 * @param \WP_Customize_Manager $wp_customize  Customizer manager.
	 * @param string                $id            Setting id.
	 * @param string                $section       Section id.
	 * @param string                $label         Control label.
	 * @param string                $default_value Default value.
	 * @param callable              $sanitize      Sanitize callback.
	 * @param string                $description   Optional control description.
	 */
	private function add_textarea( \WP_Customize_Manager $wp_customize, string $id, string $section, string $label, string $default_value, callable $sanitize, string $description = '' ): void {
		$wp_customize->add_setting(
			$id,
			[
				'default'           => $default_value,
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			]
		);

		$wp_customize->add_control(
			$id,
			[
				'label'       => $label,
				'description' => $description,
				'section'     => $section,
				'type'        => 'textarea',
			]
		);
	}

	/**
	 * Register a single-line text setting and its control.
	 *
	 * Separate from add_textarea() for the admin's sake, not the data's:
	 * sanitize_text_field() collapses newlines either way, so a one-line
	 * value stored through a `<textarea>` comes out identical. What differs
	 * is what the control invites — a multi-line box for a button label
	 * suggests a paragraph belongs there, and Enter inside it inserts a
	 * newline the sanitizer then silently eats. The URL field takes
	 * `type => 'url'` so the browser's own keyboard and validation apply;
	 * it is still sanitized server-side by esc_url_raw(), which is what
	 * actually enforces the scheme.
	 *
	 * @param \WP_Customize_Manager $wp_customize  Customizer manager.
	 * @param string                $id            Setting id.
	 * @param string                $section       Section id.
	 * @param string                $label         Control label.
	 * @param string                $default_value Default value.
	 * @param callable              $sanitize      Sanitize callback.
	 * @param string                $description   Optional control description.
	 * @param string                $type          Input type: 'text' or 'url'.
	 */
	private function add_text( \WP_Customize_Manager $wp_customize, string $id, string $section, string $label, string $default_value, callable $sanitize, string $description = '', string $type = 'text' ): void {
		$wp_customize->add_setting(
			$id,
			[
				'default'           => $default_value,
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			]
		);

		$wp_customize->add_control(
			$id,
			[
				'label'       => $label,
				'description' => $description,
				'section'     => $section,
				'type'        => $type,
			]
		);
	}

	/**
	 * Register a media (image) setting and its control.
	 *
	 * WP_Customize_Media_Control is the real class WordPress core registers
	 * for its media-library picker UI; it is only autoloadable when
	 * WordPress' own customize-controls machinery has loaded, which the
	 * Brain\Monkey unit suite never does (no WP code runs there at all — see
	 * add_color()'s note on the same pattern). class_exists() picks a plain
	 * number control as a fallback there; the integration suite runs under
	 * real WordPress, where the class always exists and the media picker
	 * renders for real.
	 *
	 * @param \WP_Customize_Manager $wp_customize  Customizer manager.
	 * @param string                $id            Setting id.
	 * @param string                $section       Section id.
	 * @param string                $label         Control label.
	 * @param int                   $default_value Default value (attachment ID, 0 = none).
	 * @param callable              $sanitize      Sanitize callback.
	 * @param string                $description   Optional control description.
	 */
	private function add_media( \WP_Customize_Manager $wp_customize, string $id, string $section, string $label, int $default_value, callable $sanitize, string $description = '' ): void {
		$wp_customize->add_setting(
			$id,
			[
				'default'           => $default_value,
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			]
		);

		if ( class_exists( \WP_Customize_Media_Control::class ) ) {
			$wp_customize->add_control(
				new \WP_Customize_Media_Control(
					$wp_customize,
					$id,
					[
						'label'       => $label,
						'description' => $description,
						'section'     => $section,
						'mime_type'   => 'image',
					]
				)
			);

			return;
		}

		$wp_customize->add_control(
			$id,
			[
				'label'       => $label,
				'description' => $description,
				'section'     => $section,
				'type'        => 'number',
			]
		);
	}
}
