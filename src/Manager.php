<?php
/**
 * Admin Tables Registration Manager
 *
 * Central manager class for registering and rendering WordPress admin list tables.
 * Provides a configuration-driven approach to creating admin tables with support for:
 * - Column definitions with automatic formatting
 * - Row actions (edit, delete, custom)
 * - Bulk actions with callbacks
 * - Status views (tabs)
 * - Dropdown filters
 * - Search functionality
 * - Screen options (items per page)
 * - Help tabs
 * - Modern EDD-style headers
 *
 * @package     ArrayPress\WP\RegisterTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Manager
 *
 * Static manager class for admin table registration and rendering.
 *
 * ## Basic Usage
 *
 * ```php
 * // Register a table
 * Manager::register( 'my_customers', [
 *     'page'     => 'my-plugin-customers',
 *     'labels'   => [
 *         'singular' => 'customer',
 *         'plural'   => 'customers',
 *     ],
 *     'columns'  => [
 *         'name'   => 'Name',
 *         'email'  => 'Email',
 *         'status' => 'Status',
 *     ],
 *     'callbacks' => [
 *         'get_items'  => [ Customers::class, 'query' ],
 *         'get_counts' => [ Customers::class, 'get_counts' ],
 *     ],
 * ] );
 *
 * // Initialize (call once, typically in plugin bootstrap)
 * Manager::init();
 *
 * // Render in your admin page callback
 * Manager::render_table( 'my_customers' );
 * ```
 *
 * ## Configuration Options
 *
 * | Option              | Type           | Description                                      |
 * |---------------------|----------------|--------------------------------------------------|
 * | page                | string         | Admin page slug (required)                       |
 * | labels              | array          | UI labels (singular, plural, title, etc.)        |
 * | columns             | array          | Column definitions                               |
 * | sortable            | array          | Sortable column keys                             |
 * | primary_column      | string         | Column for row actions                           |
 * | hidden_columns      | array          | Columns hidden by default                        |
 * | column_widths       | array          | Custom column widths                             |
 * | row_actions         | array|callable | Row action definitions                           |
 * | bulk_actions        | array          | Bulk action definitions                          |
 * | views               | array          | Status view definitions                          |
 * | filters             | array          | Dropdown filter definitions                      |
 * | callbacks           | array          | Data callbacks (get_items, get_counts, delete)   |
 * | status_styles       | array          | Custom status => CSS class mappings              |
 * | capabilities        | array          | Required capabilities                            |
 * | per_page            | int            | Items per page default (30)                      |
 * | searchable          | bool           | Enable search box (true)                         |
 * | show_count          | bool           | Show total count in header (false)               |
 * | auto_delete_action  | bool           | Auto-add delete row action (true)                |
 * | logo                | string         | Header logo URL                                  |
 * | header_title        | string         | Custom header title                              |
 * | flyout              | string         | Edit flyout ID                                   |
 * | add_flyout          | string         | Add new flyout ID                                |
 * | add_url             | string|callable| Add new URL                                      |
 * | help                | array          | Help tab definitions                             |
 *
 * ## Action Hooks
 *
 * - `arraypress_before_render_table`          - Before table renders
 * - `arraypress_before_render_table_{$id}`    - Before specific table renders
 * - `arraypress_after_render_table`           - After table renders
 * - `arraypress_after_render_table_{$id}`     - After specific table renders
 * - `arraypress_table_item_deleted`           - After item deleted
 * - `arraypress_table_item_deleted_{$id}`     - After item deleted from specific table
 * - `arraypress_table_bulk_action`            - When bulk action processed
 * - `arraypress_table_bulk_action_{$id}`      - When bulk action processed on specific table
 * - `arraypress_table_bulk_action_{$id}_{$action}` - Specific bulk action on specific table
 * - `arraypress_table_single_action_{$id}`    - Custom single action (when no handler defined)
 *
 * ## Filter Hooks
 *
 * - `arraypress_table_admin_notices`          - Custom admin notices
 * - `arraypress_table_admin_notices_{$id}`    - Custom notices for specific table
 *
 * @since 1.0.0
 */
class Manager {

    /* =========================================================================
     * PROPERTIES
     * ========================================================================= */

    /**
     * Registered tables storage
     *
     * Associative array of table ID => configuration pairs.
     * Populated by register() calls.
     *
     * @since 1.0.0
     * @var array<string, array>
     */
    private static array $tables = [];

    /**
     * Asset enqueue flag
     *
     * Prevents duplicate asset enqueuing when multiple tables
     * are registered on the same page.
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $assets_enqueued = false;

    /* =========================================================================
     * REGISTRATION
     * ========================================================================= */

