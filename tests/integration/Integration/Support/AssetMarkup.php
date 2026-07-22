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
 * **Every assertion here is anchored on the element id**, never on the URL
 * alone. WordPress derives that id from the enqueue handle
 * (`woodev-base-style` becomes `id="woodev-base-style-css"` for a stylesheet
 * and `id="woodev-base-app-js-module"` for a script module — read off a real
 * response, not assumed), so it identifies exactly one enqueue call. A URL
 * substring does not: `Assets::enqueue()` also enqueues the JS entry's
 * imported CSS in a loop, and any plugin may enqueue from `assets/dist` too,
 * so an assertion matching "some element pointing into assets/dist" would
 * survive the deletion of the very enqueue it was written to pin.
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
	 * Assert a script module with this element id was printed.
	 *
	 * @param string $id          The element id WordPress derived from the enqueue handle.
	 * @param string $src_needle  Substring the `src` must contain; '' means "do not constrain the src".
	 *                            Built assets carry a Vite content hash, so an exact src is not assertable there.
	 * @param string $html        The captured wp_head/wp_footer markup.
	 * @param string $message     Optional assertion failure message.
	 */
	public static function assert_script_module_with_id( string $html, string $id, string $src_needle = '', string $message = '' ): void {
		$found = false;

		foreach ( self::parse_fragment( $html )->getElementsByTagName( 'script' ) as $script ) {
			if ( ! $script instanceof \DOMElement ) {
				continue;
			}

			// The HTML standard matches the `module` type value ASCII
			// case-insensitively, so `type="Module"` is a real module script.
			// WordPress emits lowercase; this only keeps the helper honest.
			if ( 'module' !== \strtolower( $script->getAttribute( 'type' ) ) ) {
				continue;
			}

			if ( $id !== $script->getAttribute( 'id' ) ) {
				continue;
			}

			if ( '' !== $src_needle && ! \str_contains( $script->getAttribute( 'src' ), $src_needle ) ) {
				continue;
			}

			$found = true;
			break;
		}

		\PHPUnit\Framework\Assert::assertTrue( $found, '' !== $message ? $message : self::describe( 'script module', $id, $src_needle ) );
	}

	/**
	 * Assert a stylesheet link element with this element id was printed.
	 *
	 * @param string $html         The captured wp_head/wp_footer markup.
	 * @param string $id           The element id WordPress derived from the enqueue handle.
	 * @param string $href_needle  Substring the `href` must contain; '' means "do not constrain the href".
	 * @param string $message      Optional assertion failure message.
	 */
	public static function assert_stylesheet_link_with_id( string $html, string $id, string $href_needle = '', string $message = '' ): void {
		$found = false;

		foreach ( self::parse_fragment( $html )->getElementsByTagName( 'link' ) as $link ) {
			if ( ! $link instanceof \DOMElement ) {
				continue;
			}

			if ( 'stylesheet' !== \strtolower( $link->getAttribute( 'rel' ) ) || $id !== $link->getAttribute( 'id' ) ) {
				continue;
			}

			if ( '' !== $href_needle && ! \str_contains( $link->getAttribute( 'href' ), $href_needle ) ) {
				continue;
			}

			$found = true;
			break;
		}

		\PHPUnit\Framework\Assert::assertTrue( $found, '' !== $message ? $message : self::describe( 'stylesheet element', $id, $href_needle ) );
	}

	/**
	 * Build a default failure message.
	 *
	 * @param string $what   Human name of the element kind being looked for.
	 * @param string $id     The element id that was required.
	 * @param string $needle The URL substring that was required, if any.
	 */
	private static function describe( string $what, string $id, string $needle ): string {
		$url_clause = '' !== $needle ? " whose URL contains \"{$needle}\"" : '';

		return "Expected a {$what} with id \"{$id}\"{$url_clause} in the rendered markup, none found.";
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
	 * **What the guard below does and does not do.** `loadHTML()` is a
	 * *recovering* HTML4 parser: malformed input is repaired, not rejected.
	 * Measured on PHP 8.1.34 in this project's own container — an unclosed
	 * `<script type="module" src="…">` returns `true` and yields a usable
	 * script node, and outright garbage returns `true` as well. So this is
	 * NOT a defence against malformed markup, and no assertion here should be
	 * read as proving the fragment was well-formed. Recovery is in fact the
	 * behaviour we want: the input is WordPress's own output, and a stricter
	 * parser would turn an unrelated plugin's sloppy tag into a failure of our
	 * asset tests.
	 *
	 * The one input `loadHTML()` genuinely refuses is an empty string, and on
	 * PHP 8 it throws `ValueError` rather than returning `false` (measured on
	 * 8.1.34 — the same trap Icons.php hit with `loadXML('')` in s4). It is
	 * caught and rethrown with context because an empty capture means the
	 * render produced nothing at all, which is a harness break rather than a
	 * missing asset, and the two deserve different messages. The `false` check
	 * is kept as belt-and-braces for a libxml build that reports failure that
	 * way instead.
	 *
	 * @param string $html The fragment to parse.
	 */
	private static function parse_fragment( string $html ): \DOMDocument {
		if ( '' === $html ) {
			throw new \RuntimeException(
				'AssetMarkup: the captured wp_head/wp_footer markup is empty. Nothing was rendered at all — ' .
				'a harness problem, not a missing asset.'
			);
		}

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
				'AssetMarkup: DOMDocument::loadHTML() reported failure on the captured wp_head/wp_footer markup.'
			);
		}

		return $dom;
	}
}
