<?php
/**
 * Admin Tables Assets Manager
 *
 * Handles loading CSS and JavaScript files for Admin Tables.
 *
 * @package     ArrayPress\WP\Register
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class AssetsManager
 *
 * Handles loading CSS and JavaScript files for Admin Tables.
 *
 * @since 1.0.0
 */
class AssetsManager {

	/**
	 * Whether assets have been registered
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Whether assets have been enqueued
	 *
	 * @var bool
	 */
	private static bool $enqueued = false;

	/**
	 * Register admin table assets
	 */
	public static function register(): void {
		// Only register once
		if ( self::$registered ) {
			return;
		}

		// Register CSS
		wp_register_style(
			'arraypress-admin-tables',
			self::get_asset_url( 'css/admin-tables.css' ),
			[],
			self::get_asset_version( 'css/admin-tables.css' )
		);

		// Register JS
		wp_register_script(
			'arraypress-admin-tables',
			self::get_asset_url( 'js/admin-tables.js' ),
			[ 'jquery' ],
			self::get_asset_version( 'js/admin-tables.js' ),
			true
		);

		self::$registered = true;
	}

	/**
	 * Enqueue admin table assets
	 */
	public static function enqueue(): void {
		// Register assets if not already registered
		if ( ! self::$registered ) {
			self::register();
		}

		// Only enqueue once
		if ( self::$enqueued ) {
			return;
		}

		wp_enqueue_style( 'arraypress-admin-tables' );
		wp_enqueue_script( 'arraypress-admin-tables' );

		self::$enqueued = true;
	}

	/**
	 * Get the URL for an asset
	 *
	 * @param string $file_path Relative file path
	 *
	 * @return string Asset URL
	 */
	private static function get_asset_url( string $file_path ): string {
		$base_dir = dirname( __FILE__, 2 ); // Go up one level from current file

		// For Composer installations
		if ( defined( 'WP_PLUGIN_URL' ) && strpos( $base_dir, WP_PLUGIN_DIR ) !== false ) {
			$relative_path = str_replace( WP_PLUGIN_DIR, '', $base_dir );

			return WP_PLUGIN_URL . $relative_path . '/assets/' . $file_path;
		}

		// Plugin directory based URL
		return plugins_url( 'assets/' . $file_path, $base_dir );
	}

	/**
	 * Get asset version number (for cache busting)
	 *
	 * @param string $file_path Relative file path
	 *
	 * @return string Version number
	 */
	private static function get_asset_version( string $file_path ): string {
		$full_path = dirname( __FILE__, 2 ) . '/assets/' . $file_path;

		if ( file_exists( $full_path ) ) {
			return (string) filemtime( $full_path );
		}

		return '1.0.0';
	}
}