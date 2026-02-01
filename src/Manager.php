<?php
/**
 * Admin Tables Registration Manager
 *
 * Manages registration and rendering of admin tables.
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
 * Manages registration and rendering of admin tables.
 *
 * @since 1.0.0
 */
class Manager {

    /**
     * Registered tables
     *
     * @since 1.0.0
     * @var array<string, array>
     */
    private static array $tables = [];

    /**
     * Whether assets have been enqueued
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $assets_enqueued = false;

    /**
     * Register an admin table
     *
     * Registers a new admin table with configuration for columns, actions, and data handling.
     *
     * @param string $id     Unique table identifier
     * @param array  $config Table configuration array
     *
     * @return void
     * @since 1.0.0
     */
    public static function register( string $id, array $config ): void {
        $defaults = [
                'labels'              => [],
                'callbacks'           => [],
                'page'                => '',
                'flyout'              => '',
                'add_flyout'          => '',
                'add_button_callback' => null,
                'columns'             => [],
                'sortable'            => [],
                'primary_column'      => '',
                'hidden_columns'      => [],
                'column_widths'       => [],
                'row_actions'         => [],
                'bulk_actions'        => [],
                'views'               => [],
                'filters'             => [],
                'status_styles'       => [],
                'base_query_args'     => [],
                'per_page'            => 30,
                'searchable'          => true,
                'capabilities'        => [],
                'show_count'          => false,
                'auto_delete_action'  => true,
                'help'                => [],

            // Header options
                'logo'                => '',
                'header_title'        => '',
                'show_title'          => true,
        ];

        $config = wp_parse_args( $config, $defaults );

        // Parse labels with defaults
        $config['labels'] = wp_parse_args( $config['labels'], [
                'singular'         => '',
                'plural'           => '',
                'title'            => '',
                'add_new'          => '',
                'search'           => '',
                'not_found'        => '',
                'not_found_search' => ''
        ] );

        // Parse callbacks with defaults
        $config['callbacks'] = wp_parse_args( $config['callbacks'], [
                'get_items'  => null,
                'get_counts' => null,
                'delete'     => null,
                'update'     => null
        ] );

        // Parse capabilities with defaults
        $config['capabilities'] = wp_parse_args( $config['capabilities'], [
                'view'   => '',
                'edit'   => '',
                'delete' => '',
                'bulk'   => ''
        ] );

        // Auto-generate missing labels
        if ( empty( $config['labels']['title'] ) && ! empty( $config['labels']['plural'] ) ) {
            $config['labels']['title'] = ucfirst( $config['labels']['plural'] );
        }

        if ( empty( $config['labels']['add_new'] ) && ! empty( $config['labels']['singular'] ) ) {
            $config['labels']['add_new'] = sprintf(
                    __( 'Add New %s', 'arraypress' ),
                    ucfirst( $config['labels']['singular'] )
            );
        }

        if ( empty( $config['labels']['search'] ) && ! empty( $config['labels']['plural'] ) ) {
            $config['labels']['search'] = sprintf(
                    __( 'Search %s', 'arraypress' ),
                    $config['labels']['plural']
            );
        }

        // Auto-detect primary column if not set
        if ( empty( $config['primary_column'] ) && ! empty( $config['columns'] ) ) {
            foreach ( $config['columns'] as $key => $column ) {
                if ( is_array( $column ) && ! empty( $column['primary'] ) ) {
                    $config['primary_column'] = $key;
                    break;
                }
            }
            // If still empty, use first non-cb column
            if ( empty( $config['primary_column'] ) ) {
                foreach ( $config['columns'] as $key => $column ) {
                    if ( $key !== 'cb' ) {
                        $config['primary_column'] = $key;
                        break;
                    }
                }
            }
        }

        // Store configuration
        self::$tables[ $id ] = $config;
    }

