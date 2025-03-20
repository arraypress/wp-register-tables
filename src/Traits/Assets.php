<?php
/**
 * Table Instance UI Trait
 *
 * Provides UI functionality for the Table_Instance class.
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register\Traits;

use ArrayPress\WP\Register\AssetsManager;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Trait_Table_Instance_UI
 *
 * UI functionality for the ListTable class
 */
trait Assets {

	/**
	 * Load necessary assets for the admin table
	 *
	 * This method is called when the table is initialized
	 */
	public function load_assets() {
		// Enqueue the admin tables assets
		AssetsManager::enqueue();

		// Add custom CSS from table configuration
		$this->add_custom_css();

		// Add custom JS from table configuration
		$this->add_custom_js();
	}

	/**
	 * Add custom CSS from table configuration
	 */
	private function add_custom_css() {
		// Check if we have custom CSS rules defined in the table config
		if ( empty( $this->table_config['css'] ) || ! is_array( $this->table_config['css'] ) ) {
			return;
		}

		$custom_css = '';
		foreach ( $this->table_config['css'] as $selector => $rules ) {
			$custom_css .= esc_html( $selector ) . ' { ' . esc_html( $rules ) . ' } ';
		}

		if ( ! empty( $custom_css ) ) {
			// Use WordPress function to add inline styles
			// Attach to our main stylesheet
			wp_add_inline_style( 'arraypress-admin-tables', $custom_css );
		}
	}

	/**
	 * Add custom JS from table configuration
	 */
	private function add_custom_js() {
		// Check if we have custom JS defined in the table config
		if ( empty( $this->table_config['js'] ) ) {
			return;
		}

		$custom_js = 'jQuery(document).ready(function($) {' . $this->table_config['js'] . '});';

		// Use WordPress function to add inline script
		// Attach to our main script
		wp_add_inline_script( 'arraypress-admin-tables', $custom_js );
	}

}