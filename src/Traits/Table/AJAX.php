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

use Exception;

/**
 * Trait TableColumnTrait
 *
 * Column handling functionality for the Tables class
 */
trait AJAX {

	/**
	 * Handle all interactive column updates
	 */
	public function handle_column_update() {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [
				'message' => __( 'You must be logged in to perform this action.', 'arraypress' )
			] );
		}

		// Get parameters from request with sanitization
		$table_id    = isset( $_POST['table_id'] ) ? sanitize_key( $_POST['table_id'] ) : '';
		$column_name = isset( $_POST['column'] ) ? sanitize_key( $_POST['column'] ) : '';
		$item_id     = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$value       = isset( $_POST['value'] ) ? filter_var( $_POST['value'], FILTER_VALIDATE_BOOLEAN ) : false;
		$nonce       = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';

		// Validate required data
		if ( empty( $table_id ) || empty( $column_name ) || empty( $item_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Missing required data.', 'arraypress' )
			] );
		}

		// Verify nonce - specific to this table and item
		if ( ! wp_verify_nonce( $nonce, "table_column_update_{$table_id}_{$item_id}" ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'arraypress' )
			] );
		}

		// Get table configuration
		$table_config = $this->registered_tables[ $table_id ] ?? null;

		if ( ! $table_config ) {
			wp_send_json_error( [
				'message' => __( 'Table configuration not found.', 'arraypress' )
			] );
		}

		// Check permissions for this table
		$capability = $table_config['capability'] ?? 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'arraypress' )
			] );
		}

		// Get the column configuration
		$column_config = $table_config['columns'][ $column_name ] ?? null;

		if ( ! $column_config ) {
			wp_send_json_error( [
				'message' => __( 'Column configuration not found.', 'arraypress' )
			] );
		}

		// Find the appropriate callback - simplified logic
		$callback = null;

		// First check column-specific update callback
		if ( ! empty( $column_config['update_callback'] ) ) {
			$callback = $column_config['update_callback'];
		} // Then check for table-level update callback
		elseif ( ! empty( $table_config['callbacks']['update'] ) ) {
			$callback = $table_config['callbacks']['update'];
		}

		// If we don't have a valid callback, fail gracefully
		if ( ! $callback || ! is_callable( $callback ) ) {
			wp_send_json_error( [
				'message' => __( 'No update handler available for this action.', 'arraypress' )
			] );
		}

		// Execute the callback
		try {
			// Get the field (either from callback or column name)
			$field = $column_config['callback'] ?? $column_name;

			// Execute the callback with a data array instead of individual parameters
			$result = call_user_func( $callback, $item_id, [ $field => $value ] );

			// Process the result
			if ( is_array( $result ) ) {
				$success = isset( $result['success'] ) && $result['success'];
				$message = $result['message'] ?? '';
				$data    = $result['data'] ?? [];
			} else {
				$success = (bool) $result;
				$message = '';
				$data    = [];
			}

			// Send response
			if ( $success ) {
				wp_send_json_success( [
					'value' => $value,
					'data'  => $data
				] );
			} else {
				wp_send_json_error( [
					'message' => ! empty( $message ) ? $message : __( 'Update failed.', 'arraypress' ),
					'data'    => $data
				] );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( [
				'message' => $e->getMessage()
			] );
		}
	}

}