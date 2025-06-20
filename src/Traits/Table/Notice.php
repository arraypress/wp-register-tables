<?php
/**
 * Table Notice Trait
 *
 * Provides notice handling functionality for the Tables class.
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
 * Trait TableNoticeTrait
 *
 * Notice handling functionality for the Tables class
 */
trait Notice {

	/**
	 * Create a notice for an action result
	 *
	 * @param string      $table_id Table ID
	 * @param string      $action   Action name
	 * @param int         $id       Item ID
	 * @param bool        $success  Whether the action was successful
	 * @param string|null $message  Optional custom message
	 *
	 * @return array Notice data
	 */
	private function create_notice( string $table_id, string $action, int $id, bool $success = true, ?string $message = null ): array {
		$table_config = $this->registered_tables[ $table_id ] ?? [];

		// Build notice data
		$notice = [
			'type'    => $success ? 'success' : 'error',
			'action'  => $action,
			'id'      => $id,
			'success' => $success
		];

		// Set message based on priority:
		// 1. Custom message if provided
		// 2. Action-specific message from config
		// 3. Default message based on action and result
		if ( ! empty( $message ) ) {
			$notice['message'] = $message;
		} elseif ( isset( $table_config['row_actions'][ $action ] ) ) {
			$action_config = $table_config['row_actions'][ $action ];

			if ( $success && ! empty( $action_config['success_notice'] ) ) {
				$notice['message'] = $action_config['success_notice'];
			} elseif ( ! $success && ! empty( $action_config['failure_notice'] ) ) {
				$notice['message'] = $action_config['failure_notice'];
			} else {
				$notice['message'] = $this->get_default_message( $table_config, $action, $success );
			}
		} else {
			$notice['message'] = $this->get_default_message( $table_config, $action, $success );
		}

		return $notice;
	}

	/**
	 * Process row action and generate notice
	 *
	 * @param string        $table_id Table ID
	 * @param string        $action   Action name
	 * @param int           $id       Item ID
	 * @param callable|null $callback Custom callback to handle action
	 *
	 * @return array Notice data
	 */
	private function process_row_action( string $table_id, string $action, int $id, ?callable $callback = null ): array {
		$table_config = $this->registered_tables[ $table_id ] ?? [];
		$result       = false;
		$message      = '';

		try {
			// Execute callback
			if ( $callback && is_callable( $callback ) ) {
				$result = call_user_func( $callback, $id );
			} elseif ( ! empty( $table_config['row_actions'][ $action ]['callback'] ) &&
			           is_callable( $table_config['row_actions'][ $action ]['callback'] ) ) {
				$result = call_user_func( $table_config['row_actions'][ $action ]['callback'], $id );
			} elseif ( isset( $table_config['callbacks']['process'] ) &&
			           is_callable( $table_config['callbacks']['process'] ) ) {
				$result = call_user_func( $table_config['callbacks']['process'], $action, $id );
			}

			// Handle result object/array with success/message properties
			if ( is_array( $result ) && isset( $result['success'] ) ) {
				$message = $result['message'] ?? '';
				$result  = $result['success'];
			}
		} catch ( \Exception $e ) {
			$message = $e->getMessage();
			$result  = false;
		}

		// Create the notice
		return $this->create_notice( $table_id, $action, $id, (bool) $result, $message );
	}

	/**
	 * Add notice parameters to URL
	 *
	 * @param string $url      Base URL
	 * @param string $table_id Table ID
	 * @param array  $notice   Notice data
	 *
	 * @return string URL with notice parameters
	 */
	private function add_notice_to_url( string $url, string $table_id, array $notice ): string {
		return add_query_arg( [
			'notice'      => $table_id . '_' . $notice['action'],
			'notice_type' => $notice['type'],
			'result'      => $notice['success'] ? 'success' : 'error',
		], $url );
	}

	/**
	 * Display admin notice based on URL parameters
	 */
	public function display_admin_notices() {
		// Get current table ID
		$table_id = $this->get_current_table_id();

		// If we have a table ID and notice parameters, display notice
		if ( ! $table_id || ! isset( $this->registered_tables[ $table_id ] ) ||
		     empty( $_GET['notice'] ) || empty( $_GET['notice_type'] ) || empty( $_GET['result'] ) ) {
			return;
		}

		$notice_code = sanitize_key( $_GET['notice'] );
		$notice_type = sanitize_key( $_GET['notice_type'] );
		$result      = sanitize_key( $_GET['result'] );

		// Check if this notice belongs to our table
		$prefix = $table_id . '_';
		if ( strpos( $notice_code, $prefix ) !== 0 ) {
			return;
		}

		// Extract action from notice code
		$action = substr( $notice_code, strlen( $prefix ) );

		// Get appropriate message
		$table_config = $this->registered_tables[ $table_id ];
		$message      = $this->get_message_for_action( $table_config, $action, $result === 'success' );

		if ( ! empty( $message ) ) {
			// Create the notice HTML
			$class = 'notice notice-' . $notice_type . ' is-dismissible';
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $message ) );

			// Fire action for custom notice handling
			do_action( "table_{$table_id}_admin_notice", $notice_type, $table_config );
		}
	}

	/**
	 * Get default message for an action
	 *
	 * @param array  $table_config Table configuration
	 * @param string $action       Action name
	 * @param bool   $success      Whether the action was successful
	 *
	 * @return string Message text
	 */
	private function get_default_message( array $table_config, string $action, bool $success ): string {
		$item_name = $table_config['singular'] ?? 'item';

		if ( $success ) {
			return sprintf(
				__( '%s action completed successfully.', 'arraypress' ),
				ucfirst( $action )
			);
		} else {
			return sprintf(
				__( 'Failed to perform %s action on %s.', 'arraypress' ),
				$action,
				$item_name
			);
		}
	}

	/**
	 * Get message for a specific action
	 *
	 * @param array  $table_config Table configuration
	 * @param string $action       Action name
	 * @param bool   $success      Whether the action was successful
	 *
	 * @return string Message text
	 */
	private function get_message_for_action( array $table_config, string $action, bool $success ): string {
		// Check for specific success/failure message in row_actions config
		if ( isset( $table_config['row_actions'][ $action ] ) ) {
			$action_config = $table_config['row_actions'][ $action ];

			if ( $success && ! empty( $action_config['success_notice'] ) ) {
				return $action_config['success_notice'];
			} elseif ( ! $success && ! empty( $action_config['failure_notice'] ) ) {
				return $action_config['failure_notice'];
			}
		}

		// Fall back to checking notices array in config
		$notice_key = $action . '_' . ( $success ? 'success' : 'error' );
		if ( isset( $table_config['notices'][ $notice_key ] ) ) {
			return $table_config['notices'][ $notice_key ];
		}

		// Default message
		return $this->get_default_message( $table_config, $action, $success );
	}

}