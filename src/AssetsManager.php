<?php
/**
 * Admin Tables Assets Manager
 *
 * Handles loading CSS and JavaScript files for Admin Tables using Composer Assets.
 *
 * @package     ArrayPress\CustomTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 */
class AssetsManager {

	/**
	 * Whether assets have been enqueued
	 */
	private static bool $enqueued = false;

	/**
	 * Enqueue admin table assets
	 */
	public static function enqueue(): void {
		// Only enqueue once
		if ( self::$enqueued ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style_from_composer_file(
			'arraypress-admin-tables',
			__FILE__,
			'css/admin-tables.css'
		);

		// Enqueue JS
		wp_enqueue_script_from_composer_file(
			'arraypress-admin-tables',
			__FILE__,
			'js/admin-tables.js'
		);

		self::$enqueued = true;
	}

}