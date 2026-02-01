<?php
/**
 * Table Class
 *
 * Generates a WP_List_Table instance from configuration. This class extends
 * WordPress's WP_List_Table to provide a configuration-driven approach to
 * creating admin list tables with automatic column formatting, row actions,
 * bulk actions, views, and filtering.
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

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use WP_List_Table;

/**
 * Class Table
 *
 * Dynamic table class that extends WP_List_Table based on configuration.
 *
 * ## Usage
 *
 * This class is instantiated automatically by the Manager class. It should not
 * be instantiated directly. Configuration is passed from the registered table.
 *
 * ## Configuration Options
 *
 * The $config array supports the following keys:
 *
 * - `columns`          (array)    Column definitions with labels and optional callbacks
 * - `sortable`         (array)    List of sortable columns
 * - `hidden_columns`   (array)    Columns hidden by default
 * - `primary_column`   (string)   Column to show row actions on
 * - `bulk_actions`     (array)    Bulk action definitions
 * - `row_actions`      (array|callable) Row action definitions or callback
 * - `views`            (array)    Status view definitions
 * - `filters`          (array)    Dropdown filter definitions
 * - `callbacks`        (array)    Data callbacks (get_items, get_counts, delete)
 * - `labels`           (array)    UI labels (singular, plural, etc.)
 * - `per_page`         (int)      Items per page default
 * - `status_styles`    (array)    Custom status => CSS class mappings
 * - `capabilities`     (array)    Required capabilities for actions
 * - `auto_delete_action` (bool)   Auto-add delete row action if delete callback exists
 *
 * ## Filters
 *
 * - `arraypress_table_columns`              - Modify column definitions
 * - `arraypress_table_hidden_columns`       - Modify hidden columns
 * - `arraypress_table_sortable_columns`     - Modify sortable columns
 * - `arraypress_table_query_args`           - Modify query args before fetching items
 * - `arraypress_table_query_args_{$id}`     - Modify query args for specific table
 * - `arraypress_table_row_actions`          - Modify row actions
 * - `arraypress_table_row_actions_{$id}`    - Modify row actions for specific table
 * - `arraypress_table_bulk_actions`         - Modify bulk actions
 * - `arraypress_table_views`                - Modify status views
 *
 * @since 1.0.0
 */
class Table extends WP_List_Table {

    /**
     * Table identifier
     *
     * Unique string identifying this table instance. Used in filter hooks
     * and for generating unique element IDs.
     *
     * @since 1.0.0
     * @var string
     */
    private string $id;

    /**
     * Table configuration
     *
     * Complete configuration array passed during registration.
     * Contains columns, actions, callbacks, and display options.
     *
     * @since 1.0.0
     * @var array
     */
    private array $config;

    /**
     * Current status filter
     *
     * The currently active status filter from the URL query string.
     * Empty string means no status filter (show all).
     *
     * @since 1.0.0
     * @var string
     */
    private string $status = '';

    /**
     * Status counts cache
     *
     * Cached array of item counts per status. Populated by get_counts()
     * and used for view tabs and pagination.
     *
     * @since 1.0.0
     * @var array
     */
    private array $counts = [];

    /**
     * Items per page
     *
     * Number of items to display per page. Retrieved from user meta
     * (screen options) or falls back to config default.
     *
     * @since 1.0.0
     * @var int
     */
    private int $per_page;

    /**
     * Constructor
     *
     * Initializes the table with configuration and sets up the parent
     * WP_List_Table with appropriate labels.
     *
     * @param string $id     Unique table identifier used in hooks and element IDs.
     * @param array  $config Table configuration array containing columns, actions,
     *                       callbacks, and display options.
     *
     * @since 1.0.0
     *
     */
    public function __construct( string $id, array $config ) {
        $this->id     = $id;
        $this->config = $config;

        // Get current status from URL
        $this->status = sanitize_key( $_GET['status'] ?? '' );

        // Get per page from screen options or use config default
        $this->per_page = $this->get_items_per_page( 'per_page', $config['per_page'] );

        // Initialize parent WP_List_Table
        parent::__construct( [
                'singular' => $config['labels']['singular'] ?? 'item',
                'plural'   => $config['labels']['plural'] ?? 'items',
                'ajax'     => false
        ] );
    }

