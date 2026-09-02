<?php
/**
 * Search And Filtering
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\RegisterTables\Manager;
use ArrayPress\RegisterTables\Request;

/**
 * What the request is asking for, and what that does to the counts.
 *
 * The awkward part is the total. Pagination needs a count of matching rows,
 * and a table with filters applied cannot use the unfiltered count without
 * showing pages that are not there — which is exactly the bug that had a
 * fourteen-row table reporting three pages and drawing five rows.
 */
trait Filtering {

    /**
     * Get total count for pagination, accounting for active filters
     *
     * When search or custom filters are active, we need to get the actual
     * filtered count rather than using the cached status counts.
     *
     * @return int Total items matching current filters.
     * @since 1.0.0
     *
     */
    private function get_filtered_total(): int {
        // Check if any filters are active (besides status)
        $has_search  = ! empty( $this->get_search() );
        $has_filters = $this->has_active_filters();

        // If no filters active, use cached counts
        if ( ! $has_search && ! $has_filters ) {
            if ( ! empty( $this->status ) && isset( $this->counts[ $this->status ] ) ) {
                return (int) $this->counts[ $this->status ];
            }

            if ( isset( $this->counts['total'] ) ) {
                return (int) $this->counts['total'];
            }

            // A table with no status views has no reason to supply a counts
            // callback, and one that supplies only get_total() used to be
            // told it had nothing: "0 items" above a full page of them, and
            // no pagination, because the total the pager works from was
            // nought.
            if ( isset( $this->config['callbacks']['get_total'] ) && is_callable( $this->config['callbacks']['get_total'] ) ) {
                return (int) call_user_func( $this->config['callbacks']['get_total'], [] );
            }

            return 0;
        }

        // Filters are active — get count from callback or count items
        return $this->get_filtered_count();
    }

    /**
     * Check if any custom filters or whitelisted query args are currently active
     *
     * Checks both dropdown filters and query_args config for active URL
     * parameters that would affect the result set.
     *
     * @return bool True if any filters or query args have values set.
     * @since 1.0.0
     *
     */
    private function has_active_filters(): bool {
        // Check dropdown filters
        foreach ( $this->config['filters'] as $filter_key => $filter ) {
            if ( '' !== $this->filter_value( $filter_key, $filter ) ) {
                return true;
            }
        }

        // Check whitelisted query args
        if ( $this->has_active_query_args() ) {
            return true;
        }

        return false;
    }

    /**
     * Get count of items matching current filters
     *
     * Builds query args with all active filters, whitelisted query args,
     * and gets the count either from a dedicated callback or by querying
     * without pagination.
     *
     * @return int Filtered item count.
     * @since 1.0.0
     *
     */
    private function get_filtered_count(): int {
        $args = [];

        // Add search (with optional callback resolution)
        $args = array_merge( $args, $this->resolve_search( $this->get_search() ) );

        // Add status filter
        if ( ! empty( $this->status ) ) {
            $args['status'] = $this->status;
        }

        // Process custom filters
        foreach ( $this->config['filters'] as $filter_key => $filter ) {
            $value = $this->filter_value( $filter_key, $filter );

            if ( $value === '' ) {
                continue;
            }

            if ( is_array( $filter ) && isset( $filter['apply_callback'] ) && is_callable( $filter['apply_callback'] ) ) {
                call_user_func_array( $filter['apply_callback'], [ &$args, $value ] );
            } else {
                $args[ $filter_key ] = $value;
            }
        }

        // Pass through whitelisted query args from URL
        $args = $this->apply_query_args( $args );

        // Request count only (no pagination)
        $args['count']  = true;
        $args['number'] = 0;

        // Try get_items callback with count flag
        if ( isset( $this->config['callbacks']['get_items'] ) && is_callable( $this->config['callbacks']['get_items'] ) ) {
            $result = call_user_func( $this->config['callbacks']['get_items'], $args );

            if ( is_int( $result ) ) {
                return $result;
            }
        }

        // Fallback: if we already have items, use that count as minimum
        if ( ! empty( $this->items ) ) {
            return count( $this->items );
        }

        return 0;
    }

    /**
     * Get search query
     *
     * Retrieves and sanitizes the search term from the request.
     *
     * @return string Sanitized search query.
     * @since 1.0.0
     *
     */
    private function get_search(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a search term is a view, not an action.
        return Request::text( 's' );
    }

