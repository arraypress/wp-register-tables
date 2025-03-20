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

namespace ArrayPress\WP\Register\Traits;

// Exit if accessed directly
use ArrayPress\WP\Register\ListTable;

defined( 'ABSPATH' ) || exit;

/**
 * Data Trait
 *
 * Data handling functionality for the Table_Instance class
 */
trait Data {

	/**
	 * Get data for the table
	 *
	 * @return array Array of data items for the table
	 */
	public function get_data(): array {
		/**
		 * Fires before retrieving table data
		 *
		 * @param ListTable $this Current table instance
		 */
		do_action( "{$this->hook_prefix}before_get_data", $this );

		// Check for data callback function
		if ( isset( $this->table_config['callbacks']['get'] ) && is_callable( $this->table_config['callbacks']['get'] ) ) {
			// Build base query args
			$args = $this->build_query_args();

			// Add pagination and sorting
			$this->args = $this->parse_pagination_args( $args );

			// Get data using callback (with pagination)
			$data = call_user_func( $this->table_config['callbacks']['get'], $this->args );

			/**
			 * Filters the table data
			 *
			 * @param array         $data The table data retrieved from the callback
			 * @param array         $args The query arguments used to retrieve the data
			 * @param ListTable $this Current table instance
			 *
			 * @return array                Modified table data
			 */
			$data = apply_filters( "{$this->hook_prefix}data", $data, $this->args, $this );

			/**
			 * Fires after retrieving table data
			 *
			 * @param ListTable $this Current table instance
			 * @param array         $data The table data retrieved
			 */
			do_action( "{$this->hook_prefix}after_get_data", $this, $data );

			return $data;
		}

		/**
		 * Fires after attempting to retrieve table data when no data was found
		 *
		 * @param ListTable $this Current table instance
		 * @param array         $data Empty array as no data was retrieved
		 */
		do_action( "{$this->hook_prefix}after_get_data", $this, [] );

		// Return empty array if no callback
		return [];
	}

	/**
	 * Get counts for different statuses
	 */
	public function get_counts() {
		/**
		 * Fires before retrieving count data
		 *
		 * @param ListTable $this Current table instance
		 */
		do_action( "{$this->hook_prefix}before_get_counts", $this );

		if ( isset( $this->table_config['callbacks']['count'] ) && is_callable( $this->table_config['callbacks']['count'] ) ) {
			// Build base query args
			$args = $this->build_query_args();

			// Get counts using callback
			$this->counts = call_user_func( $this->table_config['callbacks']['count'], $args );

			/**
			 * Filters the status counts retrieved from the callback
			 *
			 * @param array         $counts The counts for different statuses
			 * @param array         $args   The query arguments used
			 * @param ListTable $this   Current table instance
			 *
			 * @return array                  Modified counts
			 */
			$this->counts = apply_filters( "{$this->hook_prefix}counts", $this->counts, $args, $this );
		} else {
			// Default count is just the total number of items
			$data         = $this->get_data();
			$this->counts = [ 'total' => is_countable( $data ) ? count( $data ) : 0 ];

			/**
			 * Filters the default counts when no count callback is defined
			 *
			 * @param array         $counts The default counts (only 'total' is set)
			 * @param ListTable $this   Current table instance
			 *
			 * @return array                  Modified counts
			 */
			$this->counts = apply_filters( "{$this->hook_prefix}default_counts", $this->counts, $this );
		}

		// Ensure total is set
		$this->ensure_total_count();

		/**
		 * Fires after retrieving count data
		 *
		 * @param ListTable $this   Current table instance
		 * @param array         $counts The counts retrieved for different statuses
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
		 * @param array         $parsed_args The pagination arguments
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
		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();

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