    /* =========================================================================
     * COLUMN DEFINITIONS
     * ========================================================================= */

    /**
     * Get column definitions
     *
     * Returns array of column key => label pairs for the table header.
     * Automatically adds checkbox column if bulk actions are configured.
     *
     * @return array Column definitions where keys are column identifiers
     *               and values are display labels.
     * @since 1.0.0
     *
     */
    public function get_columns(): array {
        $columns = [];

        // Add checkbox column if bulk actions exist
        if ( ! empty( $this->config['bulk_actions'] ) ) {
            $columns['cb'] = '<input type="checkbox" />';
        }

        // Build columns from config
        foreach ( $this->config['columns'] as $key => $column ) {
            if ( is_string( $column ) ) {
                $columns[ $key ] = $column;
            } elseif ( is_array( $column ) && isset( $column['label'] ) ) {
                $columns[ $key ] = $column['label'];
            }
        }

        /**
         * Filter the table columns
         *
         * @param array  $columns Column definitions.
         * @param string $id      Table identifier.
         * @param array  $config  Table configuration.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_columns', $columns, $this->id, $this->config );
    }

    /**
     * Get hidden columns
     *
     * Returns array of column keys that should be hidden by default.
     * Users can show/hide columns via Screen Options.
     *
     * @return array Column keys to hide by default.
     * @since 1.0.0
     *
     */
    public function get_hidden_columns(): array {
        $hidden = $this->config['hidden_columns'] ?? [];

        /**
         * Filter the hidden columns
         *
         * @param array  $hidden Hidden column keys.
         * @param string $id     Table identifier.
         * @param array  $config Table configuration.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_hidden_columns', $hidden, $this->id, $this->config );
    }

    /**
     * Get sortable columns
     *
     * Returns array defining which columns are sortable and their
     * default sort direction.
     *
     * @return array Sortable column definitions where keys are column names
     *               and values are [orderby, is_descending_default] arrays.
     * @since 1.0.0
     *
     */
    public function get_sortable_columns(): array {
        $sortable = [];

        foreach ( $this->config['sortable'] as $key => $sort ) {
            if ( is_numeric( $key ) ) {
                // Simple format: ['column1', 'column2']
                $sortable[ $sort ] = [ $sort, false ];
            } else {
                // Advanced format: ['column' => ['orderby', true]]
                $sortable[ $key ] = $sort;
            }
        }

        /**
         * Filter the sortable columns
         *
         * @param array  $sortable Sortable column definitions.
         * @param string $id       Table identifier.
         * @param array  $config   Table configuration.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_sortable_columns', $sortable, $this->id, $this->config );
    }

    /**
     * Get primary column name
     *
     * Returns the column that should display row actions.
     * Falls back to parent method if not configured.
     *
     * @return string Primary column identifier.
     * @since 1.0.0
     *
     */
    protected function get_primary_column_name(): string {
        return $this->config['primary_column'] ?? parent::get_primary_column_name();
    }

    /* =========================================================================
     * DATA RETRIEVAL
     * ========================================================================= */

    /**
     * Get table data
     *
     * Retrieves items for display by calling the configured get_items callback.
     * Applies pagination, sorting, search, status filter, and custom filters.
     *
     * @return array Array of item objects to display.
     * @since 1.0.0
     *
     */
    public function get_data(): array {
        // Start with base query args
        $args = [];
        if ( ! empty( $this->config['base_query_args'] ) ) {
            $args = $this->config['base_query_args'];
        }

        // Add pagination and sorting
        $args = array_merge( $args, $this->parse_pagination_args() );

        // Add search
        $search = $this->get_search();
        if ( ! empty( $search ) ) {
            $args['search'] = $search;
        }

        // Add status filter
        if ( ! empty( $this->status ) ) {
            $args['status'] = $this->status;
        }

        // Process custom filters
        foreach ( $this->config['filters'] as $filter_key => $filter ) {
            if ( ! isset( $_GET[ $filter_key ] ) ) {
                continue;
            }

            $value = sanitize_text_field( $_GET[ $filter_key ] );

            if ( empty( $value ) ) {
                continue;
            }

            // Check for custom apply callback
            if ( is_array( $filter ) && isset( $filter['apply_callback'] ) && is_callable( $filter['apply_callback'] ) ) {
                /**
                 * Custom filter application callback
                 *
                 * Allows complex query modifications that can't be expressed as
                 * simple key => value pairs. The callback receives the args array
                 * by reference and the filter value.
                 *
                 * Example usage:
                 * ```php
                 * 'date_range' => [
                 *     'label' => 'All Dates',
                 *     'options' => ['today' => 'Today', 'this_week' => 'This Week'],
                 *     'apply_callback' => function(&$args, $value) {
                 *         $range = Dates::get_range($value);
                 *         $args['date_query'] = [
                 *             'after'  => $range['start'],
                 *             'before' => $range['end'],
                 *         ];
                 *     },
                 * ]
                 * ```
                 */
                call_user_func_array( $filter['apply_callback'], [ &$args, $value ] );
            } else {
                // Simple assignment
                $args[ $filter_key ] = $value;
            }
        }

        /**
         * Filter the query arguments before fetching items
         *
         * @param array  $args   Query arguments.
         * @param string $id     Table identifier.
         * @param array  $config Table configuration.
         *
         * @since 1.0.0
         *
         */
        $args = apply_filters( 'arraypress_table_query_args', $args, $this->id, $this->config );

        /**
         * Filter query arguments for a specific table
         *
         * @param array $args   Query arguments.
         * @param array $config Table configuration.
         *
         * @since 1.0.0
         *
         */
        $args = apply_filters( "arraypress_table_query_args_{$this->id}", $args, $this->config );

        // Call the get_items callback
        if ( isset( $this->config['callbacks']['get_items'] ) && is_callable( $this->config['callbacks']['get_items'] ) ) {
            return call_user_func( $this->config['callbacks']['get_items'], $args );
        }

        return [];
    }

    /**
     * Get status counts
     *
     * Retrieves and caches item counts per status. Used for view tabs
     * and determining total items for pagination.
     *
     * @return array Associative array of status => count pairs.
     *               Always includes 'total' key.
     * @since 1.0.0
     *
     */
    public function get_counts(): array {
        // Return cached counts if available
        if ( ! empty( $this->counts ) ) {
            return $this->counts;
        }

        // Call the get_counts callback
        if ( isset( $this->config['callbacks']['get_counts'] ) && is_callable( $this->config['callbacks']['get_counts'] ) ) {
            $this->counts = call_user_func( $this->config['callbacks']['get_counts'] );
        }

        // Ensure total exists
        if ( empty( $this->counts ) ) {
            $this->counts = [ 'total' => 0 ];
        }

        return $this->counts;
    }

    /* =========================================================================
     * COLUMN RENDERING
     * ========================================================================= */

    /**
     * Default column renderer
     *
     * Renders column content when no specific column_* method exists.
     * Checks for configured callbacks, then falls back to automatic
     * formatting based on column name patterns.
     *
     * @param object $item        Data object (typically a BerlinDB Row).
     * @param string $column_name Column identifier.
     *
     * @return string Rendered column HTML content.
     * @since 1.0.0
     *
     */
    public function column_default( $item, $column_name ) {
        // Check for column-specific callback in config
        if ( isset( $this->config['columns'][ $column_name ] ) ) {
            $column_config = $this->config['columns'][ $column_name ];

            if ( is_array( $column_config ) && isset( $column_config['callback'] ) && is_callable( $column_config['callback'] ) ) {
                return call_user_func( $column_config['callback'], $item );
            }
        }

        // Try getter method (e.g., get_email() for 'email' column)
        $getter = 'get_' . $column_name;
        if ( method_exists( $item, $getter ) ) {
            $value = $item->$getter();

            return $this->auto_format_column( $column_name, $value, $item );
        }

        // Try direct property access
        if ( property_exists( $item, $column_name ) ) {
            return $this->auto_format_column( $column_name, $item->$column_name, $item );
        }

        // No value found
        return Utils\Columns::render_empty();
    }

    /**
     * Auto-format column value based on naming patterns
     *
     * Delegates to Utils\Columns for intelligent formatting based on
     * column name patterns (dates, prices, statuses, etc.).
     *
     * @param string $column_name Column identifier.
     * @param mixed  $value       Raw column value.
     * @param object $item        Data object for context (e.g., getting currency).
     *
     * @return string Formatted HTML content.
     * @since 1.0.0
     *
     */
    private function auto_format_column( string $column_name, $value, $item ): string {
        return Utils\Columns::auto_format(
                $column_name,
                $value,
                $item,
                $this->config['status_styles'] ?? [],
                $this->config['views'] ?? []
        );
    }

    /**
     * Checkbox column renderer
     *
     * Renders the checkbox for bulk action selection.
     *
     * @param object $item Data object.
     *
     * @return string Checkbox input HTML.
     * @since 1.0.0
     *
     */
    public function column_cb( $item ): string {
        $id = $this->get_item_id( $item );

        return sprintf(
                '<input type="checkbox" name="%s[]" value="%s" />',
                esc_attr( $this->config['labels']['plural'] ?? 'items' ),
                esc_attr( $id )
        );
    }

    /* =========================================================================
     * ROW ACTIONS
     * ========================================================================= */

    /**
     * Handle row actions for primary column
     *
     * Builds and renders row actions (Edit, View, Delete, etc.) that appear
     * on hover below the primary column content.
     *
     * @param object $item        Data object.
     * @param string $column_name Current column being rendered.
     * @param string $primary     Primary column identifier.
     *
     * @return string Row actions HTML (empty for non-primary columns).
     * @since 1.0.0
     *
     */
    protected function handle_row_actions( $item, $column_name, $primary ) {
        // Only show actions on primary column
        if ( $column_name !== $primary ) {
            return '';
        }

        $item_id = $this->get_item_id( $item );
        $actions = [];

        // Build actions from config or callback
        if ( is_callable( $this->config['row_actions'] ) ) {
            $actions = call_user_func( $this->config['row_actions'], $item, $item_id );
        } else {
            $actions = $this->build_row_actions( $item, $item_id );
        }

        // Auto-add delete action if configured
        if ( $this->should_add_auto_delete_action( $actions ) ) {
            $actions['delete'] = $this->build_delete_action( $item_id );
        }

        /**
         * Filter row actions
         *
         * @param array  $actions Row action links.
         * @param object $item    Data object.
         * @param string $id      Table identifier.
         *
         * @since 1.0.0
         *
         */
        $actions = apply_filters( 'arraypress_table_row_actions', $actions, $item, $this->id );

        /**
         * Filter row actions for a specific table
         *
         * @param array  $actions Row action links.
         * @param object $item    Data object.
         *
         * @since 1.0.0
         *
         */
        $actions = apply_filters( "arraypress_table_row_actions_{$this->id}", $actions, $item );

        return $this->row_actions( $actions );
    }

    /**
     * Build row actions from configuration
     *
     * Processes the row_actions config array to generate action links.
     * Supports conditions, capabilities, and various action types.
     *
     * @param object $item    Data object.
     * @param int    $item_id Item ID.
     *
     * @return array Associative array of action key => HTML link.
     * @since 1.0.0
     *
     */
    private function build_row_actions( $item, int $item_id ): array {
        $actions = [];

        foreach ( $this->config['row_actions'] as $key => $action ) {
            if ( ! is_array( $action ) ) {
                continue;
            }

            // Check condition callback
            if ( isset( $action['condition'] ) && is_callable( $action['condition'] ) ) {
                if ( ! call_user_func( $action['condition'], $item ) ) {
                    continue;
                }
            }

            // Check capability
            if ( isset( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
                continue;
            }

            $action_html = $this->build_single_action( $action, $item, $item_id, $key );

            if ( ! empty( $action_html ) ) {
                $actions[ $key ] = $action_html;
            }
        }

        return $actions;
    }

    /**
     * Build a single row action link
     *
     * Generates HTML for a single row action based on its type:
     * - callback: Custom HTML from callable
     * - flyout: Opens a flyout panel (requires wp-flyout library)
     * - handler: Server-side handler with automatic nonce
     * - url: Simple link to URL
     *
     * @param array  $action  Action configuration array.
     * @param object $item    Data object.
     * @param int    $item_id Item ID.
     * @param string $key     Action identifier.
     *
     * @return string Action link HTML.
     * @since 1.0.0
     *
     */
    private function build_single_action( array $action, $item, int $item_id, string $key ): string {
        // Custom callback - full control over output
        if ( isset( $action['callback'] ) && is_callable( $action['callback'] ) ) {
            return call_user_func( $action['callback'], $item );
        }

        // Flyout action - opens edit/view flyout panel
        if ( isset( $action['flyout'] ) && $action['flyout'] === true && ! empty( $this->config['flyout'] ) ) {
            if ( function_exists( 'get_flyout_link' ) ) {
                return \get_flyout_link( $this->config['flyout'], [
                        'id'   => $item_id,
                        'text' => $action['label'] ?? ucfirst( $key ),
                ] );
            }

            return sprintf( '<a href="#">%s</a>', esc_html( $action['label'] ?? ucfirst( $key ) ) );
        }

        // Handler-based action - processed by Manager::process_single_action()
        if ( isset( $action['handler'] ) && is_callable( $action['handler'] ) ) {
            $singular     = $this->config['labels']['singular'] ?? 'item';
            $nonce_action = $action['nonce_action'] ?? "{$key}_{$singular}_{$item_id}";
            $nonce_action = str_replace( '{id}', (string) $item_id, $nonce_action );

            $url = wp_nonce_url(
                    add_query_arg(
                            [
                                    'action' => $key,
                                    'item'   => $item_id,
                            ],
                            $this->get_current_url()
                    ),
                    $nonce_action
            );

            $class = $action['class'] ?? '';
            $attrs = '';

            // Confirmation dialog
            if ( ! empty( $action['confirm'] ) ) {
                $confirm_msg = is_callable( $action['confirm'] )
                        ? call_user_func( $action['confirm'], $item )
                        : $action['confirm'];
                $attrs       .= sprintf( ' onclick="return confirm(\'%s\')"', esc_js( $confirm_msg ) );
            }

            // Dynamic label
            $label = $action['label'] ?? ucfirst( $key );
            if ( is_callable( $label ) ) {
                $label = call_user_func( $label, $item );
            }

            return sprintf(
                    '<a href="%s" class="%s"%s>%s</a>',
                    esc_url( $url ),
                    esc_attr( $class ),
                    $attrs,
                    esc_html( $label )
            );
        }

        // URL-based action - simple link
        if ( isset( $action['url'] ) ) {
            $url = is_callable( $action['url'] )
                    ? call_user_func( $action['url'], $item )
                    : $action['url'];

            $class = $action['class'] ?? '';
            $attrs = '';

            // Confirmation dialog
            if ( ! empty( $action['confirm'] ) ) {
                $confirm_msg = is_string( $action['confirm'] )
                        ? $action['confirm']
                        : __( 'Are you sure?', 'arraypress' );
                $attrs       .= sprintf( ' onclick="return confirm(\'%s\')"', esc_js( $confirm_msg ) );
            }

            return sprintf(
                    '<a href="%s" class="%s"%s>%s</a>',
                    esc_url( $url ),
                    esc_attr( $class ),
                    $attrs,
                    esc_html( $action['label'] ?? ucfirst( $key ) )
            );
        }

        return '';
    }

    /**
     * Check if auto delete action should be added
     *
     * Determines whether to automatically add a delete row action based on:
     * - auto_delete_action config is enabled
     * - No delete action already exists
     * - A delete callback is configured
     * - User has delete capability (if configured)
     *
     * @param array $actions Current row actions.
     *
     * @return bool True if auto delete should be added.
     * @since 1.0.0
     *
     */
    private function should_add_auto_delete_action( array $actions ): bool {
        if ( ! $this->config['auto_delete_action'] ) {
            return false;
        }

        if ( isset( $actions['delete'] ) ) {
            return false;
        }

        if ( ! isset( $this->config['callbacks']['delete'] ) || ! is_callable( $this->config['callbacks']['delete'] ) ) {
            return false;
        }

        if ( ! empty( $this->config['capabilities']['delete'] ) ) {
            if ( ! current_user_can( $this->config['capabilities']['delete'] ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build auto delete action HTML
     *
     * Generates a delete action link with nonce and confirmation dialog.
     *
     * @param int $item_id Item ID to delete.
     *
     * @return string Delete action link HTML.
     * @since 1.0.0
     *
     */
    private function build_delete_action( int $item_id ): string {
        $singular = $this->config['labels']['singular'] ?? 'item';

        $delete_url = wp_nonce_url(
                add_query_arg(
                        [
                                'action' => 'delete',
                                'item'   => $item_id,
                        ],
                        $this->get_current_url()
                ),
                "delete_{$singular}_{$item_id}"
        );

        $confirm_message = sprintf(
        /* translators: %s: singular item label */
                __( 'Are you sure you want to delete this %s?', 'arraypress' ),
                $singular
        );

        return sprintf(
                '<a href="%s" class="delete-link" onclick="return confirm(\'%s\')">%s</a>',
                esc_url( $delete_url ),
                esc_js( $confirm_message ),
                esc_html__( 'Delete', 'arraypress' )
        );
    }

    /* =========================================================================
     * BULK ACTIONS
     * ========================================================================= */

    /**
     * Get bulk actions
     *
     * Returns array of bulk action options for the dropdown.
     * Respects capability requirements.
     *
     * @return array Bulk action key => label pairs.
     * @since 1.0.0
     *
     */
    public function get_bulk_actions(): array {
        $actions = [];

        // Check bulk capability
        if ( ! empty( $this->config['capabilities']['bulk'] ) ) {
            if ( ! current_user_can( $this->config['capabilities']['bulk'] ) ) {
                return $actions;
            }
        }

        foreach ( $this->config['bulk_actions'] as $key => $action ) {
            if ( is_string( $action ) ) {
                $actions[ $key ] = $action;
            } elseif ( is_array( $action ) && isset( $action['label'] ) ) {
                // Check action-specific capability
                if ( isset( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
                    continue;
                }
                $actions[ $key ] = $action['label'];
            }
        }

        /**
         * Filter bulk actions
         *
         * @param array  $actions Bulk action options.
         * @param string $id      Table identifier.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_bulk_actions', $actions, $this->id );
    }

    /**
     * Process bulk actions
     *
     * Stub method - actual processing is handled by Manager::process_bulk_actions().
     *
     * @since 1.0.0
     */
    public function process_bulk_action(): void {
        // Bulk actions are processed in Manager::process_bulk_actions()
    }

    /* =========================================================================
     * VIEWS (STATUS TABS)
     * ========================================================================= */

    /**
     * Get views (status filter tabs)
     *
     * Builds the list of status links shown above the table.
     * Only shows statuses that have items.
     *
     * @return array View key => HTML link pairs.
     * @since 1.0.0
     *
     */
    public function get_views(): array {
        $views   = [];
        $current = $this->status;

        // Build base URL without status
        $base_url = $this->get_current_url();
        $base_url = remove_query_arg( [ 'status', 'paged' ], $base_url );

        // Ensure counts are loaded
        $this->get_counts();

        // All items view
        $views['all'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                esc_url( $base_url ),
                empty( $current ) ? 'current' : '',
                esc_html__( 'All', 'arraypress' ),
                esc_html( number_format_i18n( $this->counts['total'] ?? 0 ) )
        );

        // Status-specific views
        foreach ( $this->config['views'] as $key => $view ) {
            if ( $key === 'all' ) {
                continue;
            }

            // Skip if no items with this status
            if ( ! isset( $this->counts[ $key ] ) || $this->counts[ $key ] < 1 ) {
                continue;
            }

            $label = is_array( $view ) ? ( $view['label'] ?? $key ) : $view;
            $url   = add_query_arg( 'status', $key, $base_url );

            $views[ $key ] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                    esc_url( $url ),
                    $current === $key ? 'current' : '',
                    esc_html( $label ),
                    esc_html( number_format_i18n( $this->counts[ $key ] ) )
            );
        }

        /**
         * Filter the status views
         *
         * @param array  $views  View links.
         * @param string $id     Table identifier.
         * @param string $status Current status filter.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_views', $views, $this->id, $this->status );
    }

    /* =========================================================================
     * FILTERS (DROPDOWNS)
     * ========================================================================= */

    /**
     * Extra table navigation (filters)
     *
     * Renders dropdown filters above the table (top position only).
     *
     * @param string $which Position: 'top' or 'bottom'.
     *
     * @since 1.0.0
     *
     */
    protected function extra_tablenav( $which ): void {
        if ( $which !== 'top' ) {
            return;
        }

        if ( empty( $this->config['filters'] ) ) {
            return;
        }

        ?>
        <div class="alignleft actions">
            <?php
            // Render each filter dropdown
            foreach ( $this->config['filters'] as $key => $filter ) {
                $this->render_filter( $key, $filter );
            }

            // Filter submit button
            submit_button( __( 'Filter', 'arraypress' ), '', 'filter_action', false );

            // Clear filters link
            $has_filters = false;
            foreach ( $this->config['filters'] as $key => $filter ) {
                if ( ! empty( $_GET[ $key ] ) ) {
                    $has_filters = true;
                    break;
                }
            }

            if ( $has_filters ) {
                $clear_url = remove_query_arg( array_keys( $this->config['filters'] ), $this->get_current_url() );
                printf(
                        '<a href="%s" class="button">%s</a>',
                        esc_url( $clear_url ),
                        esc_html__( 'Clear', 'arraypress' )
                );
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render a single filter dropdown
     *
     * Generates a select element for filtering table data.
     * Supports static options array or dynamic options callback.
     *
     * @param string $key    Filter identifier (used as form field name).
     * @param mixed  $filter Filter configuration array or simple options array.
     *
     * @since 1.0.0
     *
     */
    private function render_filter( string $key, $filter ): void {
        $options = [];
        $label   = '';
        $current = sanitize_text_field( $_GET[ $key ] ?? '' );

        if ( is_array( $filter ) ) {
            $label = $filter['label'] ?? '';

            // Static options
            if ( isset( $filter['options'] ) && is_array( $filter['options'] ) ) {
                $options = $filter['options'];
            } // Dynamic options from callback
            elseif ( isset( $filter['options_callback'] ) && is_callable( $filter['options_callback'] ) ) {
                $options = call_user_func( $filter['options_callback'] );
            }
        }

        if ( empty( $options ) ) {
            return;
        }

        ?>
        <select name="<?php echo esc_attr( $key ); ?>" id="filter-by-<?php echo esc_attr( $key ); ?>">
            <?php if ( $label ) : ?>
                <option value=""><?php echo esc_html( $label ); ?></option>
            <?php endif; ?>

            <?php foreach ( $options as $value => $text ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, (string) $value ); ?>>
                    <?php echo esc_html( $text ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /* =========================================================================
     * PREPARATION & DISPLAY
     * ========================================================================= */

    /**
     * Prepare items for display
     *
     * Main method called before rendering. Sets up column headers,
     * fetches data, and configures pagination.
     *
     * @since 1.0.0
     */
    public function prepare_items(): void {
        // Set up column headers
        $this->_column_headers = [
                $this->get_columns(),
                $this->get_hidden_columns(),
                $this->get_sortable_columns(),
                $this->get_primary_column_name()
        ];

        // Fetch counts and items
        $this->get_counts();
        $this->items = $this->get_data();

        // Determine total for pagination
        $total = ! empty( $this->status ) && isset( $this->counts[ $this->status ] )
                ? $this->counts[ $this->status ]
                : ( $this->counts['total'] ?? 0 );

        // Set pagination
        $this->set_pagination_args( [
                'total_items' => $total,
                'per_page'    => $this->per_page,
                'total_pages' => ceil( $total / $this->per_page )
        ] );
    }

    /**
     * Display message when no items found
     *
     * Shows contextual message based on current filters/search.
     *
     * @since 1.0.0
     */
    public function no_items(): void {
        $search = $this->get_search();
        $plural = $this->config['labels']['plural'] ?? 'items';

        // Custom messages from config
        if ( ! empty( $search ) && ! empty( $this->config['labels']['not_found_search'] ) ) {
            $message = $this->config['labels']['not_found_search'];
        } elseif ( ! empty( $this->config['labels']['not_found'] ) ) {
            $message = $this->config['labels']['not_found'];
        } else {
            // Default contextual messages
            if ( ! empty( $search ) ) {
                $message = sprintf(
                /* translators: %s: plural item label */
                        __( 'No %s found for your search.', 'arraypress' ),
                        $plural
                );
            } elseif ( ! empty( $this->status ) ) {
                $status_label = Utils\StatusBadge::get_label( $this->status, $this->config['views'] ?? [] );
                $message      = sprintf(
                /* translators: 1: status label, 2: plural item label */
                        __( 'No %1$s %2$s found.', 'arraypress' ),
                        strtolower( $status_label ),
                        $plural
                );
            } else {
                $message = sprintf(
                /* translators: %s: plural item label */
                        __( 'No %s found.', 'arraypress' ),
                        $plural
                );
            }
        }

        echo esc_html( $message );
    }

    /* =========================================================================
     * HELPER METHODS
     * ========================================================================= */

    /**
     * Get items per page from screen options
     *
     * Retrieves the user's preferred items per page from their user meta,
     * falling back to the default from screen options or config.
     *
     * @param string $option  Option name (unused, kept for compatibility).
     * @param int    $default Default value from config.
     *
     * @return int Items per page.
     * @since 1.0.0
     *
     */
    protected function get_items_per_page( $option, $default = 30 ): int {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return $default;
        }

        $option_name = $screen->get_option( 'per_page', 'option' );

        if ( empty( $option_name ) ) {
            return $default;
        }

        $user     = get_current_user_id();
        $per_page = get_user_meta( $user, $option_name, true );

        if ( empty( $per_page ) || $per_page < 1 ) {
            $per_page = $screen->get_option( 'per_page', 'default' ) ?: $default;
        }

        return absint( $per_page );
    }

    /**
     * Get item ID from item object
     *
     * Extracts the ID from a data object, checking for get_id() method
     * first, then falling back to id property.
     *
     * @param object $item Data object.
     *
     * @return int Item ID.
     * @since 1.0.0
     *
     */
    private function get_item_id( $item ): int {
        if ( method_exists( $item, 'get_id' ) ) {
            return (int) $item->get_id();
        }

        return (int) ( $item->id ?? 0 );
    }

    /**
     * Parse pagination arguments
     *
     * Extracts and sanitizes pagination/sorting parameters from the
     * request for use in database queries.
     *
     * @return array Query arguments with number, offset, order, orderby keys.
     * @since 1.0.0
     *
     */
    private function parse_pagination_args(): array {
        $paged  = absint( $_REQUEST['paged'] ?? 1 );
        $offset = $paged > 1 ? $this->per_page * ( $paged - 1 ) : 0;

        $orderby = sanitize_key( $_REQUEST['orderby'] ?? '' );
        $order   = strtoupper( sanitize_key( $_REQUEST['order'] ?? '' ) );

        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        if ( empty( $orderby ) ) {
            $orderby = 'id';
        }

        return [
                'number'  => $this->per_page,
                'offset'  => $offset,
                'order'   => $order,
                'orderby' => $orderby
        ];
    }

    /**
     * Get search query
     *
     * Retrieves and sanitizes the search term from the request.
     *
     * @return string Sanitized search query.
     * @since 1.0.0
     *
     */
    private function get_search(): string {
        return sanitize_text_field( $_REQUEST['s'] ?? '' );
    }

    /**
     * Get current page URL
     *
     * Builds a clean URL for the current page with current filters,
     * status, and search preserved.
     *
     * @return string Current admin page URL with query args.
     * @since 1.0.0
     *
     */
    private function get_current_url(): string {
        $url = add_query_arg( 'page', $this->config['page'], admin_url( 'admin.php' ) );

        // Preserve status
        if ( ! empty( $_GET['status'] ) ) {
            $url = add_query_arg( 'status', sanitize_key( $_GET['status'] ), $url );
        }

        // Preserve search
        if ( ! empty( $_GET['s'] ) ) {
            $url = add_query_arg( 's', sanitize_text_field( $_GET['s'] ), $url );
        }

        // Preserve filters
        if ( ! empty( $this->config['filters'] ) ) {
            foreach ( $this->config['filters'] as $filter_key => $filter ) {
                if ( ! empty( $_GET[ $filter_key ] ) ) {
                    $url = add_query_arg( $filter_key, sanitize_text_field( $_GET[ $filter_key ] ), $url );
                }
            }
        }

        return $url;
    }

}