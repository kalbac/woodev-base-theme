<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Customizer;

use PHPUnit\Framework\TestCase;
use Woodev\Theme\Base\Customizer\ColorConverter;

/**
 * ColorConverter is pure math with no WordPress dependency, so this extends
 * PHPUnit's own TestCase directly rather than the Brain\Monkey base — there
 * is nothing to mock.
 */
final class ColorConverterTest extends TestCase {

	/**
	 * A pure grey has no hue: a/b both round to 0, so atan2() is at the
	 * mercy of floating-point noise. The converter must canonicalise to hue
	 * '0' rather than emit whatever the noise produced — #808080 and a
	 * near-grey one bit off both exercise the boundary, and the two must NOT
	 * agree on a garbage hue by coincidence (they don't: near-grey below has
	 * a real, if tiny, chroma and hue).
	 */
	public function test_pure_grey_gets_a_canonical_hue_not_floating_point_noise(): void {
		self::assertSame(
			[
				'h' => '0',
				'c' => '0',
			],
			ColorConverter::to_oklch( '#808080' )
		);
		self::assertSame(
			[
				'h' => '0',
				'c' => '0',
			],
			ColorConverter::to_oklch( '808080' )
		);
	}

	public function test_pure_black_and_white_are_grey(): void {
		self::assertSame(
			[
				'h' => '0',
				'c' => '0',
			],
			ColorConverter::to_oklch( '#000000' )
		);
		self::assertSame(
			[
				'h' => '0',
				'c' => '0',
			],
			ColorConverter::to_oklch( '#ffffff' )
		);
	}

	/**
	 * A hex one bit off pure grey has a real, tiny, non-canonical hue and
	 * chroma — proving the chroma<=0 branch is a genuine boundary check, not
	 * a blanket "small chroma" rule that would also swallow this case.
	 */
	public function test_a_near_grey_one_bit_off_keeps_its_tiny_real_hue(): void {
		$result = ColorConverter::to_oklch( '#7f8081' );

		self::assertNotNull( $result );
		self::assertNotSame( '0', $result['c'] );
	}

	/**
	 * 3-digit shorthand expands the same way CSS does (#abc == #aabbcc).
	 */
	public function test_three_digit_shorthand_expands_like_css(): void {
		self::assertSame( ColorConverter::to_oklch( '#ff0000' ), ColorConverter::to_oklch( '#f00' ) );
	}

	/**
	 * Saturated primaries sit at or near the edge of the sRGB gamut and
	 * carry far more chroma than any shipped palette (0.024-0.130) — the
	 * converter must not crash or emit NAN/INF on them, and the hue must
	 * land in the quadrant a human would expect. Reference values computed
	 * independently with the same published (Ottosson) formulas.
	 */
	public function test_saturated_primaries_do_not_crash_and_land_in_the_right_quadrant(): void {
		$red = ColorConverter::to_oklch( '#ff0000' );
		self::assertSame( '29', $red['h'] );
		self::assertSame( '0.258', $red['c'] );

		$green = ColorConverter::to_oklch( '#00ff00' );
		self::assertSame( '142', $green['h'] );
		self::assertSame( '0.295', $green['c'] );

		$blue = ColorConverter::to_oklch( '#0000ff' );
		self::assertSame( '264', $blue['h'] );
		self::assertSame( '0.313', $blue['c'] );
	}

	/**
	 * Round-trip against two of the shipped palettes' known accent values
	 * (inc/generated/palettes.php): warm-clay (accent-h=40, accent-c=0.088)
	 * and night-indigo (accent-h=274, accent-c=0.130). The fixture hexes
	 * below are oklch(47%, C, H) for each pair, rendered to sRGB with the
	 * same published formulas at a lightness that keeps the colour inside
	 * the sRGB gamut (L=0.47 is in-gamut for both of these two; it is NOT
	 * for cold-petrol, which is why that palette is not used here — see the
	 * class docblock on why lightness is not part of the accent tuple at
	 * all, so the choice of L for this fixture is arbitrary and only needs
	 * to keep the round-trip observable).
	 */
	public function test_round_trips_against_two_shipped_palettes_known_values(): void {
		$warm_clay = ColorConverter::to_oklch( '#844833' );
		self::assertSame( '40', $warm_clay['h'] );
		self::assertSame( '0.088', $warm_clay['c'] );

		$night_indigo = ColorConverter::to_oklch( '#4652a3' );
		self::assertSame( '274', $night_indigo['h'] );
		self::assertSame( '0.130', $night_indigo['c'] );
	}

	/**
	 * @return list<array{0: string}>
	 */
	public static function malformed_hex_values(): array {
		return [
			'empty string'                 => [ '' ],
			'not a colour word'            => [ 'red' ],
			'css function'                 => [ 'rgb(0,0,0)' ],
			'five digits'                  => [ '#12345' ],
			'seven digits'                 => [ '#1234567' ],
			'non-hex letters'              => [ '#gggggg' ],
			'missing digits, bare hash'    => [ '#' ],
			'css injection attempt'        => [ '#fff;}body{display:none}' ],
			'javascript scheme'            => [ 'javascript:alert(1)' ],
			'whitespace only'              => [ '   ' ],
			'hex with internal whitespace' => [ '#ff 000' ],
			// A NUL in the middle of the digits, not trailing: trim() strips
			// NUL from the ENDS by default (its charlist is " \t\n\r\0\x0B"),
			// so a trailing "#fff\0" would trim down to the valid "#fff" and
			// this would stop being a malformed-input case at all. An
			// embedded NUL survives trim() and is correctly rejected by the
			// hex-digit character class instead.
			'embedded null byte'           => [ "#f\0f000" ],
		];
	}

	/**
	 * @dataProvider malformed_hex_values
	 */
	public function test_malformed_input_returns_null_not_a_crash( string $hex ): void {
		self::assertNull( ColorConverter::to_oklch( $hex ) );
	}

	/**
	 * Leading/trailing whitespace around an otherwise valid hex is trimmed,
	 * unlike whitespace INSIDE the digits (test_malformed_input_returns_
	 * null_not_a_crash's 'hex with internal whitespace' case).
	 */
	public function test_surrounding_whitespace_is_trimmed(): void {
		self::assertSame( ColorConverter::to_oklch( '#ff0000' ), ColorConverter::to_oklch( '  #ff0000  ' ) );
	}

	public function test_case_insensitive(): void {
		self::assertSame( ColorConverter::to_oklch( '#ABCDEF' ), ColorConverter::to_oklch( '#abcdef' ) );
	}
}
