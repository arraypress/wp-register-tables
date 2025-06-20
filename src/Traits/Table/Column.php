<?php
/**
 * Table Column Trait
 *
 * Provides column handling functionality for the Tables class.
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
 * Trait TableColumnTrait
 *
 * Column handling functionality for the Tables class
 */
trait Column {

	/**
	 * Normalize columns configuration to standardized format.
	 *
	 * @param array $config Table configuration
	 *
	 * @return array Normalized configuration
	 */
	private function normalize_columns_config( array $config ): array {
		// Skip if no columns defined
		if ( empty( $config['columns'] ) || ! is_array( $config['columns'] ) ) {
			return $config;
		}

		$new_columns      = [];
		$sortable_columns = $config['sortable_columns'] ?? [];

		foreach ( $config['columns'] as $column_id => $column_config ) {
			// Handle checkbox column shorthand
			if ( $column_id === 'cb' && ( $column_config === true || $column_config === '<input type="checkbox" />' ) ) {
				$new_columns[ $column_id ] = '<input type="checkbox" />';
				continue;
			}

			// Handle string column definitions
			if ( is_string( $column_config ) ) {
				$new_columns[ $column_id ] = [
					'title' => $column_config
				];
				continue;
			}

			// Already in array format
			if ( ! is_array( $column_config ) ) {
				$new_columns[ $column_id ] = [ 'title' => ucfirst( $column_id ) ];
				continue;
			}

			// Use the column configuration as-is
			$column_normalized = $column_config;

			// Check sortable columns and integrate if found
			if ( ! empty( $sortable_columns[ $column_id ] ) ) {
				if ( is_array( $sortable_columns[ $column_id ] ) ) {
					$column_normalized['sortable']     = true;
					$column_normalized['sort_by']      = $sortable_columns[ $column_id ][0] ?? $column_id;
					$column_normalized['default_sort'] = ! empty( $sortable_columns[ $column_id ][1] );
				} else {
					$column_normalized['sortable'] = (bool) $sortable_columns[ $column_id ];
				}
			}

			$new_columns[ $column_id ] = $column_normalized;
		}

		$config['columns'] = $new_columns;

		return $config;
	}

	/**
	 * Apply convention-based defaults to column configuration
	 *
	 * @param array $config Table configuration
	 *
	 * @return array Updated configuration
	 */
	private function apply_column_conventions( array $config ): array {
		if ( empty( $config['columns'] ) || ! is_array( $config['columns'] ) ) {
			return $config;
		}

		// Common patterns by type
		$date_patterns    = [ 'date', 'created_at', 'updated_at', 'timestamp' ];
		$money_patterns   = [ 'price', 'cost', 'total', 'amount', 'spent', 'revenue' ];
		$boolean_prefixes = [ 'is_', 'has_', 'can_', 'should_' ];
		$number_patterns  = [ 'count', 'number', 'qty', 'quantity', 'num_' ];
		$status_patterns  = [ 'status', '_status', 'state' ];

		foreach ( $config['columns'] as $column_id => &$column_config ) {
			// Skip checkbox and non-array columns
			if ( $column_id === 'cb' || ! is_array( $column_config ) ) {
				continue;
			}

			// Set default type based on column name pattern
			if ( ! isset( $column_config['type'] ) ) {
				// Boolean columns - check prefixes first
				$is_boolean = false;
				foreach ( $boolean_prefixes as $prefix ) {
					if ( str_contains( $column_id, $prefix ) ) {
						$column_config['type'] = 'toggle';
						$is_boolean            = true;
						break;
					}
				}

				if ( ! $is_boolean ) {
					// Check other patterns
					if ( $this->match_column_pattern( $column_id, $date_patterns ) ) {
						$column_config['type'] = 'date';
					} elseif ( $this->match_column_pattern( $column_id, $money_patterns ) ) {
						$column_config['type'] = 'money';
					} elseif ( $this->match_column_pattern( $column_id, $number_patterns ) ) {
						$column_config['type'] = 'number';
					} elseif ( $this->match_column_pattern( $column_id, $status_patterns ) ) {
						$column_config['type'] = 'status';
					}
				}
			}

			// Use column ID as callback if not specified
			if ( ! isset( $column_config['callback'] ) ) {
				$column_config['callback'] = $column_id;
			}
		}

		return $config;
	}

	/**
	 * Check if column ID matches any pattern in the array
	 *
	 * @param string $column_id The column ID to check
	 * @param array  $patterns  Array of patterns to match against
	 *
	 * @return bool True if any pattern matches
	 */
	private function match_column_pattern( string $column_id, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( str_contains( $column_id, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

}