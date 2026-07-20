<?php
/**
 * Template hierarchy integration tests.
 *
 * A misnamed or missing template file does not error — WordPress silently
 * falls back to index.php and the view "just looks a bit wrong". These pin
 * the exact file WordPress resolves for each core view, so that regression
 * is caught here instead of by eyeballing a browser.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_UnitTestCase;

final class TemplateHierarchyTest extends WP_UnitTestCase {

	public function test_single_post_uses_single_template(): void {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		self::assertStringEndsWith( 'single.php', get_single_template() );
	}

	public function test_page_uses_page_template(): void {
		$page_id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->go_to( get_permalink( $page_id ) );

		self::assertStringEndsWith( 'page.php', get_page_template() );
	}

	/**
	 * Only archive.php exists (no category.php), so get_archive_template() is
	 * the right entry point: it walks the full archive hierarchy and returns
	 * the first file that exists, whereas get_category_template() only checks
	 * category-specific file names and would return '' here.
	 */
	public function test_category_archive_uses_archive_template(): void {
		$term_id = self::factory()->category->create();
		self::factory()->post->create( [ 'post_category' => [ $term_id ] ] );

		$this->go_to( (string) get_term_link( $term_id, 'category' ) );

		self::assertStringEndsWith( 'archive.php', get_archive_template() );
	}

	public function test_search_uses_search_template(): void {
		$this->go_to( home_url( '/?s=hello' ) );

		self::assertStringEndsWith( 'search.php', get_search_template() );
	}

	public function test_404_uses_404_template(): void {
		$this->go_to( home_url( '/this-page-does-not-exist-at-all/' ) );

		self::assertStringEndsWith( '404.php', get_404_template() );
	}
}
