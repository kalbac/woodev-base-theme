<?php
/**
 * Core's threaded-reply script is enqueued exactly where it belongs.
 *
 * Its absence is a quiet defect rather than a loud one: the Reply link keeps working
 * through `?replytocom=N#respond`, so the page looks fine and only the inline form move
 * is missing. Theme Check found it; nothing else would have. `readme.txt` claims the
 * `threaded-comments` tag, which is what makes this a promise rather than a nicety.
 *
 * Asserted through the real hook rather than by calling the method, so a change to WHERE
 * it is hooked fails here too.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_UnitTestCase;

final class CommentReplyScriptTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// $wp_scripts survives between tests even though the DB does not — the same
		// trap docs/gotchas/wp-unittestcase-does-not-reset-wp-styles.md documents for
		// styles. Without this, whichever test runs first decides the others' result.
		unset( $GLOBALS['wp_scripts'] );
	}

	private function run_enqueue(): void {
		do_action( 'wp_enqueue_scripts' );
	}

	public function test_it_is_enqueued_on_a_singular_post_with_threaded_comments_open(): void {
		update_option( 'thread_comments', 1 );

		$post_id = self::factory()->post->create( [ 'comment_status' => 'open' ] );
		$this->go_to( get_permalink( $post_id ) );

		self::assertTrue( is_singular(), 'Harness guard: the request did not resolve to a single post.' );
		self::assertTrue( comments_open( $post_id ), 'Harness guard: comments are not open.' );

		$this->run_enqueue();

		self::assertTrue(
			wp_script_is( 'comment-reply', 'enqueued' ),
			'comment-reply is not enqueued, so replies fall back to a page load.'
		);
	}

	public function test_it_is_not_enqueued_when_threading_is_off(): void {
		// The conditions are the point: enqueuing it unconditionally would ship a script
		// to every visitor of every page for a feature the site has switched off.
		update_option( 'thread_comments', 0 );

		$post_id = self::factory()->post->create( [ 'comment_status' => 'open' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->run_enqueue();

		self::assertFalse(
			wp_script_is( 'comment-reply', 'enqueued' ),
			'comment-reply is enqueued although threaded comments are disabled.'
		);
	}

	public function test_it_is_not_enqueued_when_comments_are_closed(): void {
		update_option( 'thread_comments', 1 );

		$post_id = self::factory()->post->create( [ 'comment_status' => 'closed' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->run_enqueue();

		self::assertFalse(
			wp_script_is( 'comment-reply', 'enqueued' ),
			'comment-reply is enqueued on a post whose comments are closed.'
		);
	}

	public function test_it_is_not_enqueued_on_an_archive(): void {
		update_option( 'thread_comments', 1 );

		self::factory()->post->create( [ 'comment_status' => 'open' ] );
		$this->go_to( home_url( '/' ) );

		$this->run_enqueue();

		self::assertFalse(
			wp_script_is( 'comment-reply', 'enqueued' ),
			'comment-reply is enqueued on a listing page, where no comment form exists.'
		);
	}
}