    /**
     * Register an admin table
     *
     * Registers a new admin table with the given configuration. The table
     * will be available for rendering via render_table() after init() is called.
     *
     * Configuration is merged with sensible defaults. Labels are auto-generated
     * from singular/plural if not provided. Primary column is auto-detected.
     *
     * @since 1.0.0
     *
     * @param string $id     Unique table identifier. Used in hooks and internally.
     * @param array  $config Table configuration array. See class docblock for options.
     *
     * @return void
     */
    public static function register( string $id, array $config ): void {
        // Default configuration values
        $defaults = [
            // Core settings
                'labels'              => [],
                'callbacks'           => [],
                'page'                => '',

            // Flyout integration
                'flyout'              => '',
                'view_flyout'         => '',
                'add_flyout'          => '',
                'add_url'             => '',
                'add_button_callback' => null,

            // Column configuration
                'columns'             => [],
                'sortable'            => [],
                'primary_column'      => '',
                'hidden_columns'      => [],
                'column_widths'       => [],

            // Actions
                'row_actions'         => [],
                'bulk_actions'        => [],

            // Filtering & views
                'views'               => [],
                'filters'             => [],
                'status_styles'       => [],
                'base_query_args'     => [],

            // Display options
                'per_page'            => 30,
                'searchable'          => true,
                'show_count'          => false,
                'auto_delete_action'  => true,

            // Security
                'capabilities'        => [],

            // Help
                'help'                => [],

            // Header options
                'logo'                => '',
                'header_title'        => '',
                'show_title'          => true,

            // Body class
                'body_class'          => '',
        ];

        $config = wp_parse_args( $config, $defaults );

        // Parse nested arrays with defaults
        $config['labels']       = self::parse_labels( $config['labels'] );
        $config['callbacks']    = self::parse_callbacks( $config['callbacks'] );
        $config['capabilities'] = self::parse_capabilities( $config['capabilities'] );

        // Auto-generate missing labels
        $config['labels'] = self::auto_generate_labels( $config['labels'] );

        // Auto-detect primary column
        $config['primary_column'] = self::detect_primary_column(
                $config['primary_column'],
                $config['columns']
        );

        self::init();

        // Store configuration
        self::$tables[ $id ] = $config;
    }

    /**
     * Parse labels configuration with defaults
     *
     * @since 1.0.0
     *
     * @param array $labels User-provided labels.
     *
     * @return array Merged labels with defaults.
     */
    private static function parse_labels( array $labels ): array {
        return wp_parse_args( $labels, [
                'singular'         => '',
                'plural'           => '',
                'title'            => '',
                'add_new'          => '',
                'search'           => '',
                'not_found'        => '',
                'not_found_search' => '',
        ] );
    }

    /**
     * Parse callbacks configuration with defaults
     *
     * @since 1.0.0
     *
     * @param array $callbacks User-provided callbacks.
     *
     * @return array Merged callbacks with defaults.
     */
    private static function parse_callbacks( array $callbacks ): array {
        return wp_parse_args( $callbacks, [
                'get_items'  => null,
                'get_counts' => null,
                'delete'     => null,
                'update'     => null,
        ] );
    }

    /**
     * Parse capabilities configuration with defaults
     *
     * @since 1.0.0
     *
     * @param array $capabilities User-provided capabilities.
     *
     * @return array Merged capabilities with defaults.
     */
    private static function parse_capabilities( array $capabilities ): array {
        return wp_parse_args( $capabilities, [
                'view'   => '',
                'edit'   => '',
                'delete' => '',
                'bulk'   => '',
        ] );
    }

    /**
     * Auto-generate missing labels from singular/plural
     *
     * @since 1.0.0
     *
     * @param array $labels Parsed labels array.
     *
     * @return array Labels with auto-generated values filled in.
     */
    private static function auto_generate_labels( array $labels ): array {
        // Title from plural
        if ( empty( $labels['title'] ) && ! empty( $labels['plural'] ) ) {
            $labels['title'] = ucfirst( $labels['plural'] );
        }

        // Add New from singular
        if ( empty( $labels['add_new'] ) && ! empty( $labels['singular'] ) ) {
            $labels['add_new'] = sprintf(
                    __( 'Add New %s', 'arraypress' ),
                    ucfirst( $labels['singular'] )
            );
        }

        // Search from plural
        if ( empty( $labels['search'] ) && ! empty( $labels['plural'] ) ) {
            $labels['search'] = sprintf(
                    __( 'Search %s', 'arraypress' ),
                    $labels['plural']
            );
        }

        return $labels;
    }

    /**
     * Detect primary column from configuration
     *
     * Checks for explicit 'primary' flag in column config, otherwise
     * uses the first non-checkbox column.
     *
     * @since 1.0.0
     *
     * @param string $primary_column Configured primary column (may be empty).
     * @param array  $columns        Column definitions.
     *
     * @return string Primary column key.
     */
    private static function detect_primary_column( string $primary_column, array $columns ): string {
        if ( ! empty( $primary_column ) || empty( $columns ) ) {
            return $primary_column;
        }

        // Look for explicit primary flag
        foreach ( $columns as $key => $column ) {
            if ( is_array( $column ) && ! empty( $column['primary'] ) ) {
                return $key;
            }
        }

        // Fall back to first non-cb column
        foreach ( $columns as $key => $column ) {
            if ( $key !== 'cb' ) {
                return $key;
            }
        }

        return $primary_column;
    }

    /* =========================================================================
     * INITIALIZATION
     * ========================================================================= */

