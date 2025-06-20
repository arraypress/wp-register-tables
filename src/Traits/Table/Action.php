<?php
/**
 * Table Action Trait
 *
 * Provides action handling functionality for the Tables class.
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
 * Trait TableActionTrait
 *
 * Action handling functionality for the Tables class
 */
trait Action {

	/**
	 * Process row actions early in WordPress lifecycle.
	 *
	 * This handles row action processing and redirects with notice parameters.
	 */
	public function process_row_actions() {
		// Only run in admin and if we have the required parameters
		if ( ! is_admin() || empty( $_GET['action'] ) || empty( $_GET['page'] ) || empty( $_GET['id'] ) ) {
			return;
		}

		$action = sanitize_key( $_GET['action'] );
		$page   = sanitize_key( $_GET['page'] );
		$id     = absint( $_GET['id'] );

		// Find the table ID for this page
		$table_id = null;
		foreach ( $this->registered_tables as $t_id => $config ) {
			if ( $config['slug'] === $page ) {
				$table_id = $t_id;
				break;
			}
		}

		// If no matching table found, return
		if ( ! $table_id || ! isset( $this->registered_tables[ $table_id ] ) ) {
			return;
		}

		$table_config = $this->registered_tables[ $table_id ];

		// Define excluded actions that should just be links, not processed actions
		$excluded_actions = [ 'add', 'edit', 'view' ];

		// Allow customizing excluded actions via config
		if ( isset( $table_config['excluded_row_actions'] ) && is_array( $table_config['excluded_row_actions'] ) ) {
			$excluded_actions = array_merge( $excluded_actions, $table_config['excluded_row_actions'] );
		}

		// If this is an excluded action, just return without processing
		if ( in_array( $action, $excluded_actions ) ) {
			return;
		}

		// Check if the action exists in actions config
		if (empty($table_config['actions'][$action])) {
			return;
		}

		$action_config = $table_config['actions'][$action];

		// If this action is marked as link-only, don't process it
		if ( ! empty( $action_config['link_only'] ) && $action_config['link_only'] === true ) {
			return;
		}

		// Verify nonce if present
		if ( ! empty( $action_config['nonce'] ) ) {
			$nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_key( $_GET['_wpnonce'] ) : '';
			$nonce_action = str_replace( '{id}', (string) $id, $action_config['nonce'] );

			if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
				wp_die( __( 'Security check failed', 'arraypress' ) );
			}
		}

		// Check capabilities
		$capability = $table_config['capability'] ?? 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'arraypress' ) );
		}

		// Process the action
		$callback = null;
		if ( ! empty( $action_config['callback'] ) && is_callable( $action_config['callback'] ) ) {
			$callback = $action_config['callback'];
		}

		// Process the action using the integrated notice methods
		$notice = $this->process_row_action( $table_id, $action, $id, $callback );

		// Setup URL for redirect with notice parameters
		$redirect_url = $this->add_notice_to_url(
			$this->get_url( $table_id ),
			$table_id,
			$notice
		);

		// Redirect with notice parameters
		wp_safe_redirect( $redirect_url );
		exit;
	}

}