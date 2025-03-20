<?php
/**
 * Admin Tables Registration Manager
 *
 * @package     ArrayPress\WP\Register
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register;

// Exit if accessed directly
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tables
 *
 * Manages WordPress admin table registration and display.
 *
 * @since 1.0.0
 */
class Tables {

	/**
	 * Instance of this class.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

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
	 * Debug mode status
	 *
	 * @var bool
	 */
	private bool $debug = false;

	/**
	 * Get instance of this class.
	 *
	 * @return self Instance of this class.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor - Sets up actions and filters.
	 */
	private function __construct() {
		$this->debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		// Process row actions very early
		add_action( 'admin_init', [ $this, 'process_row_actions' ], 5 );

		// Register admin pages
		add_action( 'init', [ $this, 'register_admin_pages' ] );

		// Screen options and help tabs
		add_action( 'current_screen', [ $this, 'setup_screen' ] );

		// Save screen options
		add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );

		// Register assets
		add_action( 'admin_init', [ AssetsManager::class, 'register' ] );

		//
		add_action( 'wp_ajax_table_column_update', [ $this, 'handle_column_update' ] );

	}

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

		// Auto-generate base_url if not provided
		if ( ! isset( $config['base_url'] ) ) {
			// No need for separate function, handled in get_url
		}

		// Auto-configure add_new_url if not set but base_url is available
		if ( ! isset( $config['add_new_url'] ) ) {
			$config['add_new_url'] = $this->get_url( $id, 'add' );
		}

		// Process columns configuration
		$config = $this->normalize_columns_config( $config );

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

			// Help content
			'help_tabs'        => [],
			'help_sidebar'     => '',

			// System settings
			'nonce_name'       => 'bulk-' . $id,
			'hook_prefix'      => 'table_' . $id . '_',

			// Notifications
			'notices'          => [],
		] );

		return $this;
	}

	/**
	 * Normalize columns configuration to standardized format.
	 *
	 * @param array $config Table configuration
	 *
	 * @return array Normalized configuration
	 */
	private function normalize_columns_config( array $config ): array {
		// Skip if no columns defined
		if ( empty( $config['columns'] ) || ! is_array( $config['columns'] ) ) {
			return $config;
		}

		$new_columns      = [];
		$sortable_columns = $config['sortable_columns'] ?? [];

		foreach ( $config['columns'] as $column_id => $column_config ) {
			// Handle string column definitions
			if ( is_string( $column_config ) ) {
				$new_columns[ $column_id ] = [
					'title' => $column_config
				];
				continue;
			}

			// Handle checkbox column
			if ( $column_id === 'cb' && $column_config === '<input type="checkbox" />' ) {
				$new_columns[ $column_id ] = $column_config;
				continue;
			}

			// Already in array format
			if ( ! is_array( $column_config ) ) {
				$new_columns[ $column_id ] = [ 'title' => ucfirst( $column_id ) ];
				continue;
			}

			// Use the column configuration as-is
			$column_normalized = $column_config;

			// Check sortable columns and integrate if found
			if ( ! empty( $sortable_columns[ $column_id ] ) ) {
				if ( is_array( $sortable_columns[ $column_id ] ) ) {
					$column_normalized['sortable']     = true;
					$column_normalized['sort_by']      = $sortable_columns[ $column_id ][0] ?? $column_id;
					$column_normalized['default_sort'] = ! empty( $sortable_columns[ $column_id ][1] );
				} else {
					$column_normalized['sortable'] = (bool) $sortable_columns[ $column_id ];
				}
			}

			$new_columns[ $column_id ] = $column_normalized;
		}

		$config['columns'] = $new_columns;

		return $config;
	}

	/**
	 * Normalize row actions configuration.
	 *
	 * @param array  $config   Table configuration
	 * @param string $table_id Table ID
	 *
	 * @return array Normalized configuration
	 */
	private function normalize_row_actions_config( array $config, string $table_id ): array {
		if ( empty( $config['row_actions'] ) || ! is_array( $config['row_actions'] ) ) {
			$config['row_actions'] = [];

			return $config;
		}

		$singular = $config['singular'] ?? 'item';

		// Handle standard actions
		if ( isset( $config['row_actions']['edit'] ) && $config['row_actions']['edit'] === true ) {
			$config['row_actions']['edit'] = [
				'title'     => sprintf( __( 'Edit %s', 'arraypress' ), $singular ),
				'action'    => 'edit',
				'link_only' => true
			];
		}

		if ( isset( $config['row_actions']['view'] ) && $config['row_actions']['view'] === true ) {
			$config['row_actions']['view'] = [
				'title'     => sprintf( __( 'View %s', 'arraypress' ), $singular ),
				'action'    => 'view',
				'link_only' => true
			];
		}

		if ( isset( $config['row_actions']['delete'] ) && $config['row_actions']['delete'] === true ) {
			$config['row_actions']['delete'] = [
				'title'   => sprintf( __( 'Delete %s', 'arraypress' ), $singular ),
				'action'  => 'delete',
				'class'   => 'submitdelete',
				'confirm' => sprintf( __( 'Are you sure you want to delete this %s?', 'arraypress' ), $singular ),
				'nonce'   => 'delete-' . $singular . '-{id}'
			];
		}

		// Ensure all row actions have required fields
		foreach ( $config['row_actions'] as $action_id => $action_config ) {
			// Skip already processed standard actions
			if ( in_array( $action_id, [ 'edit', 'view', 'delete' ] ) && is_array( $action_config ) ) {
				continue;
			}

			// Convert string to array format
			if ( is_string( $action_config ) ) {
				$config['row_actions'][ $action_id ] = [
					'title'  => $action_config,
					'action' => $action_id
				];
				continue;
			}

			// Handle non-array values
			if ( ! is_array( $action_config ) ) {
				$config['row_actions'][ $action_id ] = [
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

			$config['row_actions'][ $action_id ] = $action_config;
		}

		return $config;
	}

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

		// Check if the action exists in row_actions config
		if ( empty( $table_config['row_actions'][ $action ] ) ) {
			return;
		}

		$action_config = $table_config['row_actions'][ $action ];

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

		// Process the action using TableNotices
		$notices  = new TableNotices( $table_id, $table_config );
		$callback = null;

		if ( ! empty( $action_config['callback'] ) && is_callable( $action_config['callback'] ) ) {
			$callback = $action_config['callback'];
		}

		$notice = $notices->process_row_action( $action, $id, $callback );

		// Setup URL for redirect
		$redirect_url = $notices->add_notice_to_url(
			$this->get_url( $table_id ),
			$notice
		);

		// Redirect with notice parameters
		wp_safe_redirect( $redirect_url );
		exit;
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

				$hook_suffix = '';

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
	 * Setup screen options and help tabs.
	 *
	 * @param \WP_Screen $screen Current screen
	 */
	public function setup_screen( $screen ) {
		// Check if we're on one of our table pages
		$table_id = $this->get_table_id_for_screen( $screen );
		if ( ! $table_id ) {
			return;
		}

		$table_config = $this->registered_tables[ $table_id ] ?? null;
		if ( ! $table_config ) {
			return;
		}

		// Setup screen options
		if ( ! empty( $table_config['screen_options'] ) && ! empty( $table_config['screen_options']['enabled'] ) ) {
			// Per page option
			$per_page = $table_config['screen_options']['per_page'] ?? [];

			if ( ! empty( $per_page ) ) {
				$label   = $per_page['label'] ?? sprintf( __( '%s per page', 'arraypress' ), $table_config['plural'] );
				$default = $per_page['default'] ?? $table_config['per_page'] ?? 30;
				$option  = $per_page['option'] ?? 'table_' . $table_id . '_per_page';

				add_screen_option( 'per_page', [
					'label'   => $label,
					'default' => $default,
					'option'  => $option
				] );
			}
		}

		// Setup help tabs
		if ( ! empty( $table_config['help_tabs'] ) && is_array( $table_config['help_tabs'] ) ) {
			foreach ( $table_config['help_tabs'] as $tab_id => $tab ) {
				$screen->add_help_tab( [
					'id'       => $tab_id,
					'title'    => $tab['title'] ?? '',
					'content'  => $tab['content'] ?? '',
					'callback' => $tab['callback'] ?? null,
				] );
			}
		}

		// Setup help sidebar
		if ( ! empty( $table_config['help_sidebar'] ) ) {
			// Direct HTML content
			$screen->set_help_sidebar( $table_config['help_sidebar'] );
		} elseif ( ! empty( $table_config['help_sidebar_links'] ) && is_array( $table_config['help_sidebar_links'] ) ) {
			// Build from links array
			$sidebar_content = '<p><strong>' . __( 'For more information:', 'arraypress' ) . '</strong></p><ul>';

			foreach ( $table_config['help_sidebar_links'] as $link ) {
				if ( ! empty( $link['url'] ) && ! empty( $link['text'] ) ) {
					$sidebar_content .= '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['text'] ) . '</a></li>';
				}
			}

			$sidebar_content .= '</ul>';
			$screen->set_help_sidebar( $sidebar_content );
		}

		// Fire action to allow further screen customization
		do_action( "table_{$table_id}_setup_screen", $screen, $table_config );
	}

	/**
	 * Get table ID for current screen.
	 *
	 * @param \WP_Screen $screen Current screen
	 *
	 * @return string|null Table ID or null if not found
	 */
	public function get_table_id_for_screen( $screen ): ?string {
		if ( ! $screen ) {
			return null;
		}

		// Direct match from screen map
		if ( isset( $this->screen_map[ $screen->id ] ) ) {
			return $this->screen_map[ $screen->id ];
		}

		// Try to extract from page parameter
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
	 * Handle saving screen options.
	 *
	 * @param mixed  $status The value to save
	 * @param string $option The option name
	 * @param mixed  $value  The option value
	 *
	 * @return mixed
	 */
	public function set_screen_option( $status, $option, $value ) {
		// Check if this is one of our screen options
		foreach ( $this->registered_tables as $id => $config ) {
			$screen_option = $config['screen_options']['per_page']['option'] ?? 'table_' . $id . '_per_page';

			if ( $option === $screen_option ) {
				return $value;
			}
		}

		return $status;
	}

	/**
	 * Display admin notices for table actions.
	 *
	 * This method is now only called explicitly within our template,
	 * not through the admin_notices hook.
	 */
	public function display_admin_notices() {
		// Get current table ID
		$table_id = $this->get_current_table_id();

		// If we have a table ID and notice parameters, display notice
		if ( $table_id && isset( $this->registered_tables[ $table_id ] ) &&
		     ! empty( $_GET['notice_type'] ) && ! empty( $_GET['notice'] ) ) {

			$table_config = $this->registered_tables[ $table_id ];
			$notices      = new TableNotices( $table_id, $table_config );
			$notices->display_notice_from_url();
		}
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
	 * Render the table page.
	 *
	 * @param string $table_id ID of the table to render
	 */
	public function render_table_page( string $table_id ): void {
		// Check if table config exists
		if ( ! isset( $this->registered_tables[ $table_id ] ) ) {
			echo '<div class="wrap"><p>Table configuration not found.</p></div>';

			return;
		}

		$table_config = $this->registered_tables[ $table_id ];
		$hook_prefix  = $table_config['hook_prefix'] ?? 'table_' . $table_id . '_';

		// Use custom render callback if provided
		if ( ! empty( $table_config['callbacks']['render'] ) && is_callable( $table_config['callbacks']['render'] ) ) {
			call_user_func( $table_config['callbacks']['render'], $table_config );

			return;
		}

		// Permission check based on capability
		$capability = $table_config['capability'] ?? 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			wp_die(
				sprintf( __( 'You do not have permission to manage %s.', 'arraypress' ), $table_config['plural'] ),
				__( 'Error', 'arraypress' ),
				[ 'response' => 403 ]
			);
		}

		// Create table instance
		$table_instance = new ListTable( $table_id, $table_config );
		$table_instance->prepare_items();

		// Action before the table
		do_action( "{$hook_prefix}page_top", $table_id, $table_config );

		// Render modern header outside the wrap div
		$this->render_table_header( $table_id, $table_config, $hook_prefix );

		// Display the table
		?>
        <div class="wrap">
			<?php
			// Display admin notices AFTER the header but INSIDE the wrap
			$this->display_admin_notices();

			// Action before the form
			do_action( "{$hook_prefix}before_form", $table_id, $table_config );
			?>

            <!-- Use method="get" to match WordPress core behavior -->
            <form id="<?php echo esc_attr( $table_id ); ?>-filter" method="get">
				<?php
				// Add necessary hidden fields to maintain the current page
				$this->add_hidden_fields();

				// Action before the table content
				do_action( "{$hook_prefix}before_table", $table_id, $table_config, $table_instance );

				// Search box
				$table_instance->search_box(
					sprintf( __( 'Search %s', 'arraypress' ), $table_config['plural'] ),
					$table_id
				);

				// Display the table views and content
				$table_instance->views();
				$table_instance->display();

				// Action after the table content
				do_action( "{$hook_prefix}after_table", $table_id, $table_config, $table_instance );
				?>
            </form>

			<?php
			// Action after the form
			do_action( "{$hook_prefix}after_form", $table_id, $table_config );
			?>
        </div>
		<?php

		// Action after the table
		do_action( "{$hook_prefix}page_bottom", $table_id, $table_config );
	}

	/**
	 * Render the modern table header with enhanced URL handling
	 *
	 * @param string $table_id     The table ID
	 * @param array  $table_config The table configuration
	 * @param string $hook_prefix  Action hook prefix
	 */
	private function render_table_header( string $table_id, array $table_config, string $hook_prefix ): void {
		?>
        <div class="wp-header-modern">
            <div class="wp-header-wrapper">
				<?php if ( ! empty( $table_config['header_logo'] ) ) : ?>
                    <span class="wp-header-branding">
                    <img class="wp-header-logo" alt="" src="<?php echo esc_url( $table_config['header_logo'] ); ?>">
                </span>
				<?php endif; ?>

                <span class="wp-header-title-wrap">
                <?php if ( ! empty( $table_config['header_logo'] ) ) : ?>
                    <span class="wp-header-separator">/</span>
                <?php endif; ?>
                <h1 class="wp-header-title"><?php echo esc_html( $table_config['title'] ); ?></h1>

                <?php if ( ! empty( $table_config['add_new_url'] ) ) : ?>
                    <a href="<?php echo esc_url( $table_config['add_new_url'] ); ?>" class="page-title-action button">
                        <?php
                        echo esc_html(
	                        sprintf(
		                        $table_config['add_new_text'] ?? __( 'Add New %s', 'arraypress' ),
		                        $table_config['singular']
	                        )
                        );
                        ?>
                    </a>
                <?php endif; ?>
            </span>

				<?php do_action( "{$hook_prefix}header_content", $table_id, $table_config ); ?>
            </div>
        </div>
		<?php
	}

	/**
	 * Add hidden fields to maintain current page parameters.
	 */
	private function add_hidden_fields(): void {
		$parameters = [ 'page', 'post_type', 'status', 'paged', 'orderby', 'order' ];
		foreach ( $parameters as $param ) {
			if ( ! empty( $_REQUEST[ $param ] ) ) {
				echo '<input type="hidden" name="' . esc_attr( $param ) . '" value="' . esc_attr( sanitize_text_field( $_REQUEST[ $param ] ) ) . '" />';
			}
		}
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

	/**
	 * Get a URL for table operations
	 *
	 * @param string       $table_id The table ID
	 * @param string|array $action   The action name or array of query parameters
	 * @param int|string   $item_id  Optional item ID
	 * @param array        $extra    Optional extra query parameters
	 *
	 * @return string      The generated URL
	 */
	public function get_url( string $table_id, $action = '', $item_id = null, array $extra = [] ): string {
		// Get table configuration
		$table_config = $this->registered_tables[ $table_id ] ?? [];
		if ( empty( $table_config ) ) {
			return admin_url( 'admin.php' );
		}

		// Get the base URL
		$base_url = '';

		// 1. Use direct base_url if provided
		if ( isset( $table_config['base_url'] ) ) {
			if ( is_string( $table_config['base_url'] ) ) {
				$base_url = $table_config['base_url'];
			} elseif ( is_callable( $table_config['base_url'] ) ) {
				$base_url = call_user_func( $table_config['base_url'] );
			}
		} // 2. Generate from parent_slug and slug
		else {
			$slug        = $table_config['slug'] ?? $table_id;
			$parent_slug = $table_config['parent_slug'] ?? '';

			if ( in_array( $parent_slug, [ 'options-general.php', 'tools.php', 'upload.php', 'index.php' ] ) ) {
				$base_url = admin_url( $parent_slug . '?page=' . $slug );
			} elseif ( strpos( $parent_slug, 'edit.php' ) === 0 ) {
				$base_url = admin_url( $parent_slug . '&page=' . $slug );
			} else {
				$base_url = admin_url( 'admin.php?page=' . $slug );
			}
		}

		// Build query parameters
		$params = [];

		if ( is_array( $action ) ) {
			$params = $action;
		} elseif ( ! empty( $action ) && is_string( $action ) ) {
			$params['action'] = $action;
		}

		if ( $item_id !== null ) {
			$params['id'] = $item_id;
		}

		if ( ! empty( $extra ) ) {
			$params = array_merge( $params, $extra );
		}

		// Add parameters if we have any
		if ( ! empty( $params ) ) {
			$base_url = add_query_arg( $params, $base_url );
		}

		return $base_url;
	}

}