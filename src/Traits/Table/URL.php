<?php
/**
 * Table URL Trait
 *
 * Provides URL generation functionality for the Tables class.
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\Table;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Trait TableUrlTrait
 *
 * URL generation functionality for the Tables class
 */
trait URL {

	/**
	 * Get a URL for table operations with comprehensive parameter handling
	 *
	 * @param string       $table_id The table ID
	 * @param string|array $action   The action name or array of query parameters
	 * @param int|string   $item_id  Optional item ID
	 * @param array        $extra    Optional extra query parameters
	 *
	 * @return string      The generated URL
	 */
	public function get_url( string $table_id, $action = '', $item_id = null, array $extra = [] ): string {
		// Get table configuration
		$table_config = $this->registered_tables[ $table_id ] ?? [];
		if ( empty( $table_config ) ) {
			return admin_url( 'admin.php' );
		}

		// 1. Use direct base_url if provided as a string
		if ( isset( $table_config['base_url'] ) && is_string( $table_config['base_url'] ) ) {
			$base_url = $table_config['base_url'];
		} // 2. Use callback if provided
		elseif ( isset( $table_config['base_url'] ) && is_callable( $table_config['base_url'] ) ) {
			$base_url = call_user_func( $table_config['base_url'] );
		} // 3. Generate based on parent_slug and slug
		else {
			$slug        = $table_config['slug'] ?? $table_id;
			$parent_slug = $table_config['parent_slug'] ?? '';

			if ( in_array( $parent_slug, [ 'options-general.php', 'tools.php', 'upload.php', 'index.php' ] ) ) {
				$base_url = admin_url( $parent_slug . '?page=' . $slug );
			} elseif ( strpos( $parent_slug, 'edit.php' ) === 0 ) {
				$base_url = admin_url( $parent_slug . '&page=' . $slug );
			} else {
				$base_url = admin_url( 'admin.php?page=' . $slug );
			}
		}

		// Build query parameters
		$params = [];

		// Handle action parameter(s)
		if ( is_array( $action ) ) {
			// If action is an array, use it directly as query parameters
			$params = $action;
		} elseif ( ! empty( $action ) && is_string( $action ) ) {
			// If action is a string, add it as an 'action' parameter
			$params['action'] = $action;
		}

		// Add item ID if provided
		if ( $item_id !== null ) {
			$params['id'] = (string)$item_id; // Ensure item_id is a string
		}

		// Add any extra parameters
		if ( ! empty( $extra ) ) {
			$params = array_merge( $params, $extra );
		}

		// Build the final URL using WordPress add_query_arg function
		// This function handles proper URL parameter encoding
		return add_query_arg( $params, $base_url );
	}

}