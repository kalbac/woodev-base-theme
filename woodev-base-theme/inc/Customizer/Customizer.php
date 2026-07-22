<?php
/**
 * Customizer registration (spec §6).
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Customizer;

use Woodev\Theme\Base\Scheme;
use Woodev\Theme\Base\StylePreset;
use Woodev\Theme\Base\Templates\Layout;

/**
 * Registers the v1 sections, settings and controls.
 *
 * Every sanitize_callback here is the same validator the front end resolves
 * with (Layout, StylePreset, Settings), so the Customizer cannot store a value
 * a template or the inline stylesheet would then reject.
 */
final class Customizer {

	/**
	 * Labels for the generated accent presets.
	 *
	 * The slugs come from the generated map; only the human-readable side lives
	 * here, because a generated file cannot carry translator context — a
	 * `__()` call needs a literal string in the source for the .pot scanner to
	 * find it.
	 *
	 * A generated slug with no label here is therefore NOT offered at all. The
	 * tempting fallback, `ucfirst( $slug )`, would put an untranslated string in
	 * front of the admin — silently, and in a theme whose i18n policy is
	 * absolute. Failing closed means the worst case of forgetting a label is a
	 * missing choice (loud, and caught by CustomizerTest against the real
	 * generated map) rather than an English word leaking into every locale.
	 *
	 * @return array<string, string>
	 */
	private function primary_preset_choices(): array {
		$labels = [
			'default' => __( 'Inherit style pack', 'woodev-base-theme' ),
			'neutral' => __( 'Neutral', 'woodev-base-theme' ),
			'blue'    => __( 'Blue', 'woodev-base-theme' ),
			'green'   => __( 'Green', 'woodev-base-theme' ),
			'red'     => __( 'Red', 'woodev-base-theme' ),
			'rose'    => __( 'Rose', 'woodev-base-theme' ),
			'orange'  => __( 'Orange', 'woodev-base-theme' ),
			'yellow'  => __( 'Yellow', 'woodev-base-theme' ),
			'violet'  => __( 'Violet', 'woodev-base-theme' ),
		];

		$choices = [ Settings::PRIMARY_PRESET_DEFAULT => $labels['default'] ];

		foreach ( array_keys( Settings::presets() ) as $slug ) {
			if ( isset( $labels[ $slug ] ) ) {
				$choices[ $slug ] = $labels[ $slug ];
			}
		}

		return $choices;
	}

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

		$this->add_select(
			$wp_customize,
			'style_preset',
			'woodev_base_colors',
			__( 'Style pack', 'woodev-base-theme' ),
			StylePreset::choices(),
			StylePreset::default()->value,
			StylePreset::sanitize( ... ),
			__( 'Basecoat visual style. Packs change component shape and density, not the palette.', 'woodev-base-theme' )
		);

		$this->add_select(
			$wp_customize,
			'primary_preset',
			'woodev_base_colors',
			__( 'Accent color', 'woodev-base-theme' ),
			$this->primary_preset_choices(),
			Settings::PRIMARY_PRESET_DEFAULT,
			Settings::sanitize_primary_preset( ... ),
			__( 'Applies on top of the style pack, in both light and dark schemes.', 'woodev-base-theme' )
		);

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

		$this->add_select(
			$wp_customize,
			'radius_scale',
			'woodev_base_layout',
			__( 'Corner rounding', 'woodev-base-theme' ),
			[
				'none' => __( 'Square', 'woodev-base-theme' ),
				'sm'   => __( 'Small', 'woodev-base-theme' ),
				'md'   => __( 'Medium', 'woodev-base-theme' ),
				'lg'   => __( 'Large', 'woodev-base-theme' ),
			],
			Settings::RADIUS_DEFAULT,
			Settings::sanitize_radius_scale( ... )
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
}
