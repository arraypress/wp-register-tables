<?php
/**
 * Table Instance UI Trait
 *
 * Provides UI functionality for the Table_Instance class.
 *
 * @package     SugarCart\Admin
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register\Traits;

// Exit if accessed directly
use Elementify\Create;

defined( 'ABSPATH' ) || exit;

/**
 * Trait_Table_Instance_UI
 *
 * UI functionality for the Table_Instance class
 */
trait Views {

	/**
	 * Generate views (status tabs) with improved "current" selection logic
	 *
	 * @return array Views array
	 */
	public function get_views(): array {
		// Get the current status
		$current = $this->get_status();

		// Determine if any filters/search are active
		$has_active_filters = $this->has_active_filters();
		$search             = $this->get_search();
		$has_search         = ! empty( $search );

		// Create clean base URL (removing all filter parameters)
		$base_url = $this->get_clean_url();

		// Get status counts directly from the callback
		if ( isset( $this->table_config['callbacks']['count'] ) && is_callable( $this->table_config['callbacks']['count'] ) ) {
			$status_counts = call_user_func( $this->table_config['callbacks']['count'], [] );
		} else {
			$status_counts = [ 'total' => count( $this->items ) ];
		}

		// Determine whether "All" view should be marked as current
		// Only mark a view as current if there are no filters/search active
		$all_current = ( ! $has_active_filters && ! $has_search &&
		                 ( empty( $current ) || $current === 'all' ) );

		$class = $all_current ? ' class="current"' : '';

		// All view with formatted count
		$count = '&nbsp;<span class="count">(' . number_format_i18n( $status_counts['total'] ) . ')</span>';
		$label = __( 'All', 'sugarcart' ) . $count;
		$views = array(
			'all' => sprintf( '<a href="%s"%s>%s</a>', esc_url( $base_url ), $class, $label ),
		);

		// Remove total from counts array
		$counts = $status_counts;
		unset( $counts['total'] );

		// Check if we have predefined views in the config
		if ( isset( $this->table_config['views'] ) && is_array( $this->table_config['views'] ) ) {
			// Add predefined views
			foreach ( $this->table_config['views'] as $view_id => $view_config ) {
				// Skip 'all' as we already added it
				if ( $view_id === 'all' ) {
					continue;
				}

				// Skip views with 'show' => false
				if ( isset( $view_config['show'] ) && $view_config['show'] === false ) {
					continue;
				}

				// Get view URL with parameters
				$params   = $view_config['params'] ?? [ 'status' => $view_id ];
				$view_url = add_query_arg( $params, $base_url );

				// Determine if this view is current
				$view_current = ( ! $has_active_filters && ! $has_search && $current === $view_id );
				if ( isset( $view_config['default'] ) && $view_config['default'] && empty( $current ) ) {
					$view_current = true;
				}

				$class = $view_current ? ' class="current"' : '';

				// Get count if available
				$count_display = '';
				if ( isset( $counts[ $view_id ] ) ) {
					$count_display = '&nbsp;<span class="count">(' . number_format_i18n( absint( $counts[ $view_id ] ) ) . ')</span>';
				} elseif ( isset( $view_config['badge'] ) && $view_config['badge'] === 'count' ) {
					// Try to get count from the database
					if ( isset( $this->counts[ $view_id ] ) ) {
						$count_display = '&nbsp;<span class="count">(' . number_format_i18n( absint( $this->counts[ $view_id ] ) ) . ')</span>';
					}
				}

				// Get view label
				$view_label = $view_config['title'] ?? ucfirst( $view_id );
				$label      = $view_label . $count_display;

				// Add to views
				$views[ $view_id ] = sprintf( '<a href="%s"%s>%s</a>', esc_url( $view_url ), $class, $label );
			}
		} else if ( ! empty( $counts ) ) {
			foreach ( $counts as $status => $count ) {
				// Skip special keys
				if ( $status === 'current' || $status === 'filtered' ) {
					continue;
				}

				$count_url = add_query_arg( array(
					'status' => sanitize_key( $status ),
				), $base_url );

				// Only mark as current if exactly matching the status and no other filters are active
				$status_current = ( $current === $status && ! $has_active_filters && ! $has_search );
				$class          = $status_current ? ' class="current"' : '';

				// Format the count with number_format_i18n
				$count_display = '&nbsp;<span class="count">(' . number_format_i18n( absint( $count ) ) . ')</span>';

				// Get status label - first try config then fallback to uppercased status
				$status_label = '';

				if ( isset( $this->table_config['status_labels'][ $status ] ) ) {
					$status_label = $this->table_config['status_labels'][ $status ];
				} elseif ( isset( $this->table_config['status_label_callback'] ) && is_callable( $this->table_config['status_label_callback'] ) ) {
					$status_label = call_user_func( $this->table_config['status_label_callback'], $status );
				} else {
					// Default to capitalized status
					$status_label = ucfirst( $status );
				}

				$label            = $status_label . $count_display;
				$views[ $status ] = sprintf( '<a href="%s"%s>%s</a>', esc_url( $count_url ), $class, $label );
			}
		}

		return apply_filters( "{$this->hook_prefix}views", $views, $this );
	}

}