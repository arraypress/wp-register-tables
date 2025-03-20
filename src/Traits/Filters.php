<?php
/**
 * Table Instance Filters Trait
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register\Traits;

// Exit if accessed directly
use ArrayPress\WP\Register\ListTable;

defined( 'ABSPATH' ) || exit;

/**
 * Filters Trait
 *
 * Handles filter processing and query arguments for table instances.
 */
trait Filters {

	/**
	 * Advanced filters configuration
	 *
	 * @var array
	 */
	protected array $filters = [];

	/**
	 * Currently active filters
	 *
	 * @var array
	 */
	protected array $active_filters = [];

	/**
	 * Setup filters for this table.
	 */
	protected function setup_filters(): void {
		// Load advanced filters
		if ( isset( $this->table_config['filters']['advanced'] ) && is_array( $this->table_config['filters']['advanced'] ) ) {
			$this->filters = $this->table_config['filters']['advanced'];
		}

		/**
		 * Filters the advanced filters configuration.
		 *
		 * @param array         $filters Advanced filters configuration
		 * @param ListTable $this    The table instance
		 *
		 * @return array                 Modified filters configuration
		 */
		$this->filters = apply_filters( "{$this->hook_prefix}filters", $this->filters, $this );

		// Process active filters
		$this->process_active_filters();
	}

	/**
	 * Process active filters from request
	 */
	protected function process_active_filters(): void {
		// Clear active filters
		$this->active_filters = [];

		// Get date range parameters (to be excluded from standard processing)
		$date_params = $this->get_date_range_params();

		// Process advanced filters
		$this->process_filter_group( $this->filters, $date_params );

		// Process quick filters
		if ( isset( $this->table_config['filters']['quick'] ) && is_array( $this->table_config['filters']['quick'] ) ) {
			$this->process_filter_group( $this->table_config['filters']['quick'], $date_params );
		}

		// Process date range filters
		$this->process_date_range_filters( $date_params );
	}

	/**
	 * Process a group of filters (quick or advanced)
	 *
	 * @param array $filters     The filters to process
	 * @param array $date_params Date parameters to exclude
	 */
	protected function process_filter_group( array $filters, array $date_params ): void {
		foreach ( $filters as $filter_id => $filter ) {
			// Skip date range filters and date parameters
			if ( ( isset( $filter['type'] ) && $filter['type'] === 'date_range' ) || isset( $date_params[ $filter_id ] ) ) {
				continue;
			}

			$filter_value = $this->get_request_var( $filter_id, null );

			// Only add non-empty, non-zero values to active filters
			if ( $filter_value !== null && $filter_value !== '' ) {
				// Skip range filters with value 0
				if ( isset( $filter['type'] ) && $filter['type'] === 'range' && (int) $filter_value === 0 ) {
					continue;
				}

				$this->active_filters[ $filter_id ] = [
					'value'   => $filter_value,
					'label'   => $filter['title'] ?? ucfirst( $filter_id ),
					'display' => $this->get_filter_display_value( $filter_id, $filter_value, $filter )
				];
			}
		}
	}

	/**
	 * Get all date range parameters used in filters
	 *
	 * @return array Date parameter names as keys
	 */
	protected function get_date_range_params(): array {
		$date_params = [];

		// Get date range params from quick filters
		if ( isset( $this->table_config['filters']['quick'] ) && is_array( $this->table_config['filters']['quick'] ) ) {
			foreach ( $this->table_config['filters']['quick'] as $filter_id => $filter ) {
				if ( isset( $filter['type'] ) && $filter['type'] === 'date_range' ) {
					// Simplified and standardized naming convention
					$from_param = "{$filter_id}_from";
					$to_param   = "{$filter_id}_to";

					$date_params[ $from_param ] = true;
					$date_params[ $to_param ]   = true;
				}
			}
		}

		return $date_params;
	}

