<?php
/**
 * The i18n invariants the POT generator cannot enforce.
 *
 * `wp i18n make-pot` warns about exactly one thing: a placeholder with no
 * `translators:` comment. The two defects that actually strand a user-facing
 * string are silent, because both make the string *vanish* from the POT and an
 * absence is not something a generator can report. Measured s16 against WP-CLI's
 * own make-pot: a mutated copy of this theme carrying one `'wrong-domain'` call
 * and one `__( $variable )` call produced 99 msgids instead of 101 and printed
 * no warning at all.
 *
 * So the three rules that survive only by being asserted live here, over the
 * shipped source:
 *
 * 1. every i18n call names the `woodev-base-theme` text domain;
 * 2. every translatable argument is a literal string — no variables, no
 *    concatenation, nothing a static extractor has to evaluate;
 * 3. no `_n()` family at all (AGENTS.md: Russian has three plural forms, so
 *    count-sensitive copy is phrased count-agnostically with
 *    `number_format_i18n()` instead).
 *
 * The analyser is exercised against a deliberately broken fixture as well as the
 * theme, so a scanner that silently stopped scanning fails a test rather than
 * reporting a clean theme.
 *
 * @package Woodev\Theme\Base\Tests\Unit
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class I18nSourceTest extends PHPUnitTestCase {

	private const THEME_DIR = __DIR__ . '/../../../woodev-base-theme';

	private const TEXT_DOMAIN = 'woodev-base-theme';

	/**
	 * Translation functions mapped to the argument index carrying the text domain.
	 *
	 * The `_x` family takes a disambiguation context before the domain, which is
	 * itself translatable source and so is checked for literalness too.
	 *
	 * @var array<string, int>
	 */
	private const DOMAIN_ARGUMENT = [
		'__'         => 1,
		'_e'         => 1,
		'esc_html__' => 1,
		'esc_html_e' => 1,
		'esc_attr__' => 1,
		'esc_attr_e' => 1,
		'_x'         => 2,
		'_ex'        => 2,
		'esc_html_x' => 2,
		'esc_attr_x' => 2,
	];

	/**
	 * Functions whose plural handling cannot express the Russian rule.
	 *
	 * @var list<string>
	 */
	private const PLURAL_FUNCTIONS = [ '_n', '_nx', '_n_noop', '_nx_noop' ];

	/**
	 * @return list<string> Theme-relative paths of every shipped PHP file.
	 */
	private function shipped_php_files(): array {
		$root  = realpath( self::THEME_DIR );
		$files = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( (string) $root, FilesystemIterator::SKIP_DOTS )
		);

		/** @var SplFileInfo $file */
		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$path = str_replace( '\\', '/', $file->getPathname() );

			// assets/dist is build output, not source, and is not in git.
			if ( str_contains( $path, '/assets/dist/' ) ) {
				continue;
			}

			$files[] = ltrim( substr( $path, strlen( str_replace( '\\', '/', (string) $root ) ) ), '/' );
		}

		sort( $files );

		return $files;
	}

	/**
	 * Tokenise, dropping whitespace and comments and flattening single-character
	 * tokens so that `(`, `,` and `)` can be compared directly.
	 *
	 * @return list<array{type: int|string, text: string, line: int}>
	 */
	private function significant_tokens( string $source ): array {
		$out = [];

		foreach ( token_get_all( $source ) as $token ) {
			if ( \is_array( $token ) ) {
				if ( \in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
					continue;
				}

				$out[] = [
					'type' => $token[0],
					'text' => $token[1],
					'line' => $token[2],
				];
				continue;
			}

			$out[] = [
				'type' => $token,
				'text' => $token,
				'line' => $out ? $out[ \count( $out ) - 1 ]['line'] : 0,
			];
		}

		return $out;
	}

	/**
	 * Collect the top-level arguments of the call whose opening parenthesis sits at
	 * `$open`, each as its own list of tokens.
	 *
	 * @param list<array{type: int|string, text: string, line: int}> $tokens Flattened tokens.
	 * @return list<list<array{type: int|string, text: string, line: int}>>
	 */
	private function call_arguments( array $tokens, int $open ): array {
		$openers = [ '(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE ];
		$closers = [ ')', ']', '}' ];

		$depth   = 0;
		$args    = [];
		$current = [];

		for ( $i = $open, $count = \count( $tokens ); $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( \in_array( $token['type'], $openers, true ) ) {
				++$depth;

				if ( 1 === $depth ) {
					continue;
				}
			} elseif ( \in_array( $token['type'], $closers, true ) ) {
				--$depth;

				if ( 0 === $depth ) {
					if ( $current ) {
						$args[] = $current;
					}

					return $args;
				}
			} elseif ( ',' === $token['type'] && 1 === $depth ) {
				$args[]  = $current;
				$current = [];
				continue;
			}

			$current[] = $token;
		}

		return $args;
	}

	/**
	 * A single quoted string and nothing else — the only shape a static extractor
	 * can read. `'a' . 'b'`, `$var` and `self::CONST` all fail here.
	 *
	 * @param list<array{type: int|string, text: string, line: int}> $argument Argument tokens.
	 */
	private function is_literal_string( array $argument ): bool {
		return 1 === \count( $argument ) && T_CONSTANT_ENCAPSED_STRING === $argument[0]['type'];
	}

	private function literal_value( array $argument ): string {
		/*
		 * Exactly one delimiter off each end — never a character SET. `trim( $raw,
		 * "'\"" )` also eats quotes that belong to the value: the literal
		 * `'woodev-base-theme"'` names a real, different domain, and trimming
		 * returned `woodev-base-theme`, so a string extracted into somebody else's
		 * POT passed as ours. A T_CONSTANT_ENCAPSED_STRING is always properly
		 * delimited, which makes one character each end exact rather than a guess.
		 */
		return substr( $argument[0]['text'], 1, -1 );
	}

	/**
	 * Report every i18n rule violation in one file's source.
	 *
	 * @return list<string> Human-readable findings; empty when the source is clean.
	 */
	private function findings( string $source, string $label ): array {
		$tokens   = $this->significant_tokens( $source );
		$findings = [];

		for ( $i = 0, $count = \count( $tokens ); $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( ! \in_array( $token['type'], [ T_STRING, T_NAME_FULLY_QUALIFIED ], true ) ) {
				continue;
			}

			/*
			 * PHP 8 tokenises `\__()` as a single T_NAME_FULLY_QUALIFIED carrying the
			 * leading separator, not as T_NS_SEPARATOR followed by T_STRING. Reaching a
			 * WordPress function that way from inside a namespace is normal — this theme
			 * does it for `\sprintf()` — so a scanner watching only T_STRING walks past
			 * `\_n( $var, … )` without a word. A name with an INNER separator is somebody
			 * else's namespaced function and is not ours to judge.
			 */
			$name = ltrim( $token['text'], '\\' );

			if ( str_contains( $name, '\\' ) ) {
				continue;
			}

			$prev = $tokens[ $i - 1 ] ?? null;

			// A method, a static call or a declaration of the same name is not ours.
			$disqualifying = [ T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ];

			if ( null !== $prev && \in_array( $prev['type'], $disqualifying, true ) ) {
				continue;
			}

			$next = $tokens[ $i + 1 ] ?? null;

			if ( null === $next || '(' !== $next['type'] ) {
				continue;
			}

			$where = $label . ':' . $token['line'];

			if ( \in_array( $name, self::PLURAL_FUNCTIONS, true ) ) {
				$findings[] = sprintf(
					'%s: %s() cannot express the Russian plural rule — phrase it count-agnostically with number_format_i18n().',
					$where,
					$name
				);
				continue;
			}

			if ( ! isset( self::DOMAIN_ARGUMENT[ $name ] ) ) {
				continue;
			}

			$domain_index = self::DOMAIN_ARGUMENT[ $name ];
			$arguments    = $this->call_arguments( $tokens, $i + 1 );

			// Every argument before the domain is source a translator will read.
			for ( $position = 0; $position < $domain_index; $position++ ) {
				if ( ! isset( $arguments[ $position ] ) || ! $this->is_literal_string( $arguments[ $position ] ) ) {
					$findings[] = sprintf(
						'%s: %s() argument %d is not a literal string — the extractor cannot read it, so the string never reaches the POT.',
						$where,
						$name,
						$position + 1
					);
				}
			}

			if ( ! isset( $arguments[ $domain_index ] ) ) {
				$findings[] = sprintf( '%s: %s() names no text domain.', $where, $name );
				continue;
			}

			$domain = $arguments[ $domain_index ];

			if ( ! $this->is_literal_string( $domain ) ) {
				$findings[] = sprintf( '%s: %s() has a non-literal text domain.', $where, $name );
				continue;
			}

			if ( self::TEXT_DOMAIN !== $this->literal_value( $domain ) ) {
				$findings[] = sprintf(
					'%s: %s() uses text domain "%s", not "%s" — the string is extracted into somebody else\'s POT, silently.',
					$where,
					$name,
					$this->literal_value( $domain ),
					self::TEXT_DOMAIN
				);
			}
		}

		return $findings;
	}

	/**
	 * Count the i18n call sites the analyser recognised.
	 */
	private function call_site_count( string $source ): int {
		$tokens = $this->significant_tokens( $source );
		$sites  = 0;

		for ( $i = 0, $count = \count( $tokens ); $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( ! \in_array( $token['type'], [ T_STRING, T_NAME_FULLY_QUALIFIED ], true ) ) {
				continue;
			}

			if ( ! isset( self::DOMAIN_ARGUMENT[ ltrim( $token['text'], '\\' ) ] ) ) {
				continue;
			}

			$next = $tokens[ $i + 1 ] ?? null;

			if ( null !== $next && '(' === $next['type'] ) {
				++$sites;
			}
		}

		return $sites;
	}

	public function test_the_walker_actually_finds_the_theme(): void {
		$files = $this->shipped_php_files();

		self::assertGreaterThan( 30, \count( $files ), 'Found suspiciously few PHP files — is the path right?' );
		self::assertContains( 'inc/Customizer/Customizer.php', $files );
	}

	/**
	 * The scanner has to be able to see the strings before its silence means
	 * anything. WP-CLI's make-pot extracted 82 PHP call sites from this theme in
	 * s16; the floor is deliberately below that so adding copy does not fail the
	 * suite, but deleting the scan does.
	 */
	public function test_the_scanner_sees_the_theme_s_translatable_strings(): void {
		$sites = 0;

		foreach ( $this->shipped_php_files() as $relative ) {
			$sites += $this->call_site_count( (string) file_get_contents( self::THEME_DIR . '/' . $relative ) );
		}

		self::assertGreaterThan( 70, $sites, 'The i18n scanner found almost nothing — it has stopped scanning.' );
	}

	public function test_the_analyser_catches_every_rule_it_asserts(): void {
		$broken = <<<'PHP'
		<?php
		esc_html_e( 'Fine', 'woodev-base-theme' );
		esc_html_e( 'Wrong home', 'some-plugin' );
		esc_html_e( $label, 'woodev-base-theme' );
		esc_html__( 'No domain at all' );
		_x( 'Split' . ' string', 'context', 'woodev-base-theme' );
		printf( _n( '%s comment', '%s comments', $count, 'woodev-base-theme' ), $count );
		$this->_e( 'A method of ours, not WordPress', 'anything' );
		\esc_html_e( $label, 'woodev-base-theme' );
		\_n( '%s item', '%s items', $count, 'woodev-base-theme' );
		__( 'A quote inside the domain', 'woodev-base-theme"' );
		Other\_e( 'Somebody else namespaced function', 'whatever' );
		PHP;

		$findings = $this->findings( $broken, 'fixture' );

		self::assertCount( 8, $findings, "Expected eight findings, got:\n  " . implode( "\n  ", $findings ) );
		self::assertStringContainsString( 'text domain "some-plugin"', $findings[0] );
		self::assertStringContainsString( 'argument 1 is not a literal string', $findings[1] );
		self::assertStringContainsString( 'names no text domain', $findings[2] );
		self::assertStringContainsString( 'argument 1 is not a literal string', $findings[3] );
		self::assertStringContainsString( '_n() cannot express the Russian plural rule', $findings[4] );

		// The three the critic found the analyser walking past, plus the one it
		// must keep ignoring. Root-namespaced calls arrive as a single
		// T_NAME_FULLY_QUALIFIED token; a domain whose value ends in a quote used
		// to be trimmed back into a match.
		self::assertStringContainsString( 'argument 1 is not a literal string', $findings[5] );
		self::assertStringContainsString( '_n() cannot express the Russian plural rule', $findings[6] );
		self::assertStringContainsString( 'text domain "woodev-base-theme"', $findings[7] );
	}

	public function test_every_shipped_string_is_extractable_and_ours(): void {
		$findings = [];

		foreach ( $this->shipped_php_files() as $relative ) {
			$findings = [
				...$findings,
				...$this->findings( (string) file_get_contents( self::THEME_DIR . '/' . $relative ), $relative ),
			];
		}

		self::assertSame(
			[],
			$findings,
			"i18n rule violations:\n  " . implode( "\n  ", $findings )
		);
	}
}
