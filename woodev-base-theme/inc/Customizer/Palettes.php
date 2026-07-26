<?php
/**
 * Validated access to the generated colour palettes.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Customizer;

/**
 * The generated inc/generated/palettes.php is our own build artifact (`npm run tokens`),
 * but it is also the point where three bare numbers per palette enter a
 * <style> block by way of InlineStyles — so it is re-validated here on every
 * read, exactly like Icons.php re-validates a vendored SVG and Assets.php
 * re-validates a Vite manifest: absent, unreadable, syntactically broken, or
 * malformed entry-by-entry all degrade to a working front end instead of a
 * fatal error.
 *
 * `warm-clay` is the one entry the theme cannot do without: it is
 * Settings::PALETTE_DEFAULT, and it equals the :root defaults, so as long as
 * IT resolves, a fully corrupted generated file still renders correctly —
 * selecting the default emits no CSS override at all (InlineStyles). This
 * class therefore guarantees warm-clay is always present in the map it
 * returns, synthesising it from FALLBACK_WARM_CLAY when the generated file
 * does not supply a sound one.
 */
final class Palettes {

	/**
	 * Warm-clay's values, verbatim from src/tokens/tokens.mjs `base` — the
	 * same numbers the :root defaults already carry. Used only when the
	 * generated file is missing, unreadable, malformed, or omits warm-clay.
	 *
	 * @var array{n-h: string, accent-h: string, accent-c: string}
	 */
	private const FALLBACK_WARM_CLAY = [
		'n-h'      => '68',
		'accent-h' => '40',
		'accent-c' => '0.088',
	];

	private const DEFAULT_SLUG = 'warm-clay';

	/**
	 * A bare, optionally negative, optionally decimal number — never a CSS
	 * function, unit, or statement terminator. The one shape a --n-h,
	 * --accent-h or --accent-c value is allowed to have before it reaches a
	 * <style> block.
	 */
	private const VALUE_PATTERN = '/^-?[0-9]+(?:\.[0-9]+)?$/';

	/**
	 * Loaded and validated palettes, memoised per resolved path for the
	 * request. Keyed by path rather than unconditionally, for the same
	 * reason as Icons::$cache: a long-running worker (FrankenPHP, Swoole, a
	 * multisite process switching themes) can outlive the request that first
	 * populated it, and get_template_directory() is part of what identifies
	 * which file this data came from.
	 *
	 * @var array<string, array<string, array{n-h: string, accent-h: string, accent-c: string}>>
	 */
	private static array $cache = [];

	/**
	 * The full set of usable palettes: slug => { n-h, accent-h, accent-c }.
	 * Always includes DEFAULT_SLUG.
	 *
	 * @return array<string, array{n-h: string, accent-h: string, accent-c: string}>
	 */
	public static function all(): array {
		$path = get_template_directory() . '/inc/generated/palettes.php';

		if ( ! isset( self::$cache[ $path ] ) ) {
			self::$cache[ $path ] = self::load( $path );
		}

		return self::$cache[ $path ];
	}

	/**
	 * The valid palette slugs, in the order the generated file declares them.
	 *
	 * @return list<string>
	 */
	public static function slugs(): array {
		return array_keys( self::all() );
	}

	/**
	 * One palette's tuple. Falls back to warm-clay's values for an unknown
	 * slug rather than an empty/partial array — a caller that already
	 * validated $slug against slugs() never hits the fallback; one that did
	 * not gets the safe default instead of undefined-index CSS.
	 *
	 * @param string $slug Candidate palette slug.
	 * @return array{n-h: string, accent-h: string, accent-c: string}
	 */
	public static function get( string $slug ): array {
		return self::all()[ $slug ] ?? self::FALLBACK_WARM_CLAY;
	}

	/**
	 * Load and validate the generated file at $path.
	 *
	 * @param string $path Absolute path to the generated palettes file.
	 * @return array<string, array{n-h: string, accent-h: string, accent-c: string}>
	 */
	private static function load( string $path ): array {
		$raw = self::require_file( $path );

		$sound = [];

		if ( is_array( $raw ) ) {
			foreach ( $raw as $slug => $tuple ) {
				if ( is_string( $slug ) && self::is_sound_tuple( $tuple ) ) {
					$sound[ $slug ] = [
						'n-h'      => (string) $tuple['n-h'],
						'accent-h' => (string) $tuple['accent-h'],
						'accent-c' => (string) $tuple['accent-c'],
					];
				}
			}
		}

		if ( ! isset( $sound[ self::DEFAULT_SLUG ] ) ) {
			$sound[ self::DEFAULT_SLUG ] = self::FALLBACK_WARM_CLAY;
		}

		return $sound;
	}

	/**
	 * Include the generated file without ever letting it fatal or print.
	 *
	 * A malformed file fails in two ways PHP treats differently: a syntax
	 * error is a compile-time ParseError, thrown WHEN THE include STATEMENT
	 * RUNS (unlike a syntax error in the entry script itself, this is
	 * catchable); a file that parses but produces stray output fails
	 * silently into the response instead — output buffering swallows that,
	 * so nothing this file does not own can leak into wp_head.
	 *
	 * @param string $path Absolute path.
	 * @return mixed Whatever the file returned, or null on any failure.
	 */
	private static function require_file( string $path ): mixed {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		ob_start();

		try {
			return include $path;
		} catch ( \Throwable $e ) {
			return null;
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Whether $tuple is a well-formed palette entry: an array with exactly
	 * the three required keys, each a bare number safe to interpolate into a
	 * CSS custom property.
	 *
	 * @param mixed $tuple Candidate palette entry.
	 */
	private static function is_sound_tuple( mixed $tuple ): bool {
		if ( ! is_array( $tuple ) ) {
			return false;
		}

		foreach ( [ 'n-h', 'accent-h', 'accent-c' ] as $key ) {
			if ( ! isset( $tuple[ $key ] ) || ! is_scalar( $tuple[ $key ] ) ) {
				return false;
			}

			if ( 1 !== preg_match( self::VALUE_PATTERN, (string) $tuple[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}
}
