<?php
/**
 * Markup contracts for #43's content-template additions.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use Woodev\Theme\Base\Templates\Breadcrumbs;
use WP_UnitTestCase;

final class ContentTemplatesTest extends WP_UnitTestCase {

	public function test_a_single_post_breadcrumb_marks_the_post_as_current(): void {
		$category_id = self::factory()->category->create( [ 'name' => 'Journal' ] );
		$post_id     = self::factory()->post->create(
			[
				'post_title'    => 'Breadcrumb post',
				'post_category' => [ $category_id ],
			]
		);

		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		Breadcrumbs::render();
		$markup = (string) ob_get_clean();

		self::assertStringContainsString( 'class="wtb-breadcrumbs"', $markup );
		self::assertStringContainsString( '>Journal<', $markup );
		self::assertStringContainsString( '<span aria-current="page">Breadcrumb post</span>', $markup );
	}

	public function test_a_page_breadcrumb_includes_its_parent_before_the_current_page(): void {
		$parent_id = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_title' => 'Parent page',
			]
		);
		$page_id   = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_parent' => $parent_id,
				'post_title'  => 'Child page',
			]
		);

		$this->go_to( get_permalink( $page_id ) );

		ob_start();
		Breadcrumbs::render();
		$markup = (string) ob_get_clean();

		self::assertStringContainsString( '>Parent page<', $markup );
		self::assertStringContainsString( '<span aria-current="page">Child page</span>', $markup );
	}

	public function test_search_results_use_compact_rows_instead_of_post_cards(): void {
		self::factory()->post->create(
			[
				'post_title'   => 'Searchable article',
				'post_content' => 'A distinct search term lives here.',
			]
		);

		$this->go_to( home_url( '/?s=distinct+search+term' ) );

		ob_start();
		get_template_part( 'template-parts/content/search-results' );
		$markup = (string) ob_get_clean();

		self::assertStringContainsString( 'class="wtb-search-results"', $markup );
		self::assertStringContainsString( 'class="wtb-search-result', $markup );
		self::assertStringContainsString( 'View result', $markup );
		self::assertStringNotContainsString( 'wtb-entry-card', $markup );
	}

	public function test_breadcrumbs_are_absent_from_a_tag_archive(): void {
		$tag_id = self::factory()->tag->create( [ 'name' => 'No breadcrumb' ] );

		$this->go_to( get_tag_link( $tag_id ) );

		ob_start();
		Breadcrumbs::render();
		$markup = (string) ob_get_clean();

		self::assertSame( '', $markup );
	}
}
