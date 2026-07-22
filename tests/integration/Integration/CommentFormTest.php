<?php
/**
 * The comment form renders Basecoat form-control classes.
 *
 * Asserts the rendered markup rather than the argument array passed to
 * comment_form(): the array is our input, the HTML is the contract, and core
 * reshapes parts of that HTML between releases.
 *
 * Routed through comments_template() rather than calling comment_form()
 * directly: our classes are wired at the comments.php call site (per
 * docs/specs/2026-07-23-component-tail-design.md), so a test that calls
 * comment_form() itself would only ever see core's bare defaults and could
 * never fail no matter what comments.php does. comments_template() only
 * renders on is_single()/is_page(), which is why this goes through
 * go_to( get_permalink() ) rather than only setting $GLOBALS['post'].
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_UnitTestCase;

final class CommentFormTest extends WP_UnitTestCase {

	private function render_comment_form(): string {
		// Author/email/url fields only render for a logged-out visitor; the
		// core test suite does not log a user in by default, but this is
		// pinned explicitly rather than relied upon.
		wp_set_current_user( 0 );

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		// A real front-end request populates $wp_stylesheet_path via
		// get_header()'s call to locate_template() before comments_template()
		// ever runs; go_to() only sets up the main query, so without this,
		// comments_template()'s own trailingslashit( $wp_stylesheet_path )
		// receives null and PHP 8.1 turns that into a deprecation-as-exception
		// under this suite's convertDeprecationsToExceptions.
		wp_set_template_globals();

		ob_start();
		comments_template();

		return (string) ob_get_clean();
	}

	public function test_the_comment_textarea_carries_the_basecoat_class(): void {
		self::assertMatchesRegularExpression( '#<textarea[^>]*class="[^"]*\btextarea\b#', $this->render_comment_form() );
	}

	public function test_the_submit_button_carries_the_basecoat_class(): void {
		self::assertMatchesRegularExpression( '#<(input|button)[^>]*class="[^"]*\bbtn\b#', $this->render_comment_form() );
	}

	public function test_the_author_and_email_inputs_carry_the_basecoat_class(): void {
		$html = $this->render_comment_form();

		self::assertMatchesRegularExpression( '#<input[^>]*id="author"[^>]*class="[^"]*\binput\b#', $html );
		self::assertMatchesRegularExpression( '#<input[^>]*id="email"[^>]*class="[^"]*\binput\b#', $html );
	}
}
