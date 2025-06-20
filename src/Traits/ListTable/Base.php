<?php
/**
 * Core Table Instance Trait
 *
 * Provides the core functionality for the ListTable class.
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\ListTable;

// Exit if accessed directly
use ArrayPress\CustomTables\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Core
 *
 * Core functionality for the ListTable class
 */
trait Core {

	/**
	 * Common query arguments to remove
	 *
	 * @var array
	 */
	protected static array $common_query_args = [
		'status',
		'paged',
		'_wpnonce',
		'_wp_http_referer',
		'action',
		'action2',
		's',
		'notice_type',
		'notice'
	];

	/**
	 * Essential query parameters to preserve
	 *
	 * @var array
	 */
	protected static array $essential_query_args = [
		'page',
		'post_type',
	];

	/**
	 * Table ID
	 *
	 * @var string
	 */
	protected string $table_id;

	/**
	 * Table configuration
	 *
	 * @var array
	 */
	protected array $table_config;

	/**
	 * Arguments for the data set.
	 *
	 * @var array
	 */
	public $args = array();

	/**
	 * Number of results to show per page.
	 *
	 * @var int
	 */
	public $per_page = 30;

	/**
	 * Counts for different views/statuses.
	 *
	 * @var array
	 */
	public $counts = array(
		'total' => 0
	);

	/**
	 * Current search query
	 *
	 * @var string
	 */
	protected $search = '';

	/**
	 * Action prefix for hooks
	 *
	 * @var string
	 */
	protected string $hook_prefix = '';

	/**
	 * Initialize the core trait
	 *
	 * @param string $table_id     Table ID
	 * @param array  $table_config Table configuration
	 */
	protected function init( string $table_id, array $table_config ) {
		$this->table_id     = $table_id;
		$this->table_config = $table_config;
		$this->per_page     = $table_config['per_page'] ?? 30;
		$this->hook_prefix  = $table_config['hook_prefix'] ?? 'table_' . $table_id . '_';

		// Fire hook before setup
		do_action( "{$this->hook_prefix}before_setup", $this );

		// Setup core components
		$this->set_per_page();
		$this->setup_filters();
		$this->process_active_filters();
		$this->process_bulk_action();
		$this->get_counts();

		// Fire hook after setup
		do_action( "{$this->hook_prefix}after_setup", $this );

		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer_scripts' ) );
	}

	/**
	 * Add query args that should be removable from the URL
	 *
	 * @param array $query_args Existing removable query args
	 *
	 * @return array Updated removable query args
	 */
	public function removable_query_args( array $query_args ): array {
		$query_args[] = 'action';
		$query_args[] = '_wp_http_referer';
		$query_args[] = 'notice';
		$query_args[] = 'notice_type';

		return $query_args;
	}

	/**
	 * Set number of results per page
	 */
	protected function set_per_page() {
		$screen_option = $this->table_config['screen_options']['per_page']['option'] ?? 'table_' . $this->table_id . '_per_page';
		$default       = $this->table_config['screen_options']['per_page']['default'] ?? $this->per_page;

		$this->per_page = $this->get_items_per_page( $screen_option, $default );
	}

	/**
	 * Get the number of items to display per page
	 *
	 * @param string $option  Screen option name
	 * @param int    $default Default value
	 *
	 * @return int Number of items per page
	 */
	protected function get_items_per_page( $option, $default = 30 ): int {
		$user   = get_current_user_id();
		$screen = get_current_screen();

		if ( empty( $user ) || empty( $screen ) ) {
			return $default;
		}

		$per_page = get_user_meta( $user, $option, true );

		if ( empty( $per_page ) || $per_page < 1 ) {
			return $default;
		}

		return (int) $per_page;
	}

	/**
	 * Get the base URL for the table
	 *
	 * @return string Base URL
	 */
	protected function get_base_url(): string {
		return Tables::instance()->get_url($this->table_id);
	}

	/**
	 * Get a request var, or return the default if not set.
	 *
	 * @param string $var     Request variable name
	 * @param mixed  $default Default value if not set
	 *
	 * @return mixed Sanitized request var
	 */
	public function get_request_var( $var = '', $default = false ) {
		$value = $_REQUEST[ $var ] ?? $default;

		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		return is_string( $value ) ? sanitize_text_field( $value ) : $value;
	}

