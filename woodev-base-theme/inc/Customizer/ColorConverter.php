<?php
/**
 * Converts a hex colour (sRGB) to OKLCH hue/chroma for the accent Customizer control.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Customizer;

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

/**
 * Pure conversion, no WordPress dependency: implements Björn Ottosson's
 * sRGB -> OKLab -> OKLCH formulas (https://bottosson.github.io/posts/oklab/).
 *
 * The theme's palette architecture (ADR-008) carries an accent as exactly two
 * numbers, --accent-h and --accent-c — lightness is fixed per surface and
 * colour scheme by the token formulas (e.g. `oklch(47% var(--accent-c)
 * var(--accent-h))` in light, 72% in dark), never chosen by the admin. This
 * converter therefore computes and returns hue + chroma only; the OKLab
 * lightness the maths produces along the way is discarded on purpose.
 */
final class ColorConverter {

	/**
	 * Convert a hex colour to the --accent-h / --accent-c pair.
	 *
	 * @param string $hex A 3- or 6-digit hex colour, with or without a
	 *                     leading '#'. Expected to already be normalized by
	 *                     Settings::sanitize_accent(), but re-validated here
	 *                     too — this class has no other caller today, but it
	 *                     is deliberately independent of that assumption.
	 * @return array{h: string, c: string}|null Null when $hex does not parse
	 *         as a hex colour.
	 */
	public static function to_oklch( string $hex ): ?array {
		$rgb = self::parse_hex( $hex );

		if ( null === $rgb ) {
			return null;
		}

		[ $red, $green, $blue ] = $rgb;

		$linear_red   = self::to_linear( $red );
		$linear_green = self::to_linear( $green );
		$linear_blue  = self::to_linear( $blue );

		// sRGB (linear) -> LMS. Coefficients are Ottosson's published matrix;
		// all three are non-negative and sum to ~1, so l/m/s stay >= 0 for any
		// in-gamut r/g/b in [0, 1] and the cube root below never sees a
		// negative argument on this path.
		$l = 0.4122214708 * $linear_red + 0.5363325363 * $linear_green + 0.0514459929 * $linear_blue;
		$m = 0.2119034982 * $linear_red + 0.6806995451 * $linear_green + 0.1073969566 * $linear_blue;
		$s = 0.0883024619 * $linear_red + 0.2817188376 * $linear_green + 0.6299787005 * $linear_blue;

		$l_cbrt = self::cbrt( $l );
		$m_cbrt = self::cbrt( $m );
		$s_cbrt = self::cbrt( $s );

		// LMS' -> OKLab a/b (OKLab L is computed by the same matrix but is
		// never used — see the class docblock).
		$a = 1.9779984951 * $l_cbrt - 2.4285922050 * $m_cbrt + 0.4505937099 * $s_cbrt;
		$b = 0.0259040371 * $l_cbrt + 0.7827717662 * $m_cbrt - 0.8086757660 * $s_cbrt;

		$chroma = round( sqrt( $a * $a + $b * $b ), 3 );

		// Chroma 0 (a grey) has no hue: a/b are both ~0, so atan2() is at the
		// mercy of floating-point noise (e.g. #808080 round-trips to a hue
		// near 90 for no reason a human picked). Canonicalise instead of
		// emitting whatever the noise produced — a real accent color never
		// rounds to 0 chroma (the shipped palettes range 0.024-0.130), so
		// this only ever fires for a deliberately achromatic pick, where any
		// hue renders identically anyway.
		if ( $chroma <= 0.0 ) {
			return [
				'h' => '0',
				'c' => '0',
			];
		}

		$hue = atan2( $b, $a ) * 180 / M_PI;

		if ( $hue < 0 ) {
			$hue += 360;
		}

		return [
			'h' => (string) (int) round( $hue ),
			'c' => number_format( $chroma, 3, '.', '' ),
		];
	}

	/**
	 * Parse a hex colour string into its RGB channels.
	 *
	 * @param string $hex Candidate hex colour.
	 * @return array{0: int, 1: int, 2: int}|null RGB channels 0-255, or null
	 *         when $hex is not a 3- or 6-digit hex colour.
	 */
	private static function parse_hex( string $hex ): ?array {
		$hex = trim( $hex );

		if ( 1 !== preg_match( '/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex, $matches ) ) {
			return null;
		}

		$digits = $matches[1];

		if ( 3 === strlen( $digits ) ) {
			$digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
		}

		return [
			(int) hexdec( substr( $digits, 0, 2 ) ),
			(int) hexdec( substr( $digits, 2, 2 ) ),
			(int) hexdec( substr( $digits, 4, 2 ) ),
		];
	}

	/**
	 * Applies sRGB companding: an integer channel 0-255 to a linear value 0.0-1.0.
	 *
	 * @param int $channel 0-255.
	 */
	private static function to_linear( int $channel ): float {
		$normalized = $channel / 255;

		return $normalized <= 0.04045
			? $normalized / 12.92
			: ( ( $normalized + 0.055 ) / 1.055 ) ** 2.4;
	}

	/**
	 * A real cube root for any float, including negative ones.
	 *
	 * PHP's ** operator raises a negative base to a fractional exponent as
	 * NAN (it does not fall through to a real root the way C's cbrt() does).
	 * Never exercised on the current sRGB -> LMS path (see to_oklch()'s
	 * comment on the matrix), but OKLab's l_/m_/s_ terms are negative for
	 * some inputs in the general case, so this guards a correctness property
	 * of the formula rather than a scenario reachable through this class's
	 * only entry point today.
	 *
	 * @param float $value Candidate value, positive or negative.
	 */
	private static function cbrt( float $value ): float {
		return $value < 0 ? -( ( -$value ) ** ( 1 / 3 ) ) : $value ** ( 1 / 3 );
	}
}
