<?php
/**
 * Admin Tables Notice Handler
 *
 * Simplified notice handling for admin tables without transients
 *
 * @package     SugarCart\Admin
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Table_Notices
 *
 * Manages admin notices for table actions without using transients
 *
 * @since 1.0.0
 */
class TableNotices {

	/**
	 * Table ID
	 *
	 * @var string
	 */
	private string $table_id;

	/**
	 * Table configuration
	 *
	 * @var array
	 */
	private array $table_config;

	/**
	 * Standard notice types
	 *
	 * @var array
	 */
	private array $notice_types = [
		'success' => 'success',
		'error'   => 'error',
		'warning' => 'warning',
		'info'    => 'info'
	];

	/**
	 * Constructor
	 *
	 * @param string $table_id     Table ID
	 * @param array  $table_config Table configuration
	 */
	public function __construct( string $table_id, array $table_config ) {
		$this->table_id     = $table_id;
		$this->table_config = $table_config;
	}

	/**
	 * Process row action and generate notice
	 *
	 * @param string        $action   Action name
	 * @param int           $id       Item ID
	 * @param callable|null $callback Custom callback to handle action
	 *
	 * @return array Notice data
	 */
	public function process_row_action( string $action, int $id, ?callable $callback = null ): array {
		// Start with default notice structure
		$notice = [
			'type'     => 'error',
			'message'  => '',
			'table_id' => $this->table_id,
			'action'   => $action
		];

		$result        = false;
		$error_message = '';

		// Get action config
		$action_config = $this->table_config['row_actions'][ $action ] ?? [];

		try {
			// Execute callback
			if ( $callback && is_callable( $callback ) ) {
				$result = call_user_func( $callback, $id );
			} elseif ( ! empty( $action_config['callback'] ) && is_callable( $action_config['callback'] ) ) {
				$result = call_user_func( $action_config['callback'], $id );
			} elseif ( isset( $this->table_config['callbacks']['process'] ) && is_callable( $this->table_config['callbacks']['process'] ) ) {
				$result = call_user_func( $this->table_config['callbacks']['process'], $action, $id );
			}
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
			$result        = false;
		}

		// Set notice type based on result
		$notice['type']   = $result ? 'success' : 'error';
		$notice['result'] = $result ? 'success' : 'error';

		// Determine message text
		if ( $result && ! empty( $action_config['success_notice'] ) ) {
			$notice['message'] = $action_config['success_notice'];
		} elseif ( ! $result && ! empty( $action_config['failure_notice'] ) ) {
			$notice['message'] = $action_config['failure_notice'];
		} elseif ( ! $result && ! empty( $error_message ) ) {
			$notice['message'] = $error_message;
		} else {
			// Default notices
			$item_name = $this->table_config['singular'] ?? 'item';
			if ( $result ) {
				$notice['message'] = sprintf(
					__( '%s action completed successfully.', 'sugarcart' ),
					ucfirst( $action )
				);
			} else {
				$notice['message'] = sprintf(
					__( 'Failed to perform %s action on %s.', 'sugarcart' ),
					$action,
					$item_name
				);
			}
		}

		return $notice;
	}

	/**
	 * Add notice parameters to URL using simplified code format
	 *
	 * @param string $url    Base URL
	 * @param array  $notice Notice data
	 *
	 * @return string URL with notice parameters
	 */
	public function add_notice_to_url( string $url, array $notice ): string {
		$action = $notice['action'] ?? '';
		$result = $notice['result'] ?? 'error';

		// Use notice code format: action_result
		$notice_code = ! empty( $action ) ? "{$action}_{$result}" : 'general_notice';

		return add_query_arg( [
			'notice_type' => $notice['type'] ?? 'info',
			'notice'      => $notice_code
		], $url );
	}

	/**
	 * Display admin notice based on URL parameters
	 *
	 * @return void
	 */
	public function display_notice_from_url(): void {
		// Check for notice parameters in URL
		if ( empty( $_GET['notice_type'] ) || empty( $_GET['notice'] ) ) {
			return;
		}

		$notice_type = sanitize_key( $_GET['notice_type'] );
		$notice_code = sanitize_key( $_GET['notice'] );

		// Valid notice types
		if ( ! isset( $this->notice_types[ $notice_type ] ) ) {
			$notice_type = 'info';
		}

		// Get message based on notice code
		$message = $this->get_message_for_notice_code( $notice_code, $notice_type );

		if ( ! empty( $message ) ) {
			// Make notices dismissible
			$class = 'notice notice-' . $notice_type . ' is-dismissible';
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $message ) );

			// Fire action for custom notice handling
			do_action( "table_{$this->table_id}_admin_notice", $notice_type, $this->table_config );
		}
	}

	/**
	 * Get message for notice code
	 *
	 * @param string $notice_code Notice code (usually action_result)
	 * @param string $notice_type Notice type (success, error, etc)
	 *
	 * @return string Message text
	 */
	private function get_message_for_notice_code( string $notice_code, string $notice_type ): string {
		// Check if we have a specific notice defined in table config
		if ( isset( $this->table_config['notices'][ $notice_code ] ) ) {
			return $this->table_config['notices'][ $notice_code ];
		}

		// Parse notice code to extract action and result
		if ( preg_match( '/^(.+)_(success|error)$/', $notice_code, $matches ) ) {
			$action = $matches[1];
			$result = $matches[2];

			// Check for specific action messages in row_actions config
			if ( isset( $this->table_config['row_actions'][ $action ] ) ) {
				$action_config = $this->table_config['row_actions'][ $action ];

				if ( $result === 'success' && ! empty( $action_config['success_notice'] ) ) {
					return $action_config['success_notice'];
				}

				if ( $result === 'error' && ! empty( $action_config['failure_notice'] ) ) {
					return $action_config['failure_notice'];
				}
			}

			// Generate default messages
			$item_name = $this->table_config['singular'] ?? 'item';

			if ( $result === 'success' ) {
				return sprintf( __( '%s action completed successfully.', 'sugarcart' ), ucfirst( $action ) );
			} else {
				return sprintf( __( 'Failed to perform %s action on %s.', 'sugarcart' ), $action, $item_name );
			}
		}

		// Default fallback message
		return sprintf( __( 'Action completed with %s', 'sugarcart' ), $notice_type );
	}

}