	/**
	 * Get a request parameter from GET or POST
	 *
	 * @param string $param   Parameter name
	 * @param mixed  $default Default value if not set
	 * @param string $method  Specific method to check ('get', 'post', or 'any')
	 *
	 * @return mixed Sanitized parameter value
	 */
	public function get_request_parameter( string $param, $default = '', string $method = 'any' ) {
		$value = null;

		// Check GET if requested
		if ( $method === 'get' || $method === 'any' ) {
			if ( isset( $_GET[ $param ] ) ) {
				$value = $_GET[ $param ];
			}
		}

		// Check POST if requested and value not found yet
		if ( ( $method === 'post' || ( $method === 'any' && $value === null ) ) && isset( $_POST[ $param ] ) ) {
			$value = $_POST[ $param ];
		}

		// Return default if nothing found
		if ( $value === null ) {
			return $default;
		}

		// Sanitize value based on type
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		return is_string( $value ) ? sanitize_text_field( $value ) : $value;
	}

	/**
	 * Get a status request var, if set.
	 *
	 * @param string $default Default status
	 *
	 * @return string Status value
	 */
	protected function get_status( string $default = '' ): string {
		return sanitize_key( $this->get_request_var( 'status', $default ) );
	}

	/**
	 * Get the current page number.
	 *
	 * @return int Current page number
	 */
	protected function get_paged(): int {
		return absint( $this->get_request_var( 'paged', 1 ) );
	}

	/**
	 * Get the current orderby value.
	 *
	 * @return string Current orderby value
	 */
	protected function get_orderby(): string {
		return sanitize_key( $this->get_request_var( 'orderby', $this->table_config['default_sort'] ?? 'id' ) );
	}

	/**
	 * Get the current order value.
	 *
	 * @return string Current order value (ASC or DESC)
	 */
	protected function get_order(): string {
		$order = strtoupper( $this->get_request_var( 'order', $this->table_config['default_order'] ?? 'DESC' ) );

		return in_array( $order, [ 'ASC', 'DESC' ] ) ? $order : 'DESC';
	}

	/**
	 * Get the current search term.
	 *
	 * @return string Sanitized search term
	 */
	protected function get_search(): string {
		if ( empty( $this->search ) ) {
			$raw_search   = urldecode( trim( $this->get_request_var( 's', '' ) ) );
			$this->search = sanitize_text_field( $raw_search );
		}

		return $this->search;
	}

	/**
	 * Create a clean URL by removing all filter parameters
	 *
	 * @param array $additional_args    Additional query args to remove
	 * @param bool  $preserve_essential Whether to preserve essential parameters
	 *
	 * @return string Cleaned URL
	 */
	protected function get_clean_url( array $additional_args = [], bool $preserve_essential = true ): string {
		// Start with common query args to remove
		$args_to_remove = self::$common_query_args;

		// Add any additional arguments
		if ( ! empty( $additional_args ) ) {
			$args_to_remove = array_merge( $args_to_remove, $additional_args );
		}

		// Add advanced filter keys
		if ( ! empty( $this->filters ) ) {
			$args_to_remove = array_merge( $args_to_remove, array_keys( $this->filters ) );
		}

		// Add quick filter keys
		if ( isset( $this->table_config['filters']['quick'] ) && is_array( $this->table_config['filters']['quick'] ) ) {
			foreach ( $this->table_config['filters']['quick'] as $filter_id => $filter ) {
				// Regular quick filter
				$args_to_remove[] = $filter_id;

				// Add date range parameters if applicable
				if ( isset( $filter['type'] ) && $filter['type'] === 'date_range' ) {
					$from_param       = $filter['from_param'] ?? $filter_id . '_from';
					$to_param         = $filter['to_param'] ?? $filter_id . '_to';
					$args_to_remove[] = $from_param;
					$args_to_remove[] = $to_param;
				}
			}
		}

		// Remove all identified parameters
		$clean_url = remove_query_arg( $args_to_remove );

		// Preserve essential parameters if requested
		if ( $preserve_essential ) {
			foreach ( self::$essential_query_args as $param ) {
				$value = $this->get_request_var( $param, null );
				if ( $value !== null && $value !== '' ) {
					$clean_url = add_query_arg( $param, $value, $clean_url );
				}
			}
		}

		return $clean_url;
	}

	/**
	 * Get the current screen object
	 *
	 * @return \WP_Screen|null Screen object or null
	 */
	public function get_screen() {
		if (function_exists('get_current_screen')) {
			return get_current_screen();
		}
		return null;
	}

}