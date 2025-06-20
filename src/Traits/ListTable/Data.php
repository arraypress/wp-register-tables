<?php
/**
 * Table Instance Data Trait
 *
 * Provides data handling functionality for the Table_Instance class.
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\ListTable;

// Exit if accessed directly
use ArrayPress\CustomTables\ListTable;

defined( 'ABSPATH' ) || exit;

/**
 * Data Trait
 *
 * Data handling functionality for the Table_Instance class
 */
trait Data {

	/**
	 * Cache storage for table data
	 *
	 * Stores query results to avoid redundant database calls
	 * within the same page load.
	 *
	 * @var array
	 */
	protected array $data_cache = [];

	/**
	 * Generate a consistent cache key for storing and retrieving data
	 *
	 * @param array  $args Query arguments
	 * @param string $type Cache type (data, count, etc)
	 *
	 * @return string Cache key
	 */
	protected function generate_cache_key( array $args, string $type = 'data' ): string {
		return md5( $this->table_id . '_' . $type . '_' . serialize( $args ) );
	}

	/**
	 * Get data for the table with in-memory caching
	 *
	 * Retrieves data using the configured callback, with caching to
	 * improve performance for repeated calls with the same arguments.
	 *
	 * @return array Array of data items for the table
	 */
	public function get_data(): array {
		// Fire action before retrieving table data
		do_action( "{$this->hook_prefix}before_get_data", $this );

		// Check for data callback function
		if ( isset( $this->table_config['callbacks']['get'] ) && is_callable( $this->table_config['callbacks']['get'] ) ) {
			// Build base query args
			$args = $this->build_query_args();

			// Add pagination and sorting
			$this->args = $this->parse_pagination_args( $args );

			// Generate cache key
			$cache_key = $this->generate_cache_key( $this->args );

			// Check if we have cached data
			if ( isset( $this->data_cache[ $cache_key ] ) ) {
				$data = $this->data_cache[ $cache_key ];
			} else {
				// Get data using callback (with pagination)
				$data = call_user_func( $this->table_config['callbacks']['get'], $this->args );

				// Store in cache
				$this->data_cache[ $cache_key ] = $data;
			}

			/**
			 * Filters the table data
			 *
			 * @param array     $data The table data
			 * @param array     $args The query arguments used
			 * @param ListTable $this Current table instance
			 *
			 * @return array Modified table data
			 */
			$data = apply_filters( "{$this->hook_prefix}data", $data, $this->args, $this );

			/**
			 * Fires after retrieving table data
			 *
			 * @param ListTable $this Current table instance
			 * @param array     $data The table data retrieved
			 */
			do_action( "{$this->hook_prefix}after_get_data", $this, $data );

			return $data;
		}

		// Return empty array if no callback
		do_action( "{$this->hook_prefix}after_get_data", $this, [] );

		return [];
	}

	/**
	 * Get counts for different statuses with caching
	 *
	 * Retrieves count data for views/tabs with in-memory caching
	 * to improve performance.
	 */
	public function get_counts(): void {
		/**
		 * Fires before retrieving count data
		 *
		 * @param ListTable $this Current table instance
		 */
		do_action( "{$this->hook_prefix}before_get_counts", $this );

		if ( isset( $this->table_config['callbacks']['count'] ) && is_callable( $this->table_config['callbacks']['count'] ) ) {
			// Build base query args
			$args = $this->build_query_args();

			// Generate cache key
			$cache_key = $this->generate_cache_key( $args, 'counts' );

			// Check if we have cached counts
			if ( isset( $this->data_cache[ $cache_key ] ) ) {
				$this->counts = $this->data_cache[ $cache_key ];
			} else {
				// Get counts using callback
				$this->counts = call_user_func( $this->table_config['callbacks']['count'], $args );

				// Store in cache
				$this->data_cache[ $cache_key ] = $this->counts;
			}

			/**
			 * Filters the status counts
			 *
			 * @param array     $counts The counts for different statuses
			 * @param array     $args   The query arguments used
			 * @param ListTable $this   Current table instance
			 *
			 * @return array Modified counts
			 */
			$this->counts = apply_filters( "{$this->hook_prefix}counts", $this->counts, $args, $this );
		} else {
			// Default count is just the total number of items
			$data         = $this->get_data();
			$this->counts = [ 'total' => is_countable( $data ) ? count( $data ) : 0 ];

			/**
			 * Filters the default counts when no count callback is defined
			 *
			 * @param array     $counts The default counts (only 'total' is set)
			 * @param ListTable $this   Current table instance
			 *
			 * @return array Modified counts
			 */
			$this->counts = apply_filters( "{$this->hook_prefix}default_counts", $this->counts, $this );
		}

		// Ensure total is set
		$this->ensure_total_count();

		/**
		 * Fires after retrieving count data
		 *
		 * @param ListTable $this   Current table instance
		 * @param array     $counts The counts retrieved for different statuses
		 */
		do_action( "{$this->hook_prefix}after_get_counts", $this, $this->counts );
	}

