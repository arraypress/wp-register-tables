<?php
/**
 * Core Table Instance Trait
 *
 * Provides the core functionality for the Table_Instance class.
 *
 * @package     SugarCart\Admin
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\ListTable;

// Exit if accessed directly
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Trait_Table_Instance_Data
 *
 * Data handling functionality for the Table_Instance class
 */
trait BulkActions {

	/**
	 * Get bulk actions
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		$bulk_actions = [];

		if ( ! empty( $this->table_config['bulk_actions'] ) && is_array( $this->table_config['bulk_actions'] ) ) {
			foreach ( $this->table_config['bulk_actions'] as $action_id => $action_config ) {
				$bulk_actions[ $action_id ] = is_array( $action_config ) && isset( $action_config['title'] )
					? $action_config['title']
					: $action_config;
			}
		}

		return apply_filters( "{$this->hook_prefix}bulk_actions", $bulk_actions, $this );
	}

	/**
	 * Process bulk action
	 */
	public function process_bulk_action() {
		// Get the current action - check both action and action2 (bottom dropdown)
		$action = '';
		if ( ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] != '-1' ) {
			$action = $_REQUEST['action'];
		} elseif ( ! empty( $_REQUEST['action2'] ) && $_REQUEST['action2'] != '-1' ) {
			$action = $_REQUEST['action2'];
		}

		if ( empty( $action ) ) {
			return;
		}

		// Verify nonce
		$nonce_name = 'bulk-' . $this->_args['plural'];
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], $nonce_name ) ) {
			return;
		}

		// Check capabilities
		$capability = $this->table_config['capability'] ?? 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		// Get the IDs
		$singular = $this->_args['singular'];
		$ids      = isset( $_REQUEST[ $singular ] ) ? wp_parse_id_list( (array) $_REQUEST[ $singular ] ) : array();
		if ( empty( $ids ) ) {
			return;
		}

		// Get the appropriate callback
		$callback = null;
		if ( isset( $this->table_config['bulk_actions'][ $action ]['callback'] ) &&
		     is_callable( $this->table_config['bulk_actions'][ $action ]['callback'] ) ) {
			$callback = $this->table_config['bulk_actions'][ $action ]['callback'];
		} elseif ( isset( $this->table_config['callbacks']['process'] ) &&
		           is_callable( $this->table_config['callbacks']['process'] ) ) {
			$callback = $this->table_config['callbacks']['process'];
		}

		// Process each ID if we have a callback
		if ( $callback ) {
			foreach ( $ids as $id ) {
				try {
					if ( isset( $this->table_config['bulk_actions'][ $action ]['callback'] ) ) {
						call_user_func( $callback, $id );
					} else {
						call_user_func( $callback, $action, $id );
					}
				} catch ( Exception $e ) {
					// Silent error handling
				}
			}
		}
	}

}