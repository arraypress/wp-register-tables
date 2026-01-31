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
     * Whether styles have been enqueued
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $styles_enqueued = false;

    /**
     * Register an admin table
     *
     * Registers a new admin table with configuration for columns, actions, and data handling.
     *
     * @param string $id     Unique table identifier
     * @param array  $config {
     *     Table configuration array.
     *
     *     @type array    $labels           {
     *         Labels for display purposes.
     *
     *         @type string $singular         Singular name (e.g., 'order')
     *         @type string $plural           Plural name (e.g., 'orders')
     *         @type string $title            Page title (e.g., 'Orders')
     *         @type string $add_new          Add new button label (e.g., 'Add New Order')
     *         @type string $search           Search button label (e.g., 'Search Orders')
     *         @type string $not_found        No items message (e.g., 'No orders found.')
     *         @type string $not_found_search No items found in search (e.g., 'No orders found for your search.')
     *     }
     *     @type array    $callbacks        {
     *         Data operation callbacks.
     *
     *         @type callable $get_items  Callback to get items. Receives query args.
     *         @type callable $get_counts Callback to get status counts.
     *         @type callable $delete     Callback to delete single item. Receives ID.
     *         @type callable $update     Callback to update single item. Receives ID and data array.
     *     }
     *     @type string   $page             Admin page slug
     *     @type string   $flyout           Flyout identifier for edit/view actions
     *     @type string   $add_flyout       Separate flyout identifier for adding new items
     *     @type callable $add_button_callback Custom callback to render add button
     *     @type array    $columns          Column definitions
     *     @type array    $sortable         Sortable column configurations
     *     @type string   $primary_column   Primary column key
     *     @type array    $hidden_columns   Array of column IDs to hide by default
     *     @type array    $column_widths    Array of column widths as [column_id => width]
     *     @type array    $row_actions      Row action definitions or callable returning actions
     *     @type array    $bulk_actions     Bulk action definitions
     *     @type array    $views            View/filter definitions
     *     @type array    $filters          Additional filter dropdowns
     *     @type array    $status_styles    Custom status badge styles as [status => class]
     *     @type array    $base_query_args  Default query args that always apply
     *     @type int      $per_page         Items per page (default: 30)
     *     @type bool     $searchable       Whether to show search box (default: true)
     *     @type array    $capabilities     {
     *         Permission requirements.
     *
     *         @type string $view   Capability required to view the table
     *         @type string $edit   Capability required to edit items
     *         @type string $delete Capability required to delete items
     *         @type string $bulk   Capability required for bulk actions
     *     }
     *     @type bool     $show_count       Show item count in title (default: false)
     *     @type bool     $auto_delete_action Whether to auto-generate delete row action (default: true)
     *     @type array    $help             {
     *         Help tab configuration.
     *
     *         @type array  $overview Help tab with 'title' and 'content'
     *         @type string $sidebar  Help sidebar content
     *     }
     * }
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
                'help'                => []
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
        // Process at priority 20 to ensure tables are registered first (usually at priority 10)
        add_action( 'admin_init', [ __CLASS__, 'process_early_actions' ], 20 );

        // Setup screen options after menu is registered
        add_action( 'admin_menu', [ __CLASS__, 'setup_screen_hooks' ], 999 );
    }

    /**
     * Setup screen option hooks for all registered tables
     *
     * @return void
     * @since 1.0.0
     */
    public static function setup_screen_hooks(): void {
        foreach ( self::$tables as $id => $config ) {
            // We need to find the page hook for this table's page
            // The hook name is based on how the menu was registered
            $page = $config['page'] ?? '';

            if ( empty( $page ) ) {
                continue;
            }

            // Hook into load-{page} to setup screen options
            // This fires before the page renders but after current_screen is set
            add_action( 'load-toplevel_page_' . $page, function () use ( $config ) {
                self::setup_screen( $config );
                self::handle_screen_options();
            } );

            // Also try submenu page format
            add_action( 'load-admin_page_' . $page, function () use ( $config ) {
                self::setup_screen( $config );
                self::handle_screen_options();
            } );
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
                self::process_single_actions( $id, $config );
                self::process_bulk_actions( $id, $config );
                break;
            }
        }
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

        $singular = $config['labels']['singular'] ?? 'item';

        // Handle delete action
        if ( $action === 'delete' ) {
            if ( ! isset( $config['callbacks']['delete'] ) || ! is_callable( $config['callbacks']['delete'] ) ) {
                return;
            }

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
             * The dynamic portion of the hook name, `$id`, refers to the table identifier.
             *
             * @param int   $item_id Item ID that was deleted
             * @param mixed $result  Result from delete callback
             * @param array $config  Table configuration
             *
             * @since 1.0.0
             */
            do_action( "arraypress_table_item_deleted_{$id}", $item_id, $result, $config );

            // Clean up URL - remove all form submission parameters
            $redirect_url = remove_query_arg( [
                    'action',
                    'action2',
                    'item',
                    '_wpnonce',
                    '_wp_http_referer',
                    'filter_action',
            ] );
            $redirect_url = add_query_arg( 'deleted', $result ? 1 : 0, $redirect_url );

            wp_safe_redirect( $redirect_url );
            exit;
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
        if ( ! empty( $redirect_args ) ) {
            $base_url = add_query_arg( 'page', $config['page'], admin_url( 'admin.php' ) );

            // Preserve status filter
            if ( ! empty( $_GET['status'] ) ) {
                $base_url = add_query_arg( 'status', sanitize_key( $_GET['status'] ), $base_url );
            }

            $redirect_url = add_query_arg( $redirect_args, $base_url );

            wp_safe_redirect( $redirect_url );
            exit;
        }
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
         * Allows adding custom notices to be displayed on the table page.
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
         * The dynamic portion of the hook name, `$id`, refers to the table identifier.
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

        // Enqueue styles if needed
        self::maybe_enqueue_styles( $config );

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

        // Render the page
        ?>
        <div class="wrap arraypress-table-wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html( $config['labels']['title'] ); ?><?php echo $total_count; ?>
            </h1>

            <?php if ( ! empty( $config['labels']['add_new'] ) ) : ?>
                <?php if ( isset( $config['add_button_callback'] ) && is_callable( $config['add_button_callback'] ) ) : ?>
                    <?php echo call_user_func( $config['add_button_callback'] ); ?>
                <?php elseif ( ! empty( $config['add_flyout'] ) && function_exists( 'render_flyout_button' ) ) : ?>
                    <?php
                    \render_flyout_button( $config['add_flyout'], [
                            'text'  => $config['labels']['add_new'],
                            'class' => 'page-title-action',
                            'icon'  => 'plus-alt',
                    ] );
                    ?>
                <?php elseif ( ! empty( $config['flyout'] ) && function_exists( 'render_flyout_button' ) ) : ?>
                    <?php
                    \render_flyout_button( $config['flyout'], [
                            'text'  => $config['labels']['add_new'],
                            'class' => 'page-title-action',
                            'icon'  => 'plus-alt',
                    ] );
                    ?>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'action', 'add', admin_url( 'admin.php?page=' . $config['page'] ) ) ); ?>"
                       class="page-title-action">
                        <span class="dashicons dashicons-plus-alt" style="vertical-align: text-top;"></span>
                        <?php echo esc_html( $config['labels']['add_new'] ); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <hr class="wp-header-end">

            <?php self::render_admin_notices( $id, $config ); ?>

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
             * The dynamic portion of the hook name, `$id`, refers to the table identifier.
             *
             * @param array $config Table configuration
             *
             * @since 1.0.0
             */
            do_action( "arraypress_before_render_table_{$id}", $config );
            ?>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ?? $config['page'] ); ?>">

                <?php
                // Preserve other query args
                foreach ( $_GET as $key => $value ) {
                    if ( in_array( $key, [ 'page', 'paged', '_wpnonce', '_wp_http_referer', 'deleted', 'updated', 'error' ], true ) ) {
                        continue;
                    }

                    if ( is_array( $value ) ) {
                        foreach ( $value as $v ) {
                            printf(
                                    '<input type="hidden" name="%s[]" value="%s">',
                                    esc_attr( $key ),
                                    esc_attr( $v )
                            );
                        }
                    } else {
                        printf(
                                '<input type="hidden" name="%s" value="%s">',
                                esc_attr( $key ),
                                esc_attr( $value )
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
             * The dynamic portion of the hook name, `$id`, refers to the table identifier.
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
     * Maybe enqueue styles
     *
     * Enqueues table styles if not already enqueued.
     *
     * @param array $config Table configuration for dynamic styles
     *
     * @return void
     * @since 1.0.0
     */
    private static function maybe_enqueue_styles( array $config = [] ): void {
        if ( self::$styles_enqueued ) {
            return;
        }

        self::$styles_enqueued = true;

        // Output styles directly since we're called during page render
        self::output_styles( $config );
    }

    /**
     * Output table styles
     *
     * @param array $config Table configuration for dynamic styles
     *
     * @return void
     * @since 1.0.0
     */
    private static function output_styles( array $config = [] ): void {
        ?>
        <style>
            .arraypress-table-wrap .badge {
                display: inline-block;
                padding: 3px 8px;
                font-size: 12px;
                line-height: 1.2;
                font-weight: 600;
                border-radius: 3px;
                background: #dcdcde;
                color: #2c3338;
            }

            .arraypress-table-wrap .badge-success {
                background: #d4f4dd;
                color: #00a32a;
            }

            .arraypress-table-wrap .badge-warning {
                background: #fcf0e4;
                color: #996800;
            }

            .arraypress-table-wrap .badge-error {
                background: #facfd2;
                color: #d63638;
            }

            .arraypress-table-wrap .badge-info {
                background: #e5f5fa;
                color: #0073aa;
            }

            .arraypress-table-wrap .badge-default {
                background: #f0f0f1;
                color: #50575e;
            }

            .arraypress-table-wrap .price {
                font-weight: 600;
                color: #00a32a;
            }

            .arraypress-table-wrap .recurring-badge {
                display: inline-block;
                padding: 2px 6px;
                margin-left: 4px;
                font-size: 11px;
                font-weight: normal;
                color: #50575e;
                background: #f0f0f1;
                border-radius: 2px;
            }

            .arraypress-table-wrap .text-muted {
                color: #a7aaad;
            }

            .arraypress-table-wrap .unlimited {
                font-size: 18px;
                color: #0073aa;
                font-weight: 600;
            }

            .arraypress-table-wrap .column-cb {
                width: 2.2em;
            }

            .arraypress-table-wrap .delete-link {
                color: #b32d2e;
            }

            .arraypress-table-wrap .delete-link:hover {
                color: #dc3545;
            }

            /* Match WordPress native avatar styling */
            .arraypress-table-wrap .avatar {
                border-radius: 50%;
                vertical-align: middle;
                margin-right: 10px;
            }

            /* Handle primary column with avatar */
            .arraypress-table-wrap .column-primary strong {
                display: inline-block;
                vertical-align: middle;
            }

            <?php
            // Add custom column widths
            if ( ! empty( $config['column_widths'] ) ) {
                foreach ( $config['column_widths'] as $column => $width ) {
                    printf(
                        ".arraypress-table-wrap .column-%s { width: %s; }\n",
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
                        printf(
                            ".arraypress-table-wrap .column-%s { text-align: %s; }\n",
                            esc_attr( $column ),
                            esc_attr( $align )
                        );
                    }
                }
            }
            ?>
        </style>
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