    /**
     * Initialize the manager
     *
     * Hooks into WordPress admin to enable action processing, screen options,
     * and asset enqueuing. Call this once after registering all tables,
     * typically in your plugin's main file or bootstrap.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function init(): void {
        // Process actions early (before output) to enable redirects
        add_action( 'admin_init', [ __CLASS__, 'process_early_actions' ], 20 );

        // Setup screen options after page loads
        add_action( 'admin_init', [ __CLASS__, 'setup_load_hooks' ], 999 );

        // Enqueue CSS/JS assets
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Add body classes for table pages
        add_filter( 'admin_body_class', [ __CLASS__, 'add_body_class' ] );
    }

    /* =========================================================================
     * ASSETS
     * ========================================================================= */

    /**
     * Enqueue assets callback
     *
     * Hooked to admin_enqueue_scripts. Checks if current page matches
     * a registered table and enqueues styles if so.
     *
     * @since 1.0.0
     *
     * @param string $hook Current admin page hook suffix.
     *
     * @return void
     */
    public static function enqueue_assets( string $hook ): void {
        $page = $_GET['page'] ?? '';

        if ( empty( $page ) ) {
            return;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( ( $config['page'] ?? '' ) === $page ) {
                self::do_enqueue_assets( $config );
                break;
            }
        }
    }

    /**
     * Actually enqueue the assets
     *
     * Enqueues the main stylesheet and outputs dynamic inline styles
     * for column widths and alignments.
     *
     * @since 1.0.0
     *
     * @param array $config Table configuration.
     *
     * @return void
     */
    private static function do_enqueue_assets( array $config ): void {
        if ( self::$assets_enqueued ) {
            return;
        }

        self::$assets_enqueued = true;

        // Enqueue CSS from composer assets package
        if ( function_exists( 'wp_enqueue_composer_style' ) ) {
            wp_enqueue_composer_style(
                    'list-table-styles',
                    __FILE__,
                    'css/admin-tables.css'
            );
        }

        // Output dynamic styles for this table's configuration
        self::output_dynamic_styles( $config );
    }

    /**
     * Output dynamic inline styles
     *
     * Generates CSS for custom column widths and text alignments
     * based on the table configuration.
     *
     * @since 1.0.0
     *
     * @param array $config Table configuration.
     *
     * @return void
     */
    private static function output_dynamic_styles( array $config ): void {
        $styles = '';

        // Custom column widths
        if ( ! empty( $config['column_widths'] ) ) {
            foreach ( $config['column_widths'] as $column => $width ) {
                $styles .= sprintf(
                        ".wp-list-table .column-%s { width: %s; }\n",
                        esc_attr( $column ),
                        esc_attr( $width )
                );
            }
        }

        // Column alignments from column config
        if ( ! empty( $config['columns'] ) ) {
            foreach ( $config['columns'] as $column => $col_config ) {
                if ( is_array( $col_config ) && ! empty( $col_config['align'] ) ) {
                    $align  = in_array( $col_config['align'], [ 'left', 'center', 'right' ], true )
                            ? $col_config['align']
                            : 'left';
                    $styles .= sprintf(
                            ".wp-list-table .column-%s { text-align: %s; }\n",
                            esc_attr( $column ),
                            esc_attr( $align )
                    );
                }
            }
        }

        if ( ! empty( $styles ) ) {
            wp_add_inline_style( 'list-table-styles', $styles );
        }
    }

    /* =========================================================================
     * SCREEN OPTIONS
     * ========================================================================= */

    /**
     * Setup load hooks for screen options
     *
     * Detects when we're on one of our admin pages and sets up screen
     * options (items per page) and help tabs on the current_screen hook.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function setup_load_hooks(): void {
        global $pagenow;

        // Only process on admin.php pages
        if ( $pagenow !== 'admin.php' ) {
            return;
        }

        $page = $_GET['page'] ?? '';

        if ( empty( $page ) ) {
            return;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( ( $config['page'] ?? '' ) === $page ) {
                // Setup screen options on current_screen hook
                add_action( 'current_screen', function () use ( $id, $config ) {
                    self::setup_screen( $id, $config );
                    self::handle_screen_options();
                } );
                break;
            }
        }
    }

    /**
     * Setup screen options and help tabs
     *
     * Adds the "items per page" screen option and any configured help tabs.
     *
     * @since 1.0.0
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     */
    private static function setup_screen( string $id, array $config ): void {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return;
        }

        // Build unique option name for this table
        $option_name = $id . '_per_page';

        // Add per page screen option
        $per_page_label = ! empty( $config['labels']['plural'] )
                ? sprintf( __( '%s per page', 'arraypress' ), $config['labels']['plural'] )
                : __( 'Items per page', 'arraypress' );

        $screen->add_option( 'per_page', [
                'label'   => $per_page_label,
                'default' => $config['per_page'],
                'option'  => $option_name,
        ] );

