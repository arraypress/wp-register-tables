<?php
/**
 * Table Registration Trait
 *
 * Provides registration functionality for the Tables class.
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
 * Trait Register
 *
 * Registration functionality for the Tables class
 */
trait Register {

	/**
	 * Collection of registered tables
	 *
	 * @var array
	 */
	private array $registered_tables = [];

	/**
	 * Collection of screen IDs and their associated table IDs
	 *
	 * @var array
	 */
	private array $screen_map = [];

	/**
	 * Enhanced register method with automatic URL configurations
	 *
	 * @param string $id     Unique identifier for the table
	 * @param array  $config Table configuration
	 *
	 * @return self
	 */
	public function register( string $id, array $config ): self {
		// Basic validation
		if ( empty( $id ) ) {
			$this->log( 'Table ID cannot be empty' );

			return $this;
		}

		if ( empty( $config['columns'] ) || ! is_array( $config['columns'] ) ) {
			$this->log( sprintf( 'Table %s missing columns configuration', $id ) );

			return $this;
		}

		// Configure all URLs for this table
		$this->configure_table_urls( $id, $config );

		// Process columns configuration
		$config = $this->normalize_columns_config( $config );

		//
		$config = $this->apply_column_conventions( $config );

		// Process row actions
		$config = $this->normalize_row_actions_config( $config, $id );

		// Set configuration with defaults
		$this->registered_tables[ $id ] = wp_parse_args( $config, [
			// Basic table details
			'title'            => '',                          // Table title
			'singular'         => '',                          // Singular name for items
			'plural'           => '',                          // Plural name for items

			// Menu registration
			'parent_slug'      => 'tools.php',                 // Parent menu slug
			'menu_title'       => '',                          // Menu title (defaults to table title)
			'slug'             => $id,                         // Menu slug
			'position'         => null,                        // Menu position
			'capability'       => 'manage_options',            // Required capability

			// Table structure
			'columns'          => [],                          // Table columns
			'sortable_columns' => [],                          // Sortable columns

			// Filters - Using cleaner structure
			'filters'          => [
				'quick'    => [],                              // Quick filters shown in the top bar
				'advanced' => []                               // Advanced filters in the expandable panel
			],

			// Actions
			'bulk_actions'     => [],                          // Bulk actions
			'row_actions'      => [],                          // Row actions

			// Core callbacks
			'callbacks'        => [
				'get'     => null,                             // Get data items
				'count'   => null,                             // Get counts for views
				'process' => null,                             // Process actions
			],

			// Table behavior
			'per_page'         => 30,                          // Items per page
			'default_sort'     => 'id',                        // Default sorting column
			'default_order'    => 'DESC',                      // Default sort order

			// Field mappings
			'item_id_field'    => 'id',                        // Field to use for item IDs
			'item_name_field'  => 'name',                      // Field to use for item names

			// Customization
			'css'              => [],                          // Custom CSS rules
			'js'               => '',                          // Custom JS code

			// WordPress integration
			'screen_options'   => [
				'enabled'  => true,
				'per_page' => [
					'label'   => '',
					'default' => 30,
					'option'  => ''
				],
			],

			// Help content - unified format
			'help'             => [
				'tabs'    => [],                               // Help tabs configuration
				'sidebar' => '',                               // Direct sidebar HTML content
				'links'   => [],                               // Simplified help links
			],

			// System settings
			'nonce_name'       => 'bulk-' . $id,
			'hook_prefix'      => 'table_' . $id . '_',

			// Notifications
			'notices'          => [],
		] );

		return $this;
	}

	/**
	 * Configure URLs for a table
	 *
	 * @param string $table_id Table ID
	 * @param array  $config   Table configuration (passed by reference)
	 */
	protected function configure_table_urls( string $table_id, array &$config ): void {
		// First, determine the base URL for this table
		$base_url = $this->determine_base_url( $table_id, $config );

		// Store it in the configuration for future use
		$config['_base_url'] = $base_url;

		// Autoconfigure add_new_url if not explicitly set
		if ( ! isset( $config['add_new_url'] ) ) {
			if ( isset( $config['show_add_new'] ) && $config['show_add_new'] === false ) {
				$config['add_new_url'] = '';
			} else {
				$config['add_new_url'] = add_query_arg( 'action', 'add', $base_url );
			}
		}
	}

	/**
	 * Determine the base URL for a table using all available information
	 *
	 * @param string $table_id Table ID
	 * @param array  $config   Table configuration
	 *
	 * @return string              Base URL for the table
	 */
	protected function determine_base_url( string $table_id, array $config ): string {
		// 1. Use direct base_url if provided as a string
		if ( isset( $config['base_url'] ) && is_string( $config['base_url'] ) ) {
			return $config['base_url'];
		}

		// 2. Use callback if provided
		if ( isset( $config['base_url'] ) && is_callable( $config['base_url'] ) ) {
			return call_user_func( $config['base_url'] );
		}

		// 3. Generate based on parent_slug and slug
		$slug        = $config['slug'] ?? $table_id;
		$parent_slug = $config['parent_slug'] ?? '';

		if ( in_array( $parent_slug, [ 'options-general.php', 'tools.php', 'upload.php', 'index.php' ] ) ) {
			return admin_url( $parent_slug . '?page=' . $slug );
		}

		if ( strpos( $parent_slug, 'edit.php' ) === 0 ) {
			return admin_url( $parent_slug . '&page=' . $slug );
		}

		return admin_url( 'admin.php?page=' . $slug );
	}