    /**
     * What a filter is set to, if it is set to something it offers.
     *
     * A filter that lists its options is a select, and a select constrains
     * a browser but nothing constrains a URL. The value goes into the
     * consumer's query under the filter's own key, so one the dropdown never
     * offered is treated as the dropdown left on "All". A filter with no
     * options -- or with a callback that produces them -- takes what it is
     * given, sanitised as text.
     *
     * @param string $filter_key The filter's key, which is also its query argument.
     * @param mixed  $filter     Its configuration.
     *
     * @return string The value, or an empty string when there is none to apply.
     */
    private function filter_value( string $filter_key, $filter ): string {
        $value = Request::text( $filter_key );

        if ( '' === $value || ! is_array( $filter ) || ! isset( $filter['options'] ) || ! is_array( $filter['options'] ) ) {
            return $value;
        }

        return array_key_exists( $value, $filter['options'] ) ? $value : '';
    }

    /**
     * Get current page URL
     *
     * Builds a clean URL for the current page with current filters,
     * status, search, and whitelisted query args preserved.
     *
     * @return string Current admin page URL with query args.
     * @since 1.0.0
     *
     */
    private function get_current_url(): string {
        $url = Manager::page_url( $this->config );

        // Preserve status
        if ( Request::filled( 'status' ) ) {
            $url = add_query_arg( 'status', Request::key( 'status' ), $url );
        }

        // Preserve search
        if ( Request::filled( 's' ) ) {
            $url = add_query_arg( 's', Request::text( 's' ), $url );
        }

        // Preserve dropdown filters
        if ( ! empty( $this->config['filters'] ) ) {
            foreach ( $this->config['filters'] as $filter_key => $filter ) {
                if ( Request::filled( $filter_key ) ) {
                    $url = add_query_arg( $filter_key, Request::text( $filter_key ), $url );
                }
            }
        }

        // Preserve whitelisted query args
        foreach ( $this->config['query_args'] as $key => $value ) {
            $arg_key = is_numeric( $key ) ? $value : $key;

            if ( Request::filled( $arg_key ) ) {
                $url = add_query_arg( $arg_key, Request::text( $arg_key ), $url );
            }
        }

        return $url;
    }

    /**
     * Resolve search term into query arguments
     *
     * If a search_callback is configured, calls it with the search term.
     * The callback can return an array of query args (e.g., ['customer_id' => 42])
     * which replace the raw search term. If the callback returns an empty array,
     * the raw search term is used as a fallback.
     *
     * @param string $search Search term from the request.
     *
     * @return array Query arguments to merge. Contains either resolved args
     *               from the callback or ['search' => $term] as fallback.
     * @since 1.0.0
     *
     */
    private function resolve_search( string $search ): array {
        if ( empty( $search ) ) {
            return [];
        }

        // Try the search callback first
        if ( isset( $this->config['callbacks']['search_callback'] )
            && is_callable( $this->config['callbacks']['search_callback'] ) ) {
            $resolved = call_user_func( $this->config['callbacks']['search_callback'], $search );

            if ( ! empty( $resolved ) && is_array( $resolved ) ) {
                return $resolved;
            }
        }

        // Fallback to raw search
        return [ 'search' => $search ];
    }

    /**
     * Apply whitelisted query args from URL
     *
     * Processes the query_args config to pass URL parameters directly
     * to the query. Supports both simple and advanced formats:
     *
     * Simple:   ['customer_id', 'product_id']
     * Advanced: ['customer_id' => 'absint', 'discount_code' => 'sanitize_key']
     * Mixed:    ['customer_id' => 'absint', 'product_id']
     *
     * Default sanitizer is sanitize_key when not specified.
     *
     * @param array $args Current query args.
     *
     * @return array Modified query args with whitelisted URL params applied.
     * @since 1.0.0
     *
     */
    private function apply_query_args( array $args ): array {
        foreach ( $this->config['query_args'] as $key => $value ) {
            if ( is_numeric( $key ) ) {
                $arg_key   = $value;
                $sanitizer = 'sanitize_key';
            } else {
                $arg_key   = $key;
                $sanitizer = is_callable( $value ) ? $value : 'sanitize_key';
            }

            if ( Request::filled( $arg_key ) ) {
                // Unslashed before the consumer's sanitizer sees it: it is
                // theirs to validate, not to remember WordPress's slashing.
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $sanitizer is the sanitizer.
                $args[ $arg_key ] = call_user_func( $sanitizer, Request::text( $arg_key ) );
            }
        }

        return $args;
    }

    /**
     * Check if any whitelisted query args are active in the URL
     *
     * Inspects the query_args config and checks whether any of the
     * whitelisted parameter names are present and non-empty in the
     * current request.
     *
     * @return bool True if any query args have values set.
     * @since 1.0.0
     *
     */
    private function has_active_query_args(): bool {
        foreach ( $this->config['query_args'] as $key => $value ) {
            $arg_key = is_numeric( $key ) ? $value : $key;

            if ( Request::filled( $arg_key ) ) {
                return true;
            }
        }

        return false;
    }
}