	/**
	 * Process date range filters
	 *
	 * @param array $date_params List of date parameters to process
	 */
	protected function process_date_range_filters( array $date_params ): void {
		if ( empty( $date_params ) ) {
			return;
		}

		// Process each date parameter
		foreach ( $date_params as $param => $true ) {
			$value = $this->get_request_var( $param, null );

			// Skip empty values
			if ( empty( $value ) ) {
				continue;
			}

			// Validate date format
			$value = $this->validate_date_format( $value ) ? $value : '';
			if ( empty( $value ) ) {
				continue;
			}

			// Find filter this parameter belongs to and get label
			$label = $this->get_date_param_label( $param );

			// Add to active filters
			$this->active_filters[ $param ] = [
				'value'   => $value,
				'label'   => $label,
				'display' => $value
			];
		}
	}

	/**
	 * Get label for a date parameter
	 *
	 * @param string $param Date parameter name
	 *
	 * @return string Label for the parameter
	 */
	protected function get_date_param_label( string $param ): string {
		// Default labels based on parameter name
		$is_from       = strpos( $param, '_from' ) !== false;
		$default_label = $is_from ? __( 'From', 'arraypress' ) : __( 'To', 'arraypress' );

		// Extract filter ID from parameter name
		$filter_id = preg_replace( '/_(?:from|to)$/', '', $param );

		// Find the associated filter to get its title
		if ( isset( $this->table_config['filters']['quick'] ) &&
		     is_array( $this->table_config['filters']['quick'] ) &&
		     isset( $this->table_config['filters']['quick'][ $filter_id ] ) ) {

			$filter = $this->table_config['filters']['quick'][ $filter_id ];
			$title  = $filter['title'] ?? ucfirst( $filter_id );

			return $is_from
				? sprintf( __( '%s From', 'arraypress' ), $title )
				: sprintf( __( '%s To', 'arraypress' ), $title );
		}

		return $default_label;
	}

	/**
	 * Get display value for a filter
	 *
	 * @param string     $filter_id Filter ID
	 * @param mixed      $value     Filter value
	 * @param array|null $filter    Filter configuration
	 *
	 * @return string Display value for the filter
	 */
	protected function get_filter_display_value( string $filter_id, $value, ?array $filter = null ): string {
		// Get filter config if not provided
		if ( $filter === null ) {
			$filter = $this->get_filter_config( $filter_id );
		}

		if ( ! $filter ) {
			return (string) $value;
		}

		// Boolean filters (toggle/checkbox)
		if ( isset( $filter['type'] ) && in_array( $filter['type'], [ 'toggle', 'checkbox' ] ) && $value == '1' ) {
			return __( 'Yes', 'arraypress' );
		}

		// Select options
		if ( isset( $filter['options'] ) && is_array( $filter['options'] ) ) {
			// Handle array of options with value/label pairs
			foreach ( $filter['options'] as $option ) {
				if ( is_array( $option ) && isset( $option['value'] ) && $option['value'] == $value ) {
					return $option['label'] ?? (string) $value;
				}
			}

			// Handle direct mapping in values array
			if ( isset( $filter['values'][ $value ] ) ) {
				return $filter['values'][ $value ];
			}
		}

		// Use display callback if provided
		if ( isset( $filter['display_callback'] ) && is_callable( $filter['display_callback'] ) ) {
			return call_user_func( $filter['display_callback'], $value );
		}

		return (string) $value;
	}

	/**
	 * Get filter configuration by ID
	 *
	 * @param string $filter_id Filter ID to look up
	 *
	 * @return array|null Filter configuration or null if not found
	 */
	protected function get_filter_config( string $filter_id ): ?array {
		// Check advanced filters
		if ( isset( $this->filters[ $filter_id ] ) ) {
			return $this->filters[ $filter_id ];
		}

		// Check quick filters
		if ( isset( $this->table_config['filters']['quick'][ $filter_id ] ) ) {
			return $this->table_config['filters']['quick'][ $filter_id ];
		}

		return null;
	}