	/**
	 * Normalize actions configuration.
	 *
	 * @param array  $config   Table configuration
	 * @param string $table_id Table ID
	 *
	 * @return array Normalized configuration
	 */
	private function normalize_row_actions_config( array $config, string $table_id ): array {
		if ( empty( $config['actions'] ) || ! is_array( $config['actions'] ) ) {
			$config['actions'] = [];

			return $config;
		}

		$singular = $config['singular'] ?? 'item';

		// Handle standard actions
		if ( isset( $config['actions']['edit'] ) && $config['actions']['edit'] === true ) {
			$config['actions']['edit'] = [
				'title'     => sprintf( __( 'Edit %s', 'arraypress' ), $singular ),
				'action'    => 'edit',
				'link_only' => true
			];
		}

		if ( isset( $config['actions']['view'] ) && $config['actions']['view'] === true ) {
			$config['actions']['view'] = [
				'title'     => sprintf( __( 'View %s', 'arraypress' ), $singular ),
				'action'    => 'view',
				'link_only' => true
			];
		}

		if ( isset( $config['actions']['delete'] ) && $config['actions']['delete'] === true ) {
			$config['actions']['delete'] = [
				'title'   => sprintf( __( 'Delete %s', 'arraypress' ), $singular ),
				'action'  => 'delete',
				'class'   => 'submitdelete',
				'confirm' => sprintf( __( 'Are you sure you want to delete this %s?', 'arraypress' ), $singular ),
				'nonce'   => 'delete-' . $singular . '-{id}'
			];
		}

		// Ensure all actions have required fields
		foreach ( $config['actions'] as $action_id => $action_config ) {
			// Skip already processed standard actions
			if ( in_array( $action_id, [ 'edit', 'view', 'delete' ] ) && is_array( $action_config ) ) {
				continue;
			}

			// Convert string to array format
			if ( is_string( $action_config ) ) {
				$config['actions'][ $action_id ] = [
					'title'  => $action_config,
					'action' => $action_id
				];
				continue;
			}

			// Handle non-array values
			if ( ! is_array( $action_config ) ) {
				$config['actions'][ $action_id ] = [
					'title'  => ucfirst( str_replace( '_', ' ', $action_id ) ),
					'action' => $action_id
				];
				continue;
			}

			// Ensure action has at least these properties
			if ( ! isset( $action_config['title'] ) ) {
				$action_config['title'] = ucfirst( str_replace( '_', ' ', $action_id ) );
			}

			if ( ! isset( $action_config['action'] ) ) {
				$action_config['action'] = $action_id;
			}

			// Set default nonce if callback exists but no nonce defined
			if ( ! empty( $action_config['callback'] ) && empty( $action_config['nonce'] ) ) {
				$action_config['nonce'] = $table_id . '-' . $action_id . '-{id}';
			}

			$config['actions'][ $action_id ] = $action_config;
		}

		return $config;
	}

	/**
	 * Get the current table ID from request parameters.
	 *
	 * @return string|null Table ID or null if not found
	 */
	private function get_current_table_id(): ?string {
		// Try to get from page parameter
		if ( ! empty( $_GET['page'] ) ) {
			$page = sanitize_key( $_GET['page'] );

			foreach ( $this->registered_tables as $id => $config ) {
				if ( $config['slug'] === $page ) {
					return $id;
				}
			}
		}

		return null;
	}

	/**
	 * Register admin menu pages for registered tables.
	 */
	public function register_admin_pages(): void {
		if ( empty( $this->registered_tables ) ) {
			return;
		}

		// Register on admin_menu hook
		add_action( 'admin_menu', function () {
			foreach ( $this->registered_tables as $id => $table ) {
				$title       = $table['title'] ?? ucwords( str_replace( '_', ' ', $id ) );
				$menu_title  = $table['menu_title'] ?? $title;
				$parent_slug = $table['parent_slug'];
				$capability  = $table['capability'];
				$slug        = $table['slug'];

				// Create a closure to ensure we have the correct table config
				$callback = function () use ( $id ) {
					$this->render_table_page( $id );
				};

				if ( empty( $parent_slug ) ) {
					// Register as top-level menu
					$hook_suffix = add_menu_page(
						$title,
						$menu_title,
						$capability,
						$slug,
						$callback,
						'',
						$table['position']
					);
				} else {
					// Register as submenu
					$hook_suffix = add_submenu_page(
						$parent_slug,
						$title,
						$menu_title,
						$capability,
						$slug,
						$callback
					);
				}

				// Store hook suffix to screen ID mapping for screen options
				if ( $hook_suffix ) {
					$this->screen_map[ $hook_suffix ] = $id;
				}
			}
		} );
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log
	 * @param array  $context Optional context
	 */
	protected function log( string $message, array $context = [] ): void {
		if ( $this->debug ) {
			error_log( sprintf(
				'[ArrayPress Tables] %s %s',
				$message,
				$context ? json_encode( $context ) : ''
			) );
		}
	}

}