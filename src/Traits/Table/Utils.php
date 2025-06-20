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
trait Utils {

	/**
	 * Get columns for a specific table ID
	 *
	 * @param string $table_id Table ID
	 *
	 * @return array Columns configuration or empty array if not found
	 */
	private function get_columns_by_id( string $table_id ): array {
		return $this->registered_tables[ $table_id ]['columns'] ?? [];
	}

	/**
	 * Get column headers for screen options
	 *
	 * @param string $table_id Table ID
	 *
	 * @return array Simple column headers array
	 */
	private function get_column_headers( string $table_id ): array {
		$columns = $this->registered_tables[ $table_id ]['columns'] ?? [];
		$headers = [];

		foreach ( $columns as $column_id => $column_config ) {
			if ( $column_id === 'cb' ) {
//				$headers[$column_id] = '<input type="checkbox" />';
				continue;
			}

			if ( is_array( $column_config ) ) {
				$headers[ $column_id ] = $column_config['title'] ?? ucfirst( $column_id );
			} else {
				$headers[ $column_id ] = $column_config;
			}
		}

		return $headers;
	}

}