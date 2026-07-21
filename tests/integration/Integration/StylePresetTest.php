<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use Woodev\Theme\Base\StylePreset;
use WP_UnitTestCase;

final class StylePresetTest extends WP_UnitTestCase {

	public function test_from_theme_mod_reads_a_real_stored_pack(): void {
		set_theme_mod( 'style_preset', 'nova' );
		self::assertSame( StylePreset::Nova, StylePreset::from_theme_mod() );
	}

	public function test_from_theme_mod_defaults_to_vega_without_a_setting(): void {
		remove_theme_mod( 'style_preset' );
		self::assertSame( StylePreset::Vega, StylePreset::from_theme_mod() );
	}
}