        // Add help tabs if configured
        if ( ! empty( $config['help'] ) ) {
            self::setup_help_tabs( $screen, $config['help'] );
        }
    }

    /**
     * Setup help tabs on the screen
     *
     * @since 1.0.0
     *
     * @param \WP_Screen $screen Current screen object.
     * @param array      $help   Help tab configuration.
     *
     * @return void
     */
    private static function setup_help_tabs( $screen, array $help ): void {
        foreach ( $help as $key => $tab ) {
            // Sidebar is special
            if ( $key === 'sidebar' ) {
                $screen->set_help_sidebar( $tab );
                continue;
            }

            if ( ! is_array( $tab ) || ! isset( $tab['title'] ) ) {
                continue;
            }

            // Get content from callback or direct content
            $content = '';
            if ( isset( $tab['callback'] ) && is_callable( $tab['callback'] ) ) {
                $content = call_user_func( $tab['callback'] );
            } elseif ( isset( $tab['content'] ) ) {
                $content = $tab['content'];
            }

            $screen->add_help_tab( [
                    'id'      => sanitize_key( $key ),
                    'title'   => $tab['title'],
                    'content' => $content,
            ] );
        }
    }

    /**
     * Handle screen option saving
     *
     * Adds filter to allow the per_page option to be saved.
     * Matches any option ending in '_per_page' for our tables.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function handle_screen_options(): void {
        add_filter( 'set-screen-option', function ( $status, $option, $value ) {
            // Match our table per_page options (e.g., 'ate_customers_per_page')
            if ( str_ends_with( $option, '_per_page' ) ) {
                return absint( $value );
            }

            return $status;
        }, 10, 3 );
    }

    /* =========================================================================
     * ACTION PROCESSING
     * ========================================================================= */

    /**
     * Process early actions
     *
     * Handles actions that require redirects before any output is sent.
     * This includes filter form submissions, single item actions (delete),
     * and bulk actions.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function process_early_actions(): void {
        $page = $_GET['page'] ?? '';

        if ( empty( $page ) ) {
            return;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( $config['page'] === $page ) {
                // Process in order of priority
                self::process_filter_redirect( $id, $config );
                self::process_single_actions( $id, $config );
                self::process_bulk_actions( $id, $config );
                break;
            }
        }
    }

    /**
     * Process filter form submission
     *
     * When filters are submitted, redirects to a clean URL with only
     * the necessary parameters (removes _wpnonce, filter_action, etc.).
     *
     * @since 1.0.0
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     */
    private static function process_filter_redirect( string $id, array $config ): void {
        if ( ! isset( $_GET['filter_action'] ) ) {
            return;
        }

        $clean_args = [
                'page' => $config['page'],
        ];

        // Preserve search
        if ( ! empty( $_GET['s'] ) ) {
            $clean_args['s'] = sanitize_text_field( $_GET['s'] );
        }

        // Preserve status
        if ( ! empty( $_GET['status'] ) ) {
            $clean_args['status'] = sanitize_key( $_GET['status'] );
        }

        // Preserve custom filters
        foreach ( $config['filters'] as $filter_key => $filter ) {
            if ( ! empty( $_GET[ $filter_key ] ) ) {
                $clean_args[ $filter_key ] = sanitize_text_field( $_GET[ $filter_key ] );
            }
        }

        wp_safe_redirect( add_query_arg( $clean_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Process single item actions
     *
     * Handles row actions like delete. Checks for handler-based actions
     * in the row_actions config, or falls back to the built-in delete action.
     *
     * @since 1.0.0
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     */
    private static function process_single_actions( string $id, array $config ): void {
        $action  = sanitize_key( $_GET['action'] ?? '' );
        $item_id = absint( $_GET['item'] ?? 0 );

        if ( empty( $action ) || empty( $item_id ) ) {
            return;
        }

        // Skip bulk action placeholder values
        if ( $action === '-1' ) {
            return;
        }

        // Handle built-in delete action
        if ( $action === 'delete' ) {
            self::handle_delete_action( $id, $config, $item_id );

            return;
        }

        // Check for custom handler in row_actions config
        if ( ! is_callable( $config['row_actions'] ) && isset( $config['row_actions'][ $action ] ) ) {
            $action_config = $config['row_actions'][ $action ];

            if ( is_array( $action_config ) && isset( $action_config['handler'] ) && is_callable( $action_config['handler'] ) ) {
                self::handle_custom_action( $id, $config, $action, $action_config, $item_id );

                return;
            }
        }

        /**
         * Fires when a custom single action is triggered without a handler
         *
         * Use this hook to handle custom row actions that don't have
         * a handler defined in the row_actions config.
         *
         * @since 1.0.0
         *
         * @param string $action  Action key being performed.
         * @param int    $item_id Item ID the action is performed on.
         * @param array  $config  Table configuration.
         */
        do_action( "arraypress_table_single_action_{$id}", $action, $item_id, $config );
    }

    /**
     * Handle custom row action with handler callback
     *
     * Processes a row action that has a 'handler' callback defined.
     * Verifies nonce, checks capability, calls handler, and redirects.
     *
     * @since 1.0.0
     *
     * @param string $id            Table identifier.
     * @param array  $config        Table configuration.
     * @param string $action        Action key.
     * @param array  $action_config Action configuration from row_actions.
     * @param int    $item_id       Item ID.
     *
     * @return void
     */
    private static function handle_custom_action(
            string $id,
            array $config,
            string $action,
            array $action_config,
            int $item_id
    ): void {
        $singular = $config['labels']['singular'] ?? 'item';

        // Determine nonce action string
        $nonce_action = $action_config['nonce_action'] ?? "{$action}_{$singular}_{$item_id}";
        $nonce_action = str_replace( '{id}', (string) $item_id, $nonce_action );

        // Verify nonce
        $nonce = $_GET['_wpnonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            wp_die( __( 'Security check failed.', 'arraypress' ) );
        }

        // Check capability
        if ( ! empty( $action_config['capability'] ) ) {
            if ( ! current_user_can( $action_config['capability'] ) ) {
                wp_die( __( 'You do not have permission to perform this action.', 'arraypress' ) );
            }
        }

        // Call the handler
        $result = call_user_func( $action_config['handler'], $item_id, $config );

        // Build redirect URL with result
        $redirect_url = self::get_clean_base_url( $config );

        if ( is_array( $result ) ) {
            $redirect_url = add_query_arg( $result, $redirect_url );
        } elseif ( $result === true ) {
            $redirect_url = add_query_arg( 'updated', 1, $redirect_url );
        } elseif ( $result === false ) {
            $redirect_url = add_query_arg( 'error', 'action_failed', $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle built-in delete action
     *
     * Processes delete requests using the configured delete callback.
     * Verifies nonce, checks capability, calls callback, and redirects.
     *
     * @since 1.0.0
     *
     * @param string $id      Table identifier.
     * @param array  $config  Table configuration.
     * @param int    $item_id Item ID to delete.
     *
     * @return void
     */
    private static function handle_delete_action( string $id, array $config, int $item_id ): void {
        // Ensure delete callback exists
        if ( ! isset( $config['callbacks']['delete'] ) || ! is_callable( $config['callbacks']['delete'] ) ) {
            return;
        }

        $singular = $config['labels']['singular'] ?? 'item';

        // Verify nonce
        $nonce = $_GET['_wpnonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, "delete_{$singular}_{$item_id}" ) ) {
            wp_die( __( 'Security check failed.', 'arraypress' ) );
        }

        // Check capability
        if ( ! empty( $config['capabilities']['delete'] ) ) {
            if ( ! current_user_can( $config['capabilities']['delete'] ) ) {
                wp_die( __( 'You do not have permission to delete this item.', 'arraypress' ) );
            }
        }

        // Perform deletion
        $result = call_user_func( $config['callbacks']['delete'], $item_id );

        /**
         * Fires after a single item is deleted
         *
         * @since 1.0.0
         *
         * @param int    $item_id Item ID that was deleted.
         * @param mixed  $result  Result from delete callback.
         * @param string $id      Table identifier.
         * @param array  $config  Table configuration.
         */
        do_action( 'arraypress_table_item_deleted', $item_id, $result, $id, $config );

        /**
         * Fires after a single item is deleted from a specific table
         *
         * @since 1.0.0
         *
         * @param int   $item_id Item ID that was deleted.
         * @param mixed $result  Result from delete callback.
         * @param array $config  Table configuration.
         */
        do_action( "arraypress_table_item_deleted_{$id}", $item_id, $result, $config );

        // Redirect with result
        $redirect_url = self::get_clean_base_url( $config );
        $redirect_url = add_query_arg( 'deleted', $result ? 1 : 0, $redirect_url );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Process bulk actions
     *
     * Handles bulk action form submissions. Verifies nonce, checks capability,
     * executes callback (if defined), fires hooks, and redirects.
     *
     * @since 1.0.0
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     */
    private static function process_bulk_actions( string $id, array $config ): void {
        // Determine which bulk action was selected
        $action = '';
        if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] !== '-1' ) {
            $action = sanitize_key( $_REQUEST['action'] );
        } elseif ( isset( $_REQUEST['action2'] ) && $_REQUEST['action2'] !== '-1' ) {
            $action = sanitize_key( $_REQUEST['action2'] );
        }

        if ( empty( $action ) ) {
            return;
        }

        $plural = $config['labels']['plural'] ?? 'items';

        // Verify nonce
        if ( ! isset( $_REQUEST['_wpnonce'] ) ||
             ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-' . $plural ) ) {
            return;
        }

        // Get selected items
        $items = $_REQUEST[ $plural ] ?? [];

        if ( empty( $items ) ) {
            return;
        }

        $items = array_map( 'absint', $items );

        // Get action configuration
        $action_config = $config['bulk_actions'][ $action ] ?? null;

        if ( ! $action_config ) {
            return;
        }

        // Normalize string config to array
        if ( is_string( $action_config ) ) {
            $action_config = [ 'label' => $action_config ];
        }

        // Check capability
        if ( isset( $action_config['capability'] ) ) {
            if ( ! current_user_can( $action_config['capability'] ) ) {
                wp_die( __( 'Sorry, you are not allowed to perform this action.', 'arraypress' ) );
            }
        }

        /**
         * Fires when a bulk action is processed
         *
         * @since 1.0.0
         *
         * @param array  $items  Selected item IDs.
         * @param string $action Bulk action key.
         * @param string $id     Table identifier.
         */
        do_action( 'arraypress_table_bulk_action', $items, $action, $id );

        /**
         * Fires when a bulk action is processed on a specific table
         *
         * @since 1.0.0
         *
         * @param array  $items  Selected item IDs.
         * @param string $action Bulk action key.
         */
        do_action( "arraypress_table_bulk_action_{$id}", $items, $action );

        /**
         * Fires for a specific bulk action on a specific table
         *
         * @since 1.0.0
         *
         * @param array $items Selected item IDs.
         */
        do_action( "arraypress_table_bulk_action_{$id}_{$action}", $items );

        // Execute callback if defined
        $redirect_args = [];

        if ( isset( $action_config['callback'] ) && is_callable( $action_config['callback'] ) ) {
            $result = call_user_func( $action_config['callback'], $items );

            // Handle different return types
            if ( is_array( $result ) ) {
                $redirect_args = $result;
            } elseif ( is_int( $result ) ) {
                $redirect_args = [ 'updated' => $result ];
            } elseif ( is_bool( $result ) ) {
                $redirect_args = [ 'updated' => $result ? count( $items ) : 0 ];
            }
        } else {
            // No callback - assume success
            $redirect_args = [ 'updated' => count( $items ) ];
        }

        // Redirect with results
        $redirect_url = self::get_clean_base_url( $config );

        if ( ! empty( $redirect_args ) ) {
            $redirect_url = add_query_arg( $redirect_args, $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /* =========================================================================
     * RENDERING
     * ========================================================================= */

    /**
     * Render a registered table
     *
     * Outputs the complete admin page including header, notices, search banner,
     * views, search box, and the table itself. Call this in your admin page callback.
     *
     * @since 1.0.0
     *
     * @param string $id Table identifier (as passed to register()).
     *
     * @return void
     */
    public static function render_table( string $id ): void {
        if ( ! isset( self::$tables[ $id ] ) ) {
            return;
        }

        $config = self::$tables[ $id ];

        // Check view capability
        if ( ! empty( $config['capabilities']['view'] ) ) {
            if ( ! current_user_can( $config['capabilities']['view'] ) ) {
                wp_die( __( 'Sorry, you are not allowed to access this page.', 'arraypress' ) );
            }
        }

        // Create and prepare table instance
        $table = new Table( $id, $config );
        $table->process_bulk_action();
        $table->prepare_items();

        // Build total count for header
        $total_count = '';
        if ( $config['show_count'] ) {
            $counts = $table->get_counts();
            $total  = $counts['total'] ?? 0;
            if ( $total > 0 ) {
                $total_count = sprintf(
                        ' <span class="count">(%s)</span>',
                        esc_html( number_format_i18n( $total ) )
                );
            }
        }

        // Render header outside .wrap (EDD pattern)
        self::render_header( $config, $total_count );

        // Start WordPress wrap
        ?>
        <div class="wrap">
            <?php self::render_admin_notices( $id, $config ); ?>
            <?php self::render_search_results_banner( $config ); ?>

            <?php
            /**
             * Fires before the table is rendered
             *
             * @since 1.0.0
             *
             * @param string $id     Table identifier.
             * @param array  $config Table configuration.
             */
            do_action( 'arraypress_before_render_table', $id, $config );

            /**
             * Fires before a specific table is rendered
             *
             * @since 1.0.0
             *
             * @param array $config Table configuration.
             */
            do_action( "arraypress_before_render_table_{$id}", $config );
            ?>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $config['page'] ); ?>">

                <?php
                // Preserve essential params (not nonce, action, etc.)
                $preserve_params = [ 'status' ];

                // Add filter keys to preserve list
                foreach ( $config['filters'] as $filter_key => $filter ) {
                    $preserve_params[] = $filter_key;
                }

                foreach ( $preserve_params as $key ) {
                    if ( isset( $_GET[ $key ] ) && $_GET[ $key ] !== '' ) {
                        printf(
                                '<input type="hidden" name="%s" value="%s">',
                                esc_attr( $key ),
                                esc_attr( sanitize_text_field( $_GET[ $key ] ) )
                        );
                    }
                }

                // Render table components
                $table->views();

                if ( $config['searchable'] !== false ) {
                    $table->search_box(
                            $config['labels']['search'] ?: __( 'Search', 'arraypress' ),
                            $config['labels']['singular'] ?: 'item'
                    );
                }

                $table->display();
                ?>
            </form>

            <?php
            /**
             * Fires after the table is rendered
             *
             * @since 1.0.0
             *
             * @param string $id     Table identifier.
             * @param array  $config Table configuration.
             */
            do_action( 'arraypress_after_render_table', $id, $config );

            /**
             * Fires after a specific table is rendered
             *
             * @since 1.0.0
             *
             * @param array $config Table configuration.
             */
            do_action( "arraypress_after_render_table_{$id}", $config );
            ?>
        </div>
        <?php
    }

    /**
     * Render the modern header
     *
     * Outputs the EDD-style header with optional logo, title, and add button.
     * Placed outside .wrap for proper WordPress admin styling.
     *
     * @since 1.0.0
     *
     * @param array  $config      Table configuration.
     * @param string $total_count Formatted total count HTML (or empty).
     *
     * @return void
     */
    private static function render_header( array $config, string $total_count ): void {
        $logo_url     = $config['logo'] ?? '';
        $header_title = ! empty( $config['header_title'] )
                ? $config['header_title']
                : $config['labels']['title'];
        $show_title   = $config['show_title'] ?? true;

        ?>
        <div class="list-table-header">
            <div class="list-table-header__inner">
                <div class="list-table-header__branding">
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="list-table-header__logo">
                        <?php if ( $show_title ) : ?>
                            <span class="list-table-header__separator">/</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ( $show_title ) : ?>
                        <h1 class="list-table-header__title">
                            <?php echo esc_html( $header_title ); ?><?php echo $total_count; ?>
                        </h1>
                    <?php endif; ?>
                </div>

                <div class="list-table-header__actions">
                    <?php self::render_add_button( $config ); ?>
                </div>
            </div>
        </div>
        <hr class="wp-header-end">
        <?php
    }

    /**
     * Render the add new button
     *
     * Outputs the "Add New" button using the configured method:
     * 1. Custom callback (add_button_callback)
     * 2. Flyout button (add_flyout)
     * 3. Link button (add_url)
     *
     * @since 1.0.0
     *
     * @param array $config Table configuration.
     *
     * @return void
     */
    private static function render_add_button( array $config ): void {
        if ( empty( $config['labels']['add_new'] ) ) {
            return;
        }

        // Custom callback takes priority
        if ( isset( $config['add_button_callback'] ) && is_callable( $config['add_button_callback'] ) ) {
            echo call_user_func( $config['add_button_callback'] );

            return;
        }

        // Flyout button
        if ( ! empty( $config['add_flyout'] ) && function_exists( 'render_flyout_button' ) ) {
            \render_flyout_button( $config['add_flyout'], [
                    'text'  => $config['labels']['add_new'],
                    'class' => 'page-title-action',
                    'icon'  => 'plus-alt',
            ] );

            return;
        }

        // URL button
        if ( ! empty( $config['add_url'] ) ) {
            $url = is_callable( $config['add_url'] )
                    ? call_user_func( $config['add_url'] )
                    : $config['add_url'];

            printf(
                    '<a href="%s" class="page-title-action"><span class="dashicons dashicons-plus-alt"></span> %s</a>',
                    esc_url( $url ),
                    esc_html( $config['labels']['add_new'] )
            );
        }
    }

    /**
     * Render search results banner
     *
     * Shows a banner when search results are being displayed,
     * with a link to clear the search.
     *
     * @since 1.0.0
     *
     * @param array $config Table configuration.
     *
     * @return void
     */
    private static function render_search_results_banner( array $config ): void {
        $search = sanitize_text_field( $_GET['s'] ?? '' );

        if ( empty( $search ) ) {
            return;
        }

        $clear_url = remove_query_arg( 's', self::get_clean_base_url( $config ) );
        $plural    = $config['labels']['plural'] ?? 'items';

        ?>
        <div class="list-table-search-banner">
            <span class="list-table-search-banner__text">
                <span class="dashicons dashicons-search"></span>
                <?php
                printf(
                /* translators: 1: search term, 2: plural item name */
                        esc_html__( 'Search results for %1$s in %2$s', 'arraypress' ),
                        '<strong>"' . esc_html( $search ) . '"</strong>',
                        esc_html( $plural )
                );
                ?>
            </span>
            <a href="<?php echo esc_url( $clear_url ); ?>" class="list-table-search-banner__clear">
                <?php esc_html_e( 'Clear search', 'arraypress' ); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Render admin notices
     *
     * Displays success/error messages based on URL parameters
     * from action processing (deleted, updated, error).
     *
     * @since 1.0.0
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     */
    private static function render_admin_notices( string $id, array $config ): void {
        $singular = $config['labels']['singular'] ?? 'item';
        $plural   = $config['labels']['plural'] ?? 'items';

        // Deleted notice
        if ( isset( $_GET['deleted'] ) ) {
            $count = absint( $_GET['deleted'] );

            if ( $count > 0 ) {
                $message = sprintf(
                        _n(
                                '%s ' . $singular . ' deleted successfully.',
                                '%s ' . $plural . ' deleted successfully.',
                                $count,
                                'arraypress'
                        ),
                        number_format_i18n( $count )
                );
                $type    = 'success';
            } else {
                $message = __( 'Delete failed. Please try again.', 'arraypress' );
                $type    = 'error';
            }

            printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr( $type ),
                    esc_html( $message )
            );
        }

        // Updated notice
        if ( isset( $_GET['updated'] ) ) {
            $count = absint( $_GET['updated'] );

            if ( $count > 0 ) {
                $message = sprintf(
                        _n(
                                '%s ' . $singular . ' updated successfully.',
                                '%s ' . $plural . ' updated successfully.',
                                $count,
                                'arraypress'
                        ),
                        number_format_i18n( $count )
                );

                printf(
                        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                        esc_html( $message )
                );
            }
        }

        // Error notice
        if ( isset( $_GET['error'] ) ) {
            $error = sanitize_text_field( $_GET['error'] );

            if ( ! empty( $error ) ) {
                printf(
                        '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                        esc_html( $error )
                );
            }
        }

        /**
         * Filter custom admin notices for a table
         *
         * @since 1.0.0
         *
         * @param array  $notices Array of notices. Each notice should have:
         *                        - 'message' (string) Notice text
         *                        - 'type' (string) Notice type: success, error, warning, info
         *                        - 'dismissible' (bool) Whether notice is dismissible (default true)
         * @param string $id      Table identifier.
         * @param array  $config  Table configuration.
         */
        $custom_notices = apply_filters( 'arraypress_table_admin_notices', [], $id, $config );

        /**
         * Filter custom admin notices for a specific table
         *
         * @since 1.0.0
         *
         * @param array $notices Array of notices (see above for format).
         * @param array $config  Table configuration.
         */
        $custom_notices = apply_filters( "arraypress_table_admin_notices_{$id}", $custom_notices, $config );

        foreach ( $custom_notices as $notice ) {
            if ( empty( $notice['message'] ) ) {
                continue;
            }

            $type        = $notice['type'] ?? 'info';
            $dismissible = $notice['dismissible'] ?? true;
            $class       = 'notice notice-' . esc_attr( $type );

            if ( $dismissible ) {
                $class .= ' is-dismissible';
            }

            printf(
                    '<div class="%s"><p>%s</p></div>',
                    esc_attr( $class ),
                    esc_html( $notice['message'] )
            );
        }
    }

    /* =========================================================================
     * UTILITY METHODS
     * ========================================================================= */

    /**
     * Get clean base URL for redirects
     *
     * Builds a URL with the page parameter and preserves status, search,
     * and custom filter parameters. Used for post-action redirects.
     *
     * @since 1.0.0
     *
     * @param array $config Table configuration.
     *
     * @return string Clean admin URL.
     */
    private static function get_clean_base_url( array $config ): string {
        $url = add_query_arg( 'page', $config['page'], admin_url( 'admin.php' ) );

        // Preserve status filter
        if ( ! empty( $_GET['status'] ) ) {
            $url = add_query_arg( 'status', sanitize_key( $_GET['status'] ), $url );
        }

        // Preserve search
        if ( ! empty( $_GET['s'] ) ) {
            $url = add_query_arg( 's', sanitize_text_field( $_GET['s'] ), $url );
        }

        // Preserve custom filters
        if ( ! empty( $config['filters'] ) ) {
            foreach ( $config['filters'] as $filter_key => $filter ) {
                if ( ! empty( $_GET[ $filter_key ] ) ) {
                    $url = add_query_arg( $filter_key, sanitize_text_field( $_GET[ $filter_key ] ), $url );
                }
            }
        }

        return $url;
    }

    /* =========================================================================
     * BODY CLASSES
     * ========================================================================= */

    /**
     * Add body classes to admin table pages
     *
     * Adds CSS classes to the admin body element for styling table pages.
     * Classes added:
     * - `admin-table` - Generic class for all table pages
     * - `admin-table-{$id}` - Table-specific class
     * - Custom class from `body_class` config option
     *
     * @since 1.0.0
     *
     * @param string $classes Space-separated list of body classes.
     *
     * @return string Modified classes string.
     */
    public static function add_body_class( string $classes ): string {
        $page = $_GET['page'] ?? '';

        if ( empty( $page ) ) {
            return $classes;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( $config['page'] === $page ) {
                // Add generic table class
                $classes .= ' admin-table';

                // Add table-specific class
                $classes .= ' admin-table-' . sanitize_html_class( $id );

                // Add custom class from config if provided
                if ( ! empty( $config['body_class'] ) ) {
                    $classes .= ' ' . sanitize_html_class( $config['body_class'] );
                }

                break;
            }
        }

        return $classes;
    }

    /* =========================================================================
     * TABLE MANAGEMENT
     * ========================================================================= */

    /**
     * Get a registered table configuration
     *
     * @since 1.0.0
     *
     * @param string $id Table identifier.
     *
     * @return array|null Table configuration or null if not found.
     */
    public static function get_table( string $id ): ?array {
        return self::$tables[ $id ] ?? null;
    }

    /**
     * Check if a table is registered
     *
     * @since 1.0.0
     *
     * @param string $id Table identifier.
     *
     * @return bool True if registered.
     */
    public static function has_table( string $id ): bool {
        return isset( self::$tables[ $id ] );
    }

    /**
     * Unregister a table
     *
     * Removes a table from the registry. Useful for conditional table removal.
     *
     * @since 1.0.0
     *
     * @param string $id Table identifier.
     *
     * @return bool True if removed, false if not found.
     */
    public static function unregister( string $id ): bool {
        if ( isset( self::$tables[ $id ] ) ) {
            unset( self::$tables[ $id ] );

            return true;
        }

        return false;
    }

    /**
     * Get all registered tables
     *
     * @since 1.0.0
     *
     * @return array All registered table configurations.
     */
    public static function get_all_tables(): array {
        return self::$tables;
    }

}