	/**
	 * Build query arguments for data retrieval
	 *
	 * @param array $initial_args Initial arguments
	 *
	 * @return array Complete query arguments
	 */
	protected function build_query_args( array $initial_args = [] ): array {
		$args = $initial_args;

		// Add status
		$status = $this->get_status();
		if ( ! isset( $args['status'] ) && ! empty( $status ) && $status !== 'all' ) {
			$args['status'] = $status;
		}

		// Add search
		$search = $this->get_search();
		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		// Get list of date parameters
		$date_params = $this->get_date_range_params();

		// Add active filters (excluding date parameters)
		foreach ( $this->active_filters as $filter_id => $filter_data ) {
			// Skip date range parameters as they're handled separately
			if ( ! isset( $date_params[ $filter_id ] ) ) {
				$args[ $filter_id ] = $filter_data['value'];
			}
		}

		// Handle range filters with default values
		$this->exclude_default_range_values( $args );

		// Add date range queries
		$this->add_date_range_queries( $args );

		// Remove empty values
		$args = array_filter( $args, function ( $value ) {
			return $value !== '' && $value !== null;
		} );

		/**
		 * Filters the query arguments before they're passed to the data callback.
		 *
		 * @param array         $args Query arguments for retrieving data
		 * @param ListTable $this The table instance
		 *
		 * @return array               Modified query arguments
		 */
		return apply_filters( "{$this->hook_prefix}query_args", $args, $this );
	}

	/**
	 * Exclude range filters that are set to their default value
	 *
	 * @param array $args Query arguments to modify
	 */
	protected function exclude_default_range_values( array &$args ): void {
		// Check for range filters in advanced filters
		if ( ! empty( $this->filters ) ) {
			$this->process_range_filters( $this->filters, $args );
		}

		// Check for range filters in quick filters
		if ( isset( $this->table_config['filters']['quick'] ) && is_array( $this->table_config['filters']['quick'] ) ) {
			$this->process_range_filters( $this->table_config['filters']['quick'], $args );
		}
	}

	/**
	 * Process range filters to exclude default values
	 *
	 * @param array $filters Filter configurations
	 * @param array $args    Query arguments to modify
	 */
	protected function process_range_filters( array $filters, array &$args ): void {
		foreach ( $filters as $filter_id => $filter ) {
			// Only process range type filters
			if ( ! isset( $filter['type'] ) || $filter['type'] !== 'range' ) {
				continue;
			}

			// Skip if the filter is not in the args
			if ( ! isset( $args[ $filter_id ] ) ) {
				continue;
			}

			// Get the default value for this range
			$default_value = $filter['default'] ?? $filter['min'] ?? 0;

			// If the current value equals the default, remove it from args
			if ( (string) $args[ $filter_id ] === (string) $default_value ) {
				unset( $args[ $filter_id ] );
			}
		}
	}

	/**
	 * Add date range queries to args
	 *
	 * @param array $args Query arguments to modify
	 */
	protected function add_date_range_queries( array &$args ): void {
		// Look for a date range filter in quick filters
		if ( empty( $this->table_config['filters']['quick'] ) ) {
			return;
		}

		// Find the first date range filter
		$date_filter = null;
		$filter_id   = null;

		foreach ( $this->table_config['filters']['quick'] as $id => $filter ) {
			if ( isset( $filter['type'] ) && $filter['type'] === 'date_range' ) {
				$date_filter = $filter;
				$filter_id   = $id;
				break;
			}
		}

		if ( $date_filter === null ) {
			return;
		}

		// Get date values
		$from = $this->get_request_var( "{$filter_id}_from", '' );
		$to   = $this->get_request_var( "{$filter_id}_to", '' );

		// Skip if no valid dates
		if ( empty( $from ) && empty( $to ) ) {
			return;
		}

		// Validate dates
		$from = $this->validate_date_format( $from ) ? $from : '';
		$to   = $this->validate_date_format( $to ) ? $to : '';

		if ( empty( $from ) && empty( $to ) ) {
			return;
		}

		// Get field to query against
		$date_field = $date_filter['field'] ?? 'date_created';

		// Create date query
		$date_query = [ 'relation' => 'AND' ];

		if ( ! empty( $from ) ) {
			$date_query[] = [
				'column' => $date_field,
				'after'  => $from . ' 00:00:00'
			];
		}

		if ( ! empty( $to ) ) {
			$date_query[] = [
				'column' => $date_field,
				'before' => $to . ' 23:59:59'
			];
		}

		$args['date_query'] = $date_query;
	}

	/**
	 * Check if any filters are currently active
	 *
	 * @return bool True if any filters are active
	 */
	protected function has_active_filters(): bool {
		return ! empty( $this->active_filters ) || ! empty( $this->get_search() );
	}

}