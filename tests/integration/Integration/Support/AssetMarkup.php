<?php
/**
 * Structural assertions against a captured wp_head/wp_footer HTML fragment.
 *
 * @package Woodev\Theme\Base\Tests\Integration\Support
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration\Support;

/**
 * Parses the markup with DOMDocument and asks precise structural questions,
 * instead of pattern-matching the HTML.
 *
 * A regex heuristic for "is this a script module with this src" was tried
 * first and rejected: `\b` matches after a hyphen, so `data-type="module"`
 * and `data-src="…"` both satisfy `\btype=…` / `\bsrc=…`, `<script` also
 * matches `<scripture`, and spacing/attribute-order variants are missed
 * entirely. See docs/gotchas/three-rounds-of-fixes-means-change-the-approach.md
 * — regex-parsing HTML attributes has already cost this codebase three review
 * rounds once. DOMDocument answers the same question exactly instead of
 * approximately.
 *
 * Failure messages below deliberately avoid writing the tags out as literal
 * markup (`<script … src="…">`, `<link rel="stylesheet">`): WordPress.WP.
 * EnqueuedResources flags exactly those substrings, on the assumption that
 * shipped theme code is echoing an un-enqueued resource — a false positive
 * here, since these strings only ever appear inside PHPUnit assertion
 * messages, but avoiding the trigger keeps the sniff meaningful everywhere
 * else instead of adding a suppression.
 */
final class AssetMarkup {

	/**
	 * Not instantiable — a namespace for static assertion helpers.
	 */
	private function __construct() {}

	/**
	 * Assert a script module (`<script type="module">`) with exactly this `src` was printed.
	 *
	 * @param string $html    The captured wp_head/wp_footer markup.
	 * @param string $src     The exact `src` attribute value to require.
	 * @param string $message Optional assertion failure message.
	 */
	public static function assert_script_module_with_exact_src( string $html, string $src, string $message = '' ): void {
		self::assert_script_module_matches(
			$html,
			static fn ( string $actual_src ): bool => $actual_src === $src,
			'' !== $message ? $message : "Expected a script module with src \"{$src}\" in the rendered markup, none found."
		);
	}

	/**
	 * Assert a script module (`<script type="module">`) whose `src` contains a substring was printed.
	 *
	 * Built assets carry a Vite content hash in the file name (app-LESVLxdP.js),
	 * so production code cannot assert an exact `src` — only that some script
	 * module resolved through the expected build directory.
	 *
	 * @param string $html    The captured wp_head/wp_footer markup.
	 * @param string $needle  Substring the `src` attribute must contain.
	 * @param string $message Optional assertion failure message.
	 */
	public static function assert_script_module_with_src_containing( string $html, string $needle, string $message = '' ): void {
		self::assert_script_module_matches(
			$html,
			static fn ( string $actual_src ): bool => \str_contains( $actual_src, $needle ),
			'' !== $message ? $message : "Expected a script module whose src contains \"{$needle}\" in the rendered markup, none found."
		);
	}

	/**
	 * Assert a stylesheet link element whose `href` contains a substring was printed.
	 *
	 * @param string $html    The captured wp_head/wp_footer markup.
	 * @param string $needle  Substring the `href` attribute must contain.
	 * @param string $message Optional assertion failure message.
	 */
	public static function assert_stylesheet_link_with_href_containing( string $html, string $needle, string $message = '' ): void {
		$found = false;

		foreach ( self::parse_fragment( $html )->getElementsByTagName( 'link' ) as $link ) {
			if ( ! $link instanceof \DOMElement ) {
				continue;
			}

			if ( 'stylesheet' === $link->getAttribute( 'rel' ) && \str_contains( $link->getAttribute( 'href' ), $needle ) ) {
				$found = true;
				break;
			}
		}

		\PHPUnit\Framework\Assert::assertTrue(
			$found,
			'' !== $message ? $message : "Expected a stylesheet link element whose href contains \"{$needle}\" in the rendered markup, none found."
		);
	}

	/**
	 * Shared walk over every script module element, testing its `src`
	 * against a caller-supplied predicate.
	 *
	 * @param string                $html    The captured wp_head/wp_footer markup.
	 * @param callable(string):bool $matches Predicate applied to each module's `src` attribute.
	 * @param string                $message Assertion failure message.
	 */
	private static function assert_script_module_matches( string $html, callable $matches, string $message ): void {
		$found = false;

		foreach ( self::parse_fragment( $html )->getElementsByTagName( 'script' ) as $script ) {
			if ( ! $script instanceof \DOMElement ) {
				continue;
			}

			if ( 'module' === $script->getAttribute( 'type' ) && $matches( $script->getAttribute( 'src' ) ) ) {
				$found = true;
				break;
			}
		}

		\PHPUnit\Framework\Assert::assertTrue( $found, $message );
	}

	/**
	 * Parse an HTML fragment (not a full document) into a DOMDocument.
	 *
	 * Follows Icons.php's DOMDocument idiom: internal libxml errors are
	 * captured rather than emitted as PHP warnings, and the toggle is always
	 * restored via `finally` so a throw mid-parse cannot leak it into later
	 * tests.
	 *
	 * The captured string is `wp_head` + `wp_footer` output concatenated —
	 * a fragment with no single root element, not a full HTML document — so
	 * this wraps it in a `<body>` before parsing and passes
	 * LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD to stop libxml silently
	 * inserting its own `<html><body>` wrapper and DOCTYPE around that, which
	 * would otherwise have to be stripped back out to reach the same nodes.
	 *
	 * A failed parse throws rather than returning an empty document: silently
	 * treating "the markup itself is malformed" the same as "the asset under
	 * test is absent" would hide a real harness break behind an assertion
	 * failure that looks identical to a genuine missing-asset failure.
	 *
	 * @param string $html The fragment to parse.
	 */
	private static function parse_fragment( string $html ): \DOMDocument {
		$dom      = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );

		try {
			$loaded = $dom->loadHTML(
				'<body>' . $html . '</body>',
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
		} finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}

		if ( ! $loaded ) {
			throw new \RuntimeException(
				'AssetMarkup: DOMDocument::loadHTML() failed to parse the captured wp_head/wp_footer markup. ' .
				'The fragment itself is malformed — this is a harness problem, not merely a missing asset.'
			);
		}

		return $dom;
	}
}