	/**
	 * Ensure total count is set
	 */
	protected function ensure_total_count(): void {
		if ( ! isset( $this->counts['total'] ) ) {
			$total = 0;
			foreach ( $this->counts as $status => $count ) {
				if ( $status !== 'total' ) {
					$total += $count;
				}
			}
			$this->counts['total'] = $total;
		}
	}

	/**
	 * Clear the table's data cache
	 *
	 * Clears all cached data or just a specific type
	 * to ensure fresh data is fetched on next request.
	 *
	 * @param string|null $type Specific cache type to clear or null for all
	 *
	 * @return void
	 */
	public function clear_cache( ?string $type = null ): void {
		if ( $type === null ) {
			$this->data_cache = [];
		} else {
			foreach ( array_keys( $this->data_cache ) as $key ) {
				if ( strpos( $key, "_{$type}_" ) !== false ) {
					unset( $this->data_cache[ $key ] );
				}
			}
		}
	}

	/**
	 * Parse pagination query arguments.
	 *
	 * @param array $args Query arguments
	 *
	 * @return array Parsed arguments with pagination data
	 */
	public function parse_pagination_args( $args = array() ): array {
		// Get pagination and sorting values
		$order   = $this->get_order();
		$orderby = $this->get_orderby();
		$offset  = $this->get_paged() > 1 ? ( $this->get_paged() - 1 ) * $this->per_page : 0;

		// Parse pagination args
		$parsed_args = wp_parse_args( $args, array(
			'number'  => $this->per_page,
			'offset'  => $offset,
			'order'   => $order,
			'orderby' => $orderby
		) );

		/**
		 * Filters the pagination arguments
		 *
		 * @param array     $parsed_args The pagination arguments
		 * @param ListTable $this        Current table instance
		 *
		 * @return array                       Modified pagination arguments
		 */
		return apply_filters( "{$this->hook_prefix}pagination_args", $parsed_args, $this );
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		// Get columns configuration
		$columns = $this->get_columns();

		// Get hidden columns from user preferences
		$screen = get_current_screen();
		$hidden = [];

//		$screen = get_current_screen();

		if ($screen) {
			$hidden = get_user_option('manage' . $screen->id . 'columnshidden');
			if (!is_array($hidden)) {
				$hidden = array();
			}
		}

		$sortable = $this->get_sortable_columns();

		// Set up column headers with visibility info
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// First, get total counts for all statuses
		$this->get_counts();

		// Get current status
		$status = $this->get_status();
		if ( empty( $status ) || $status === 'all' ) {
			$status = 'total';
		}

		// Get the items with pagination
		$this->items = $this->get_data();

		// Set up pagination
		$this->set_pagination_args( [
			'total_items' => $this->counts[ $status ] ?? 0,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( ( $this->counts[ $status ] ?? 0 ) / $this->per_page )
		] );
	}

	/**
	 * Message to be displayed when there are no items
	 */
	public function no_items() {
		if ( isset( $this->table_config['no_items_message'] ) ) {
			echo esc_html( $this->table_config['no_items_message'] );
		} else {
			esc_html_e( 'No items found.', 'arraypress' );
		}
	}

}