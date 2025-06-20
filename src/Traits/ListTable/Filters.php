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

namespace ArrayPress\CustomTables\Traits\ListTable;

// Exit if accessed directly
use ArrayPress\CustomTables\Format;
use ArrayPress\CustomTables\ListTable;
use ArrayPress\CustomTables\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Filters Trait
 *
 * Handles filter processing and query arguments for table instances.
 */
trait Filters {

	/**
	 * Filters configuration
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
		// Store filters directly
		$this->filters = $this->table_config['filters'] ?? [];

		/**
		 * Filters the filters configuration.
		 *
		 * @param array     $filters Filters configuration
		 * @param ListTable $this    The table instance
		 *
		 * @return array              Modified filters configuration
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

		// Process all filters
		if ( isset( $this->table_config['filters'] ) && is_array( $this->table_config['filters'] ) ) {
			foreach ( $this->table_config['filters'] as $filter_id => $filter ) {
				// Skip date range filters as they're handled separately
				if ( isset( $filter['type'] ) && $filter['type'] === 'date_range' ) {
					$this->process_date_range_filter( $filter_id, $filter );
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
	}

	/**
	 * Process a date range filter
	 *
	 * @param string $filter_id Filter identifier
	 * @param array  $filter    Filter configuration
	 */
	protected function process_date_range_filter( string $filter_id, array $filter ): void {
		$from_param = "{$filter_id}_from";
		$to_param   = "{$filter_id}_to";

		$from = $this->get_request_var( $from_param, '' );
		$to   = $this->get_request_var( $to_param, '' );

		// Skip if no values
		if ( empty( $from ) && empty( $to ) ) {
			return;
		}

		// Validate dates
		$from = Utils::validate_date_format( $from ) ? $from : '';
		$to   = Utils::validate_date_format( $to ) ? $to : '';

		if ( ! empty( $from ) ) {
			$this->active_filters[ $from_param ] = [
				'value'   => $from,
				'label'   => sprintf( __( '%s From', 'arraypress' ), $filter['title'] ?? $filter_id ),
				'display' => $from
			];
		}

		if ( ! empty( $to ) ) {
			$this->active_filters[ $to_param ] = [
				'value'   => $to,
				'label'   => sprintf( __( '%s To', 'arraypress' ), $filter['title'] ?? $filter_id ),
				'display' => $to
			];
		}
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

		// First priority: Use format_callback if provided
		if ( isset( $filter['format_callback'] ) && is_callable( $filter['format_callback'] ) ) {
			return call_user_func( $filter['format_callback'], $value, $filter_id, $filter );
		}

		// Second priority: Use display_callback (for backward compatibility)
		if ( isset( $filter['display_callback'] ) && is_callable( $filter['display_callback'] ) ) {
			return call_user_func( $filter['display_callback'], $value );
		}

		// Boolean filters (toggle/checkbox)
		if ( isset( $filter['type'] ) && in_array( $filter['type'], [ 'toggle', 'checkbox' ] ) && $value == '1' ) {
			return __( 'Yes', 'arraypress' );
		}

		// Type-specific formatting
		if ( isset( $filter['type'] ) ) {
			switch ( $filter['type'] ) {
				case 'range':
				case 'number':
					$decimals = $filter['decimals'] ?? 0;

					return Format::number( $value, $decimals );

				case 'money':
					$currency = $filter['currency'] ?? '$';
					$decimals = $filter['decimals'] ?? 2;
					$position = $filter['currency_position'] ?? 'before';

					return Format::money( (float) $value, $currency, $decimals, $position );

				case 'percent':
					$decimals = $filter['decimals'] ?? 1;

					return Format::percentage( $value, $decimals );
			}
		}

		// Select options
		if ( isset( $filter['options'] ) && is_array( $filter['options'] ) ) {
			return Utils::get_select_option_display( $value, $filter['options'] );
		}

		// Default: Return as string
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
		if ( isset( $this->filters[ $filter_id ] ) ) {
			return $this->filters[ $filter_id ];
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

		// Add active filters
		foreach ( $this->active_filters as $filter_id => $filter_data ) {
			$args[ $filter_id ] = $filter_data['value'];
		}

		// Handle date range filters
		$this->add_date_range_queries( $args );

		// Remove empty values
		$args = array_filter( $args, function ( $value ) {
			return $value !== '' && $value !== null;
		} );

		/**
		 * Filters the query arguments before they're passed to the data callback.
		 *
		 * @param array     $args Query arguments for retrieving data
		 * @param ListTable $this The table instance
		 *
		 * @return array           Modified query arguments
		 */
		return apply_filters( "{$this->hook_prefix}query_args", $args, $this );
	}

	/**
	 * Add date range queries to args
	 *
	 * @param array $args Query arguments to modify
	 */
	protected function add_date_range_queries( array &$args ): void {
		if ( ! isset( $this->table_config['filters'] ) || ! is_array( $this->table_config['filters'] ) ) {
			return;
		}

		foreach ( $this->table_config['filters'] as $filter_id => $filter ) {
			if ( ! isset( $filter['type'] ) || $filter['type'] !== 'date_range' ) {
				continue;
			}

			// Get date field to query against
			$date_field = $filter['field'] ?? 'date_created';

			// Get date values
			$from_param = "{$filter_id}_from";
			$to_param   = "{$filter_id}_to";

			$from = $this->get_request_var( $from_param, '' );
			$to   = $this->get_request_var( $to_param, '' );

			// Skip if no valid dates
			if ( empty( $from ) && empty( $to ) ) {
				continue;
			}

			// Validate dates
			$from = Utils::validate_date_format( $from ) ? $from : '';
			$to   = Utils::validate_date_format( $to ) ? $to : '';

			if ( empty( $from ) && empty( $to ) ) {
				continue;
			}

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

			// Add to main query args
			if ( ! isset( $args['date_query'] ) ) {
				$args['date_query'] = $date_query;
			} else {
				// If there's already a date query, add this as a nested query
				$args['date_query'][] = $date_query;
			}
		}
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