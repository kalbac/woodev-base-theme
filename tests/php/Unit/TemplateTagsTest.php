<?php
/**
 * Template tags — plain functions in inc/template-tags.php, not autoloaded by
 * the class autoloader, so this file requires it directly rather than relying
 * on the bootstrap (which never calls functions.php / Theme::boot()).
 *
 * @package Woodev\Theme\Base\Tests
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;

require_once __DIR__ . '/../../../woodev-base-theme/inc/template-tags.php';

/**
 * Covers woodev_base_category_badges(): the current post's categories as
 * Basecoat badge links (spec §7 component tail).
 */
final class TemplateTagsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
	}

	/**
	 * @param array<int, object> $categories
	 */
	private function stub_categories( array $categories ): void {
		Functions\when( 'get_the_category' )->justReturn( $categories );
	}

	private static function category( int $term_id, string $name ): object {
		return (object) [
			'term_id' => $term_id,
			'name'    => $name,
		];
	}

	public function test_no_categories_prints_nothing_at_all(): void {
		$this->stub_categories( [] );

		\ob_start();
		\woodev_base_category_badges();
		$output = \ob_get_clean();

		// Not merely "no badge" — nothing at all, not even the wrapper div.
		self::assertSame( '', $output );
	}

	public function test_one_category_prints_the_wrapper_and_one_badge_link(): void {
		$this->stub_categories( [ self::category( 5, 'News' ) ] );
		Functions\when( 'get_category_link' )->justReturn( 'https://example.test/category/news/' );

		\ob_start();
		\woodev_base_category_badges();
		$output = \ob_get_clean();

		self::assertStringContainsString( '<div class="wtb-entry-categories">', $output );
		self::assertStringContainsString( '</div>', $output );
		self::assertSame( 1, \substr_count( $output, '<a class="badge" data-variant="secondary"' ) );
		self::assertStringContainsString( 'href="https://example.test/category/news/"', $output );
		self::assertStringContainsString( '>News</a>', $output );
	}

	public function test_multiple_categories_print_one_badge_link_each(): void {
		$this->stub_categories(
			[
				self::category( 5, 'News' ),
				self::category( 7, 'Reviews' ),
			]
		);
		Functions\when( 'get_category_link' )->alias(
			static fn( int $term_id ): string => "https://example.test/category/{$term_id}/"
		);

		\ob_start();
		\woodev_base_category_badges();
		$output = \ob_get_clean();

		self::assertSame( 1, \substr_count( $output, '<div class="wtb-entry-categories">' ) );
		self::assertSame( 2, \substr_count( $output, '<a class="badge" data-variant="secondary"' ) );
		self::assertStringContainsString( 'href="https://example.test/category/5/"', $output );
		self::assertStringContainsString( 'href="https://example.test/category/7/"', $output );
		self::assertStringContainsString( '>News</a>', $output );
		self::assertStringContainsString( '>Reviews</a>', $output );
	}

	/**
	 * Pins escaping on both the URL and the name: a category link comes from
	 * get_category_link(), which is trusted core output, but the category NAME
	 * is user-authored content (any user who can create terms) and must be
	 * esc_html()'d. Feeding a script tag through the name and asserting only
	 * the escaped form survives is what makes this guard mean something —
	 * deleting esc_html() must turn this red (mutation-tested; see plan Step 5).
	 */
	public function test_the_category_name_is_escaped(): void {
		Functions\when( 'esc_html' )->alias(
			static fn( $value ): string => \htmlspecialchars( (string) $value, ENT_QUOTES )
		);
		$this->stub_categories( [ self::category( 9, '<script>alert(1)</script>' ) ] );
		Functions\when( 'get_category_link' )->justReturn( 'https://example.test/category/hostile/' );

		\ob_start();
		\woodev_base_category_badges();
		$output = \ob_get_clean();

		self::assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $output );
		self::assertStringNotContainsString( '<script>alert(1)</script>', $output );
	}
}
