<?php
/**
 * Asset loading via the Vite manifest.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

/**
 * Enqueues theme assets resolved through the Vite build manifest.
 */
final class Assets {

	private const DEV_SERVER = 'http://localhost:5173';
	private const JS_ENTRY   = 'src/js/app.js';
	private const CSS_ENTRY  = 'src/css/app.css';

	/**
	 * Hook asset enqueuing into WordPress.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue the built (or dev-server) theme assets.
	 */
	public function enqueue(): void {
		if ( \defined( 'WOODEV_BASE_DEV' ) && WOODEV_BASE_DEV ) {
			$this->enqueue_dev();
			return;
		}

		$dist     = get_template_directory() . '/assets/dist';
		$dist_uri = get_template_directory_uri() . '/assets/dist';
		$manifest = self::read_manifest( $dist . '/.vite/manifest.json' );

		$css = self::entry_file( $manifest, self::CSS_ENTRY );
		if ( null !== $css ) {
			wp_enqueue_style( 'woodev-base-style', "{$dist_uri}/{$css}", [], null );
		}

		$js = self::entry_file( $manifest, self::JS_ENTRY );
		if ( null !== $js ) {
			wp_enqueue_script_module( 'woodev-base-app', "{$dist_uri}/{$js}", [], null );
		}

		foreach ( self::entry_css( $manifest, self::JS_ENTRY ) as $index => $imported ) {
			wp_enqueue_style( "woodev-base-app-{$index}", "{$dist_uri}/{$imported}", [], null );
		}
	}

	/**
	 * Enqueue assets straight from the Vite dev server (HMR, no manifest).
	 */
	private function enqueue_dev(): void {
		wp_enqueue_script_module( 'woodev-base-vite-client', self::DEV_SERVER . '/@vite/client', [], null );
		wp_enqueue_script_module( 'woodev-base-app', self::DEV_SERVER . '/' . self::JS_ENTRY, [], null );
	}

	/**
	 * Read and decode a Vite manifest; empty array when absent/invalid.
	 *
	 * @param string $path Absolute path to the manifest.json file.
	 * @return array<string, array{file: string, css?: list<string>}>
	 */
	public static function read_manifest( string $path ): array {
		if ( ! \is_file( $path ) ) {
			return [];
		}

		$decoded = \json_decode( (string) \file_get_contents( $path ), true );

		return \is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Resolve the built file name for a manifest entry.
	 *
	 * @param array<string, array{file: string, css?: list<string>}> $manifest Decoded manifest.
	 * @param string                                                 $entry    Manifest entry key.
	 */
	public static function entry_file( array $manifest, string $entry ): ?string {
		return $manifest[ $entry ]['file'] ?? null;
	}

	/**
	 * Resolve the imported CSS file names for a manifest entry.
	 *
	 * @param array<string, array{file: string, css?: list<string>}> $manifest Decoded manifest.
	 * @param string                                                 $entry    Manifest entry key.
	 * @return list<string>
	 */
	public static function entry_css( array $manifest, string $entry ): array {
		return $manifest[ $entry ]['css'] ?? [];
	}
}
