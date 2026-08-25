<?php
/**
 * Fetching Rows
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\RegisterTables\Row;

use ArrayPress\RegisterTables\Request;

/**
 * Asking the consumer's callback for rows, and for counts.
 *
 * The library never touches a database. It assembles the arguments — paging,
 * ordering, the search term, whatever the filters are set to — hands them to
 * a callback and formats whatever comes back, which is what lets the same
 * table class sit in front of a custom table, a post type or a remote API.
 */
trait QueryBuilder {

    /**
     * Get table data
     *
     * Retrieves items for display by calling the configured get_items callback.
     * Applies pagination, sorting, search, status filter, custom filters, and
     * whitelisted query args.
     *
     * When a search_callback is configured, it receives the search term and
     * can return an array of query args (e.g., ['customer_id' => 42]) which
     * are merged into the query. If the callback returns a non-empty array,
     * the raw search term is not passed to the query. If the callback returns
     * an empty array, the default search behavior is used as a fallback.
     *
     * @return array Array of item objects to display.
     * @since 1.0.0
     *
     */
    public function get_data(): array {
        $args = [];

        // Add pagination and sorting
        $args = array_merge( $args, $this->parse_pagination_args() );

        // Add search (with optional callback resolution)
        $args = array_merge( $args, $this->resolve_search( $this->get_search() ) );

        // Add status filter
        if ( ! empty( $this->status ) ) {
            $args['status'] = $this->status;
        }

        // Process custom filters
        foreach ( $this->config['filters'] as $filter_key => $filter ) {
            if ( ! Request::has( $filter_key ) ) {
                continue;
            }

            $value = Request::text( $filter_key );

            if ( empty( $value ) ) {
                continue;
            }

            // Check for custom apply callback
            if ( is_array( $filter ) && isset( $filter['apply_callback'] ) && is_callable( $filter['apply_callback'] ) ) {
                call_user_func_array( $filter['apply_callback'], [ &$args, $value ] );
            } else {
                // Simple assignment
                $args[ $filter_key ] = $value;
            }
        }

        // Pass through whitelisted query args from URL
        $args = $this->apply_query_args( $args );

        /**
         * Filter query arguments for a specific table
         *
         * @param array $args   Query arguments.
         * @param array $config Table configuration.
         *
         * @since 1.0.0
         *
         */
        $args = apply_filters( "arraypress_table_query_args_{$this->id}", $args, $this->config );

        // Call the get_items callback
        if ( isset( $this->config['callbacks']['get_items'] ) && is_callable( $this->config['callbacks']['get_items'] ) ) {
            return call_user_func( $this->config['callbacks']['get_items'], $args );
        }

        return [];
    }

    /**
     * Get status counts
     *
     * Retrieves and caches item counts per status. Used for view tabs
     * and determining total items for pagination.
     *
     * @return array Associative array of status => count pairs.
     *               Always includes 'total' key.
     * @since 1.0.0
     *
     */
    public function get_counts(): array {
        // Return cached counts if available
        if ( ! empty( $this->counts ) ) {
            return $this->counts;
        }

        // Call the get_counts callback
        if ( isset( $this->config['callbacks']['get_counts'] ) && is_callable( $this->config['callbacks']['get_counts'] ) ) {
            $this->counts = call_user_func( $this->config['callbacks']['get_counts'] );
        }

        // Deliberately not invented. A fabricated total of nought is
        // indistinguishable from a real one, so a table with no counts
        // callback looked like a table that had counted itself and found
        // nothing — and the get_total() fallback below it never ran.
        return $this->counts;
    }

    /**
     * Prepare items for display
     *
     * Main method called before rendering. Sets up column headers,
     * fetches data, and configures pagination.
     *
     * @since 1.0.0
     */
    public function prepare_items(): void {
        // Set up column headers
        $this->_column_headers = [
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
			$this->get_primary_column_name(),
        ];

        // Fetch counts (for status views) and items
        $this->get_counts();
        $this->items = $this->get_data();

        // Determine total for pagination
        $total = $this->get_filtered_total();

        // Set pagination
        $this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( $total / $this->per_page ),
        ] );
    }

    /**
     * Get a row's id
     *
     * Whatever shape the row is: a get_id() method, an id property, or an
     * `id` key. method_exists() throws a TypeError on an array, so this used
     * to fatal for any table whose query returned arrays.
     *
     * @param mixed $item The row.
     *
     * @return int Item ID.
     * @since 1.0.0
     *
     */
    private function get_item_id( $item ): int {
        return Row::id( $item );
    }

    /**
     * Parse pagination arguments
     *
     * Extracts and sanitizes pagination/sorting parameters from the
     * request for use in database queries.
     *
     * @return array Query arguments with number, offset, order, orderby keys.
     * @since 1.0.0
     *
     */
    private function parse_pagination_args(): array {
        $paged  = max( 1, Request::count( 'paged', 1 ) );
        $offset = $paged > 1 ? $this->per_page * ( $paged - 1 ) : 0;

        $orderby = Request::key( 'orderby', (string) $this->config['orderby'] );
        $order   = strtoupper( Request::key( 'order', (string) $this->config['order'] ) );

        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        if ( empty( $orderby ) ) {
            $orderby = 'id';
        }

        return [
			'number'  => $this->per_page,
			'offset'  => $offset,
			'order'   => $order,
			'orderby' => $orderby,
        ];
    }
}
