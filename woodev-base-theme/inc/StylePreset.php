<?php
/**
 * The Basecoat style pack selected for the front end.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

/**
 * The eight standalone Basecoat visual style packs (spec §6).
 *
 * Basecoat forbids combining packs, so the build emits one standalone CSS bundle
 * per case and Assets enqueues exactly the one this resolves to. M1-04 adds the
 * Customizer control that writes `style_preset`; this enum is the single place
 * that validates the stored value and maps it to a Vite build entry.
 */
enum StylePreset: string {

	case Vega = 'vega';
	case Nova = 'nova';
	case Maia = 'maia';
	case Lyra = 'lyra';
	case Mira = 'mira';
	case Luma = 'luma';
	case Sera = 'sera';
	case Rhea = 'rhea';

	/**
	 * Basecoat's default pack — the theme's default when the setting is unset or
	 * holds a value that is no longer valid.
	 */
	public static function default(): self {
		return self::Vega;
	}

	/**
	 * Resolve the admin-chosen pack from its theme_mod, validated.
	 */
	public static function from_theme_mod(): self {
		return self::from( self::sanitize( get_theme_mod( 'style_preset', self::default()->value ) ) );
	}

	/**
	 * Customizer sanitize callback for `style_preset`.
	 *
	 * Calling get_theme_mod() returns mixed. The value lives in the database and
	 * can be reshaped by a `theme_mod_style_preset` filter or a half-migrated
	 * option, so a non-string is reachable. Casting first would emit "Array to
	 * string conversion" for an array and throw Error for an object without
	 * __toString() — a fatal on every front-end request, because this runs on
	 * wp_enqueue_scripts. Fail closed to the default pack instead.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize( mixed $value ): string {
		if ( ! \is_string( $value ) ) {
			return self::default()->value;
		}

		return ( self::tryFrom( $value ) ?? self::default() )->value;
	}

	/**
	 * Slug => label map for the Customizer select.
	 *
	 * Labels are the pack names Basecoat ships, so they are proper nouns and
	 * deliberately not translated.
	 *
	 * @return array<string, string>
	 */
	public static function choices(): array {
		$choices = [];

		foreach ( self::cases() as $case ) {
			$choices[ $case->value ] = ucfirst( $case->value );
		}

		return $choices;
	}

	/**
	 * The Vite manifest key (and dev-server path) for this pack's CSS bundle.
	 */
	public function css_entry(): string {
		return "src/css/packs/{$this->value}.css";
	}
}