    /**
     * Initialize early action processing
     *
     * Hooks into admin_init to process redirects before any output.
     *
     * @return void
     * @since 1.0.0
     */
    public static function init(): void {
        // Process actions early (before output)
        add_action( 'admin_init', [ __CLASS__, 'process_early_actions' ], 20 );

        // Setup screen options - needs to happen on load-{hook} which we detect
        add_action( 'admin_init', [ __CLASS__, 'setup_load_hooks' ], 999 );

        // Enqueue assets
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    /**
     * Enqueue assets for admin tables
     *
     * @param string $hook Current admin page hook
     *
     * @return void
     * @since 1.0.0
     */
    public static function enqueue_assets( string $hook ): void {
        // Check if we're on one of our pages
        $page = $_GET['page'] ?? '';

        if ( empty( $page ) ) {
            return;
        }

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
     * @param array $config Table configuration
     *
     * @return void
     * @since 1.0.0
     */
    private static function do_enqueue_assets( array $config ): void {
        if ( self::$assets_enqueued ) {
            return;
        }

        self::$assets_enqueued = true;

        // Enqueue CSS from composer assets
        if ( function_exists( 'wp_enqueue_composer_style' ) ) {
            wp_enqueue_composer_style(
                    'list-table-styles',
                    __FILE__,
                    'css/admin-tables.css'
            );
        }

        // Output dynamic styles for column widths
        self::output_dynamic_styles( $config );
    }

    /**
     * Output dynamic styles for column widths and alignments
     *
     * @param array $config Table configuration
     *
     * @return void
     * @since 1.0.0
     */
    private static function output_dynamic_styles( array $config ): void {
        $styles = '';

        // Add custom column widths
        if ( ! empty( $config['column_widths'] ) ) {
            foreach ( $config['column_widths'] as $column => $width ) {
                $styles .= sprintf(
                        ".wp-list-table .column-%s { width: %s; }\n",
                        esc_attr( $column ),
                        esc_attr( $width )
                );
            }
        }

        // Add column alignments from column config
        if ( ! empty( $config['columns'] ) ) {
            foreach ( $config['columns'] as $column => $col_config ) {
                if ( is_array( $col_config ) && ! empty( $col_config['align'] ) ) {
                    $align = in_array( $col_config['align'], [ 'left', 'center', 'right' ], true )
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

    /**
     * Setup load hooks for screen options
     *
     * This runs after tables are registered and sets up the load-{page} hooks.
     *
     * @return void
     * @since 1.0.0
     */
    public static function setup_load_hooks(): void {
        global $pagenow;

        // Only on admin.php
        if ( $pagenow !== 'admin.php' ) {
            return;
        }

        $page = $_GET['page'] ?? '';

        if ( empty( $page ) ) {
            return;
        }

        // Find our table config for this page
        foreach ( self::$tables as $id => $config ) {
            if ( ( $config['page'] ?? '' ) === $page ) {
                // We're on one of our pages - setup screen options now
                add_action( 'current_screen', function () use ( $config ) {
                    self::setup_screen( $config );
                    self::handle_screen_options();
                } );
                break;
            }
        }
    }

    /**
     * Process actions that require redirects early (before output)
     *
     * @return void
     * @since 1.0.0
     */
    public static function process_early_actions(): void {
        // Check if we're on an admin page with our tables
        $page = $_GET['page'] ?? '';

        if ( empty( $page ) ) {
            return;
        }

        // Find the table for this page
        foreach ( self::$tables as $id => $config ) {
            if ( $config['page'] === $page ) {
                // Process filter redirect (clean up URL after filter)
                self::process_filter_redirect( $id, $config );

                // Process single actions
                self::process_single_actions( $id, $config );

                // Process bulk actions
                self::process_bulk_actions( $id, $config );
                break;
            }
        }
    }

    /**
     * Process filter form submission and redirect to clean URL
     *
     * @param string $id     Table identifier
     * @param array  $config Table configuration
     *
     * @return void
     * @since 1.0.0
     */
    private static function process_filter_redirect( string $id, array $config ): void {
        // Check if this is a filter submission
        if ( ! isset( $_GET['filter_action'] ) ) {
            return;
        }

        // Build clean URL with only necessary params
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

        // Redirect to clean URL
        wp_safe_redirect( add_query_arg( $clean_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Process single item actions
     *
     * Handles individual item actions like delete before the page renders.
     *
     * @param string $id     Table identifier
     * @param array  $config Table configuration
     *
     * @return void
     * @since 1.0.0
     */
    private static function process_single_actions( string $id, array $config ): void {
        $action  = sanitize_key( $_GET['action'] ?? '' );
        $item_id = absint( $_GET['item'] ?? 0 );

        if ( empty( $action ) || empty( $item_id ) ) {
            return;
        }

        // Skip bulk action values
        if ( $action === '-1' ) {
            return;
        }

        $singular = $config['labels']['singular'] ?? 'item';

        // Handle delete action (built-in)
        if ( $action === 'delete' ) {
            self::handle_delete_action( $id, $config, $item_id );
            return;
        }

        // Check for custom action handler in row_actions config
        if ( ! is_callable( $config['row_actions'] ) && isset( $config['row_actions'][ $action ] ) ) {
            $action_config = $config['row_actions'][ $action ];

            if ( is_array( $action_config ) && isset( $action_config['handler'] ) && is_callable( $action_config['handler'] ) ) {
                // Determine nonce action
                $nonce_action = $action_config['nonce_action'] ?? "{$action}_{$singular}_{$item_id}";
                $nonce_action = str_replace( '{id}', (string) $item_id, $nonce_action );

                // Verify nonce
                $nonce = $_GET['_wpnonce'] ?? '';
                if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
                    wp_die( __( 'Security check failed.', 'arraypress' ) );
                }

                // Check capability if set
                if ( ! empty( $action_config['capability'] ) ) {
                    if ( ! current_user_can( $action_config['capability'] ) ) {
                        wp_die( __( 'You do not have permission to perform this action.', 'arraypress' ) );
                    }
                }

                // Call the handler
                $result = call_user_func( $action_config['handler'], $item_id, $config );

                // Build redirect URL
                $redirect_url = self::get_clean_base_url( $config );

                // Add result message if handler returned something
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
        }

        /**
         * Fires when a custom single action is triggered.
         *
         * Allows handling of custom single-item actions beyond the built-in delete.
         *
         * @param string $action  Action being performed
         * @param int    $item_id Item ID
         * @param array  $config  Table configuration
         *
         * @since 1.0.0
         */
        do_action( "arraypress_table_single_action_{$id}", $action, $item_id, $config );
    }

    /**
     * Handle built-in delete action
     *
     * @param string $id      Table identifier
     * @param array  $config  Table configuration
     * @param int    $item_id Item ID
     *
     * @return void
     * @since 1.0.0
     */
    private static function handle_delete_action( string $id, array $config, int $item_id ): void {
        if ( ! isset( $config['callbacks']['delete'] ) || ! is_callable( $config['callbacks']['delete'] ) ) {
            return;
        }

        $singular = $config['labels']['singular'] ?? 'item';

        // Verify nonce
        $nonce = $_GET['_wpnonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, "delete_{$singular}_{$item_id}" ) ) {
            wp_die( __( 'Security check failed.', 'arraypress' ) );
        }

        // Check capability if set
        if ( ! empty( $config['capabilities']['delete'] ) ) {
            if ( ! current_user_can( $config['capabilities']['delete'] ) ) {
                wp_die( __( 'You do not have permission to delete this item.', 'arraypress' ) );
            }
        }

        $result = call_user_func( $config['callbacks']['delete'], $item_id );

        /**
         * Fires after a single item is deleted.
         *
         * @param int    $item_id Item ID that was deleted
         * @param mixed  $result  Result from delete callback
         * @param string $id      Table identifier
         * @param array  $config  Table configuration
         *
         * @since 1.0.0
         */
        do_action( 'arraypress_table_item_deleted', $item_id, $result, $id, $config );

        /**
         * Fires after a single item is deleted from a specific table.
         *
         * @param int   $item_id Item ID that was deleted
         * @param mixed $result  Result from delete callback
         * @param array $config  Table configuration
         *
         * @since 1.0.0
         */
        do_action( "arraypress_table_item_deleted_{$id}", $item_id, $result, $config );

        // Redirect to clean URL
        $redirect_url = self::get_clean_base_url( $config );
        $redirect_url = add_query_arg( 'deleted', $result ? 1 : 0, $redirect_url );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Get clean base URL for redirects
     *
     * @param array $config Table configuration
     *
     * @return string Clean URL
     * @since 1.0.0
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

    /**
     * Process bulk actions
     *
     * Handles bulk actions before the page renders.
     *
     * @param string $id     Table identifier
     * @param array  $config Table configuration
     *
     * @return void
     * @since 1.0.0
     */
    private static function process_bulk_actions( string $id, array $config ): void {
        // Check for bulk action
        $action = '';
        if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] !== '-1' ) {
            $action = sanitize_key( $_REQUEST['action'] );
        } elseif ( isset( $_REQUEST['action2'] ) && $_REQUEST['action2'] !== '-1' ) {
            $action = sanitize_key( $_REQUEST['action2'] );
        }

        if ( empty( $action ) ) {
            return;
        }

        // Get plural for nonce and field name
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

        // Get bulk action config
        $action_config = $config['bulk_actions'][ $action ] ?? null;

        if ( ! $action_config ) {
            return;
        }

        // Convert string to array
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
         * Fires when a bulk action is processed.
         *
         * @param array  $items  Selected item IDs
         * @param string $action Action key being performed
         * @param string $id     Table identifier
         *
         * @since 1.0.0
         */
        do_action( 'arraypress_table_bulk_action', $items, $action, $id );

        /**
         * Fires when a bulk action is processed for a specific table.
         *
         * @param array  $items  Selected item IDs
         * @param string $action Action key being performed
         *
         * @since 1.0.0
         */
        do_action( "arraypress_table_bulk_action_{$id}", $items, $action );

        /**
         * Fires when a specific bulk action is processed for a specific table.
         *
         * @param array $items Selected item IDs
         *
         * @since 1.0.0
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
            // No callback - assume success for item count
            $redirect_args = [ 'updated' => count( $items ) ];
        }

        // Redirect with notice parameters
        $redirect_url = self::get_clean_base_url( $config );

        if ( ! empty( $redirect_args ) ) {
            $redirect_url = add_query_arg( $redirect_args, $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Render admin notices for table actions
     *
     * Displays success/error messages after bulk or single actions.
     *
     * @param string $id     Table identifier
     * @param array  $config Table configuration
     *
     * @return void
     * @since 1.0.0
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
                $type = 'success';
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
         * Filters custom admin notices for a table.
         *
         * @param array  $notices Array of notices, each with 'message' and 'type' keys
         * @param string $id      Table identifier
         * @param array  $config  Table configuration
         *
         * @since 1.0.0
         */
        $custom_notices = apply_filters( 'arraypress_table_admin_notices', [], $id, $config );

        /**
         * Filters custom admin notices for a specific table.
         *
         * @param array $notices Array of notices, each with 'message' and 'type' keys
         * @param array $config  Table configuration
         *
         * @since 1.0.0
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

    /**
     * Setup screen options and help tabs
     *
     * Configures per-page screen options and help tab content.
     *
     * @param array $config Table configuration
     *
     * @return void
     * @since 1.0.0
     */
    private static function setup_screen( array $config ): void {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return;
        }

        // Add per page screen option
        $per_page_label = ! empty( $config['labels']['plural'] )
                ? sprintf( __( '%s per page', 'arraypress' ), $config['labels']['plural'] )
                : __( 'Items per page', 'arraypress' );

        $screen->add_option( 'per_page', [
                'label'   => $per_page_label,
                'default' => $config['per_page'],
                'option'  => 'per_page'
        ] );

        // Add help tabs if configured
        if ( ! empty( $config['help'] ) ) {
            foreach ( $config['help'] as $key => $help ) {
                if ( $key === 'sidebar' ) {
                    $screen->set_help_sidebar( $help );
                    continue;
                }

                if ( is_array( $help ) && isset( $help['title'] ) ) {
                    $content = '';

                    if ( isset( $help['callback'] ) && is_callable( $help['callback'] ) ) {
                        $content = call_user_func( $help['callback'] );
                    } elseif ( isset( $help['content'] ) ) {
                        $content = $help['content'];
                    }

                    $screen->add_help_tab( [
                            'id'      => sanitize_key( $key ),
                            'title'   => $help['title'],
                            'content' => $content
                    ] );
                }
            }
        }
    }

    /**
     * Handle screen option saving
     *
     * Filters the screen option value to allow per-page saving.
     *
     * @return void
     * @since 1.0.0
     */
    private static function handle_screen_options(): void {
        add_filter( 'set-screen-option', function ( $status, $option, $value ) {
            if ( $option === 'per_page' ) {
                return absint( $value );
            }

            return $status;
        }, 10, 3 );
    }

    /**
     * Render a registered table
     *
     * Outputs the complete admin page with the table.
     *
     * @param string $id Table identifier
     *
     * @return void
     * @since 1.0.0
     */
    public static function render_table( string $id ): void {
        if ( ! isset( self::$tables[ $id ] ) ) {
            return;
        }

        $config = self::$tables[ $id ];

        // Check capabilities if set
        if ( ! empty( $config['capabilities']['view'] ) ) {
            if ( ! current_user_can( $config['capabilities']['view'] ) ) {
                wp_die( __( 'Sorry, you are not allowed to access this page.', 'arraypress' ) );
            }
        }

        // Create table instance
        $table = new Table( $id, $config );

        // Process bulk actions if needed
        $table->process_bulk_action();

        // Prepare items
        $table->prepare_items();

        // Get total count for title if needed
        $total_count = '';
        if ( $config['show_count'] ) {
            $counts = $table->get_counts();
            $total  = $counts['total'] ?? 0;
            if ( $total > 0 ) {
                $total_count = sprintf( ' <span class="count">(%s)</span>', esc_html( number_format_i18n( $total ) ) );
            }
        }

        // Render header OUTSIDE the wrap (WordPress-native pattern)
        self::render_header( $config, $total_count );

        // Start standard WordPress wrap
        ?>
        <div class="wrap">
            <?php self::render_admin_notices( $id, $config ); ?>

            <?php self::render_search_results_banner( $config ); ?>

            <?php
            /**
             * Fires before the table is rendered.
             *
             * @param string $id     Table identifier
             * @param array  $config Table configuration
             *
             * @since 1.0.0
             */
            do_action( 'arraypress_before_render_table', $id, $config );

            /**
             * Fires before a specific table is rendered.
             *
             * @param array $config Table configuration
             *
             * @since 1.0.0
             */
            do_action( "arraypress_before_render_table_{$id}", $config );
            ?>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $config['page'] ); ?>">

                <?php
                // Only preserve essential params - NOT nonce, action, etc.
                $preserve_params = [ 'status' ];

                // Add filter keys
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
             * Fires after the table is rendered.
             *
             * @param string $id     Table identifier
             * @param array  $config Table configuration
             *
             * @since 1.0.0
             */
            do_action( 'arraypress_after_render_table', $id, $config );

            /**
             * Fires after a specific table is rendered.
             *
             * @param array $config Table configuration
             *
             * @since 1.0.0
             */
            do_action( "arraypress_after_render_table_{$id}", $config );
            ?>
        </div>
        <?php
    }

    /**
     * Render the modern header
     *
     * @param array  $config      Table configuration
     * @param string $total_count Formatted total count HTML
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_header( array $config, string $total_count ): void {
        $logo_url     = $config['logo'] ?? '';
        $header_title = ! empty( $config['header_title'] ) ? $config['header_title'] : $config['labels']['title'];
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
     * @param array $config Table configuration
     *
     * @return void
     * @since 1.0.0
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

        // Dedicated add flyout
        if ( ! empty( $config['add_flyout'] ) && function_exists( 'render_flyout_button' ) ) {
            \render_flyout_button( $config['add_flyout'], [
                    'text'  => $config['labels']['add_new'],
                    'class' => 'page-title-action',
                    'icon'  => 'plus-alt',
            ] );

            return;
        }

        // Add URL if configured
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

        // No add button if only edit flyout is configured - that doesn't make sense
    }

    /**
     * Render search results banner
     *
     * @param array $config Table configuration
     *
     * @return void
     * @since 1.0.0
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
                        esc_html__( 'Search results for %s in %s', 'arraypress' ),
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
     * Get a registered table configuration
     *
     * @param string $id Table identifier
     *
     * @return array|null Table configuration or null if not found
     * @since 1.0.0
     */
    public static function get_table( string $id ): ?array {
        return self::$tables[ $id ] ?? null;
    }

    /**
     * Check if a table is registered
     *
     * @param string $id Table identifier
     *
     * @return bool True if registered, false otherwise
     * @since 1.0.0
     */
    public static function has_table( string $id ): bool {
        return isset( self::$tables[ $id ] );
    }

    /**
     * Unregister a table
     *
     * @param string $id Table identifier
     *
     * @return bool True if removed, false if not found
     * @since 1.0.0
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
     * @return array All registered table configurations
     * @since 1.0.0
     */
    public static function get_all_tables(): array {
        return self::$tables;
    }

}