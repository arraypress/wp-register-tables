<?php
/**
 * Table Class
 *
 * Generates a WP_List_Table instance from configuration.
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
 * @since 1.0.0
 */
class Table extends WP_List_Table {

    /**
     * Table identifier
     *
     * @since 1.0.0
     * @var string
     */
    private string $id;

    /**
     * Table configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $config;

    /**
     * Current status filter
     *
     * @since 1.0.0
     * @var string
     */
    private string $status = '';

    /**
     * Status counts
     *
     * @since 1.0.0
     * @var array
     */
    private array $counts = [];

    /**
     * Items per page
     *
     * @since 1.0.0
     * @var int
     */
    private int $per_page;

    /**
     * Constructor
     *
     * @param string $id     Table identifier
     * @param array  $config Table configuration
     *
     * @since 1.0.0
     *
     */
    public function __construct( string $id, array $config ) {
        $this->id     = $id;
        $this->config = $config;

        // Get current status
        $this->status = sanitize_key( $_GET['status'] ?? '' );

        // Get per page from screen options or use default
        $this->per_page = $this->get_items_per_page( 'per_page', $config['per_page'] );

        // Set up parent
        parent::__construct( [
                'singular' => $config['labels']['singular'] ?? 'item',
                'plural'   => $config['labels']['plural'] ?? 'items',
                'ajax'     => false
        ] );
    }

    /**
     * Get items per page
     *
     * Gets the user's saved per page preference or uses default.
     *
     * @param string $option  Option name
     * @param int    $default Default value
     *
     * @return int Items per page
     * @since 1.0.0
     *
     */
    protected function get_items_per_page( $option, $default = 30 ): int {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return $default;
        }

        // Check if user has set a custom value
        $user     = get_current_user_id();
        $per_page = get_user_meta( $user, $screen->get_option( 'per_page', 'option' ), true );

        if ( empty( $per_page ) || $per_page < 1 ) {
            $per_page = $screen->get_option( 'per_page', 'default' ) ?: $default;
        }

        return absint( $per_page );
    }

    /**
     * Get columns
     *
     * @return array Column definitions
     * @since 1.0.0
     *
     */
    public function get_columns(): array {
        $columns = [];

        // Add checkbox column if bulk actions exist
        if ( ! empty( $this->config['bulk_actions'] ) ) {
            $columns['cb'] = '<input type="checkbox" />';
        }

        // Add configured columns
        foreach ( $this->config['columns'] as $key => $column ) {
            if ( is_string( $column ) ) {
                $columns[ $key ] = $column;
            } elseif ( is_array( $column ) && isset( $column['label'] ) ) {
                $columns[ $key ] = $column['label'];
            }
        }

        /**
         * Filters the table columns.
         *
         * @param array  $columns Column definitions with keys as column IDs and values as labels
         * @param string $id      Table identifier used during registration
         * @param array  $config  Full table configuration array
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_columns', $columns, $this->id, $this->config );

        /**
         * Filters the columns for a specific table.
         *
         * The dynamic portion of the hook name, `$id`, refers to the table identifier.
         *
         * @param array $columns Column definitions with keys as column IDs and values as labels
         * @param array $config  Full table configuration array
         *
         * @since 1.0.0
         *
         */
        return apply_filters( "arraypress_table_columns_{$this->id}", $columns, $this->config );
    }

    /**
     * Get hidden columns
     *
     * @return array Hidden column names
     * @since 1.0.0
     */
    public function get_hidden_columns(): array {
        $hidden = $this->config['hidden_columns'] ?? [];

        /**
         * Filters the hidden columns.
         *
         * @param array  $hidden Array of column IDs to hide
         * @param string $id     Table identifier
         * @param array  $config Full table configuration
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_hidden_columns', $hidden, $this->id, $this->config );
    }

    /**
     * Get sortable columns
     *
     * @return array Sortable column definitions
     * @since 1.0.0
     *
     */
    public function get_sortable_columns(): array {
        $sortable = [];

        foreach ( $this->config['sortable'] as $key => $sort ) {
            if ( is_numeric( $key ) ) {
                // Simple format: ['column_name']
                $sortable[ $sort ] = [ $sort, false ];
            } else {
                // Full format: ['column_name' => ['orderby_field', true]]
                $sortable[ $key ] = $sort;
            }
        }

        /**
         * Filters the sortable columns.
         *
         * @param array  $sortable Sortable column definitions as [column_id => [orderby, desc]]
         * @param string $id       Table identifier
         * @param array  $config   Full table configuration
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_sortable_columns', $sortable, $this->id, $this->config );
    }

    /**
     * Get primary column name
     *
     * @return string Primary column name
     * @since 1.0.0
     *
     */
    protected function get_primary_column_name(): string {
        return $this->config['primary_column'] ?? parent::get_primary_column_name();
    }

    /**
     * Get the table data
     *
     * @return array Array of items
     * @since 1.0.0
     *
     */
    public function get_data(): array {
        // Build query arguments
        $args = $this->parse_pagination_args();

        // Add search
        $search = $this->get_search();
        if ( ! empty( $search ) ) {
            $args['search'] = $search;
        }

        // Add status filter
        if ( ! empty( $this->status ) ) {
            $args['status'] = $this->status;
        }

        // Add custom filters
        foreach ( $this->config['filters'] as $filter_key => $filter ) {
            if ( isset( $_GET[ $filter_key ] ) ) {
                $value = sanitize_text_field( $_GET[ $filter_key ] );
                if ( ! empty( $value ) ) {
                    if ( is_array( $filter ) && isset( $filter['apply_callback'] ) ) {
                        call_user_func_array( $filter['apply_callback'], [ &$args, $value ] );
                    } else {
                        $args[ $filter_key ] = $value;
                    }
                }
            }
        }

        /**
         * Filters the query arguments before fetching data.
         *
         * @param array  $args   Query arguments including pagination, search, and filters
         * @param string $id     Table identifier
         * @param array  $config Full table configuration
         *
         * @since 1.0.0
         *
         */
        $args = apply_filters( 'arraypress_table_query_args', $args, $this->id, $this->config );

        /**
         * Filters the query arguments for a specific table.
         *
         * The dynamic portion of the hook name, `$id`, refers to the table identifier.
         *
         * @param array $args   Query arguments including pagination, search, and filters
         * @param array $config Full table configuration
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
     * Get counts for different statuses
     *
     * @return array Status counts
     * @since 1.0.0
     *
     */
    public function get_counts(): array {
        if ( ! empty( $this->counts ) ) {
            return $this->counts;
        }

        if ( isset( $this->config['callbacks']['get_counts'] ) && is_callable( $this->config['callbacks']['get_counts'] ) ) {
            $this->counts = call_user_func( $this->config['callbacks']['get_counts'] );
        }

        // Default to just total
        if ( empty( $this->counts ) ) {
            $this->counts = [ 'total' => 0 ];
        }

        return $this->counts;
    }

    /**
     * Default column renderer
     *
     * @param object $item        Data object
     * @param string $column_name Column name
     *
     * @return string Column content
     * @since 1.0.0
     *
     */
    public function column_default( $item, $column_name ) {
        // Check for column callback
        if ( isset( $this->config['columns'][ $column_name ] ) ) {
            $column_config = $this->config['columns'][ $column_name ];

            if ( is_array( $column_config ) && isset( $column_config['callback'] ) ) {
                // Callbacks can return HTML, so don't escape their output
                return call_user_func( $column_config['callback'], $item );
            }
        }

        // Try getter method
        $getter = 'get_' . $column_name;
        if ( method_exists( $item, $getter ) ) {
            $value = $item->$getter();

            // Auto-format based on column name patterns
            return $this->auto_format_column( $column_name, $value, $item );
        }

        // Try property
        if ( property_exists( $item, $column_name ) ) {
            return $this->auto_format_column( $column_name, $item->$column_name, $item );
        }

        return '<span aria-hidden="true">—</span><span class="screen-reader-text">Unknown</span>';
    }

    /**
     * Auto-format column value based on naming patterns
     *
     * @param string $column_name Column name
     * @param mixed  $value       Column value
     * @param object $item        Data object
     *
     * @return string Formatted value
     * @since 1.0.0
     *
     */
    private function auto_format_column( string $column_name, $value, $item ): string {
        // Handle empty values with proper WordPress markup
        if ( empty( $value ) && $value !== 0 && $value !== '0' ) {
            return '<span aria-hidden="true">—</span><span class="screen-reader-text">Unknown</span>';
        }

        // Email columns
        if ( str_contains( $column_name, 'email' ) ) {
            return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $value ) );
        }

        // Date columns
        if ( str_contains( $column_name, '_at' ) ||
             str_contains( $column_name, 'date' ) ||
             in_array( $column_name, [ 'created', 'updated', 'modified', 'registered', 'last_sync' ], true ) ) {
            $time  = strtotime( $value );
            $human = human_time_diff( $time, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'arraypress' );

            return sprintf(
                    '<span title="%s">%s</span>',
                    esc_attr( $value ),
                    esc_html( $human )
            );
        }

        // Price/money columns with Stripe-style formatting
        if ( str_contains( $column_name, 'price' ) ||
             str_contains( $column_name, 'total' ) ||
             str_contains( $column_name, 'amount' ) ||
             str_contains( $column_name, '_spent' ) ) {

            // Get currency from item methods or properties
            $currency = 'USD';
            if ( method_exists( $item, 'get_currency' ) ) {
                $currency = $item->get_currency();
            } elseif ( property_exists( $item, 'currency' ) ) {
                $currency = $item->currency;
            }

            // Amount is stored in cents (Stripe format)
            $amount = is_numeric( $value ) ? intval( $value ) : 0;

            // Check for recurring interval
            $interval       = null;
            $interval_count = 1;

            if ( method_exists( $item, 'get_recurring_interval' ) ) {
                $interval = $item->get_recurring_interval();
                if ( method_exists( $item, 'get_recurring_interval_count' ) ) {
                    $interval_count = $item->get_recurring_interval_count();
                }
            } elseif ( property_exists( $item, 'recurring_interval' ) ) {
                $interval = $item->recurring_interval;
                if ( property_exists( $item, 'recurring_interval_count' ) ) {
                    $interval_count = $item->recurring_interval_count;
                }
            }

            // Use the Currency library to format (assuming global function exists)
            if ( function_exists( 'format_price_interval' ) ) {
                $formatted = format_price_interval( $amount, $currency, $interval, $interval_count );
            } else {
                // Fallback to basic formatting
                $formatted = format_currency( $amount, $currency );
            }

            return sprintf( '<span class="price">%s</span>', esc_html( $formatted ) );
        }

        // Status columns
        if ( $column_name === 'status' || str_contains( $column_name, '_status' ) ) {
            $class = $this->get_status_class( $value );
            $label = $this->get_status_label( $value );

            return sprintf(
                    '<span class="badge badge-%s">%s</span>',
                    esc_attr( $class ),
                    esc_html( $label )
            );
        }

        // Count columns
        if ( str_contains( $column_name, '_count' ) ||
             str_contains( $column_name, 'count' ) ||
             str_contains( $column_name, 'limit' ) ) {

            // Handle unlimited (-1 or 0 can mean unlimited depending on context)
            if ( $value == - 1 ) {
                return '<span class="unlimited">∞</span>';
            }

            return $value > 0
                    ? number_format_i18n( $value )
                    : '<span class="text-muted">0</span>';
        }

        // URL columns (including image_url)
        if ( str_contains( $column_name, '_url' ) || str_contains( $column_name, 'url' ) ) {
            if ( str_contains( $column_name, 'image' ) ) {
                // Image URL - show as thumbnail
                return sprintf(
                        '<a href="%1$s" target="_blank"><img src="%1$s" style="max-width: 50px; height: auto;" alt="" /></a>',
                        esc_url( $value )
                );
            } else {
                // Regular URL
                $display_url = parse_url( $value, PHP_URL_HOST ) ?: $value;

                return sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        esc_url( $value ),
                        esc_html( $display_url )
                );
            }
        }

        // Boolean/test mode columns
        if ( str_starts_with( $column_name, 'is_' ) || in_array( $column_name, [
                        'test',
                        'active',
                        'enabled'
                ], true ) ) {
            $is_true = filter_var( $value, FILTER_VALIDATE_BOOLEAN );

            if ( $column_name === 'is_test' ) {
                return $is_true
                        ? '<span class="badge badge-warning">Test</span>'
                        : '<span class="badge badge-success">Live</span>';
            }

            return $is_true
                    ? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>'
                    : '<span class="dashicons dashicons-minus" style="color: #d63638;"></span>';
        }

        // Default
        return esc_html( $value );
    }

    /**
     * Get status badge class
     *
     * @param string $status Status value
     *
     * @return string Badge class
     * @since 1.0.0
     *
     */
    private function get_status_class( string $status ): string {
        // Check config first for custom status styles
        if ( isset( $this->config['status_styles'][ $status ] ) ) {
            return $this->config['status_styles'][ $status ];
        }

        // Default mappings for common status values
        $status_map = [
            // Success states (green)
                'active'             => 'success',
                'completed'          => 'success',
                'paid'               => 'success',
                'published'          => 'success',
                'approved'           => 'success',
                'confirmed'          => 'success',
                'delivered'          => 'success',

            // Warning states (yellow/orange)
                'pending'            => 'warning',
                'processing'         => 'warning',
                'draft'              => 'warning',
                'on-hold'            => 'warning',
                'on_hold'            => 'warning',
                'partially_refunded' => 'warning',
                'unpaid'             => 'warning',
                'expired'            => 'warning',
                'expiring'           => 'warning',
                'scheduled'          => 'warning',

            // Error states (red)
                'failed'             => 'error',
                'cancelled'          => 'error',
                'canceled'           => 'error',
                'refunded'           => 'error',
                'rejected'           => 'error',
                'declined'           => 'error',
                'blocked'            => 'error',
                'revoked'            => 'error',
                'suspended'          => 'error',
                'terminated'         => 'error',

            // Info states (blue)
                'new'                => 'info',
                'updated'            => 'info',

            // Default/neutral states (gray)
                'inactive'           => 'default',
                'disabled'           => 'default',
                'paused'             => 'default',
                'archived'           => 'default',
                'hidden'             => 'default',
                'trashed'            => 'default'
        ];

        return $status_map[ $status ] ?? 'default';
    }

    /**
     * Get status label
     *
     * @param string $status Status value
     *
     * @return string Status label
     * @since 1.0.0
     *
     */
    private function get_status_label( string $status ): string {
        // Check views for label
        if ( isset( $this->config['views'][ $status ] ) ) {
            $view = $this->config['views'][ $status ];
            if ( is_string( $view ) ) {
                return $view;
            } elseif ( is_array( $view ) && isset( $view['label'] ) ) {
                return $view['label'];
            }
        }

        // Format the status string
        return ucwords( str_replace( [ '-', '_' ], ' ', $status ) );
    }

    /**
     * Checkbox column
     *
     * @param object $item Data object
     *
     * @return string Checkbox HTML
     * @since 1.0.0
     *
     */
    public function column_cb( $item ): string {
        $id = method_exists( $item, 'get_id' ) ? $item->get_id() : ( $item->id ?? 0 );

        return sprintf(
                '<input type="checkbox" name="%s[]" value="%s" />',
                esc_attr( $this->config['labels']['plural'] ?? 'items' ),
                esc_attr( $id )
        );
    }

    /**
     * Handle row actions for primary column
     *
     * @param object $item        Data object
     * @param string $column_name Column name
     * @param string $primary     Primary column name
     *
     * @return string Column content with row actions
     * @since 1.0.0
     *
     */
    protected function handle_row_actions( $item, $column_name, $primary ) {
        if ( $column_name !== $primary ) {
            return '';
        }

        $actions = [];
        $item_id = method_exists( $item, 'get_id' ) ? $item->get_id() : ( $item->id ?? 0 );

        foreach ( $this->config['row_actions'] as $key => $action ) {
            // Skip if not array
            if ( ! is_array( $action ) ) {
                continue;
            }

            // Check condition
            if ( isset( $action['condition'] ) && is_callable( $action['condition'] ) ) {
                if ( ! call_user_func( $action['condition'], $item ) ) {
                    continue;
                }
            }

            // Check capability
            if ( isset( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
                continue;
            }

            // Build action link
            if ( isset( $action['callback'] ) && is_callable( $action['callback'] ) ) {
                $result = call_user_func( $action['callback'], $item );
                if ( ! empty( $result ) ) {
                    $actions[ $key ] = $result;
                }
            } elseif ( isset( $action['flyout'] ) && $action['flyout'] === true ) {
                // Use table's flyout
                $actions[ $key ] = sprintf(
                        '<a href="#" data-flyout-trigger="%s" data-flyout-action="load" data-id="%s">%s</a>',
                        esc_attr( $this->config['flyout'] ),
                        esc_attr( $item_id ),
                        esc_html( $action['label'] )
                );
            } elseif ( isset( $action['url'] ) ) {
                $url = is_callable( $action['url'] )
                        ? call_user_func( $action['url'], $item )
                        : $action['url'];

                $class = $action['class'] ?? '';
                $attrs = '';

                // Add confirmation if needed
                if ( ! empty( $action['confirm'] ) ) {
                    $confirm_msg = is_string( $action['confirm'] )
                            ? $action['confirm']
                            : __( 'Are you sure?', 'arraypress' );
                    $attrs       .= sprintf( ' onclick="return confirm(\'%s\')"', esc_js( $confirm_msg ) );
                }

                $actions[ $key ] = sprintf(
                        '<a href="%s" class="%s"%s>%s</a>',
                        esc_url( $url ),
                        esc_attr( $class ),
                        $attrs,
                        esc_html( $action['label'] )
                );
            }
        }

        /**
         * Filters the row actions for a table row.
         *
         * @param array  $actions Row actions as [action_key => html_link]
         * @param object $item    The current row's data object
         * @param string $id      Table identifier
         *
         * @since 1.0.0
         *
         */
        $actions = apply_filters( 'arraypress_table_row_actions', $actions, $item, $this->id );

        /**
         * Filters the row actions for a specific table.
         *
         * The dynamic portion of the hook name, `$id`, refers to the table identifier.
         *
         * @param array  $actions Row actions as [action_key => html_link]
         * @param object $item    The current row's data object
         *
         * @since 1.0.0
         *
         */
        $actions = apply_filters( "arraypress_table_row_actions_{$this->id}", $actions, $item );

        return $this->row_actions( $actions );
    }

    /**
     * Get bulk actions
     *
     * @return array Bulk action options
     * @since 1.0.0
     *
     */
    public function get_bulk_actions(): array {
        $actions = [];

        foreach ( $this->config['bulk_actions'] as $key => $action ) {
            if ( is_string( $action ) ) {
                $actions[ $key ] = $action;
            } elseif ( is_array( $action ) && isset( $action['label'] ) ) {
                // Check capability
                if ( isset( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
                    continue;
                }
                $actions[ $key ] = $action['label'];
            }
        }

        /**
         * Filters the bulk actions dropdown.
         *
         * @param array  $actions Bulk actions as [action_key => label]
         * @param string $id      Table identifier
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_bulk_actions', $actions, $this->id );
    }

    /**
     * Process bulk actions
     *
     * @return void
     * @since 1.0.0
     *
     */
    public function process_bulk_action(): void {
        $action = $this->current_action();
        if ( empty( $action ) ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_REQUEST['_wpnonce'] ) ||
             ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) ) {
            return;
        }

        // Get selected items
        $items = $_REQUEST[ $this->config['labels']['plural'] ?? 'items' ] ?? [];
        if ( empty( $items ) ) {
            return;
        }

        $items = array_map( 'absint', $items );

        // Get bulk action config
        $action_config = $this->config['bulk_actions'][ $action ] ?? null;
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
         *
         */
        do_action( 'arraypress_table_bulk_action', $items, $action, $this->id );

        /**
         * Fires when a bulk action is processed for a specific table.
         *
         * The dynamic portion of the hook name, `$id`, refers to the table identifier.
         *
         * @param array  $items  Selected item IDs
         * @param string $action Action key being performed
         *
         * @since 1.0.0
         *
         */
        do_action( "arraypress_table_bulk_action_{$this->id}", $items, $action );

        /**
         * Fires when a specific bulk action is processed for a specific table.
         *
         * The dynamic portions of the hook name, `$id` and `$action`, refer to the
         * table identifier and action key respectively.
         *
         * @param array $items Selected item IDs
         *
         * @since 1.0.0
         *
         */
        do_action( "arraypress_table_bulk_action_{$this->id}_{$action}", $items );

        // Process action
        $redirect_args = [];

        if ( isset( $action_config['callback'] ) && is_callable( $action_config['callback'] ) ) {
            $result = call_user_func( $action_config['callback'], $items );

            if ( is_array( $result ) ) {
                $redirect_args = $result;
            }
        }

        // Redirect with notice parameters
        if ( ! empty( $redirect_args ) ) {
            $redirect_url = add_query_arg( $redirect_args, $this->get_current_url() );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    /**
     * Get views
     *
     * @return array View links
     * @since 1.0.0
     *
     */
    public function get_views(): array {
        $views    = [];
        $current  = $this->status;
        $base_url = remove_query_arg( [
                'status',
                'paged',
                's',
                '_wpnonce',
                '_wp_http_referer'
        ], $this->get_current_url() );

        // Get counts
        $this->get_counts();

        // Add "All" view
        $views['all'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                esc_url( $base_url ),
                empty( $current ) ? 'current' : '',
                __( 'All', 'arraypress' ),
                number_format_i18n( $this->counts['total'] ?? 0 )
        );

        // Add configured views
        foreach ( $this->config['views'] as $key => $view ) {
            if ( $key === 'all' ) {
                continue; // Already added
            }

            if ( ! isset( $this->counts[ $key ] ) || $this->counts[ $key ] < 1 ) {
                continue;
            }

            $label = is_array( $view ) ? $view['label'] : $view;
            $url   = add_query_arg( 'status', $key, $base_url );

            $views[ $key ] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                    esc_url( $url ),
                    $current === $key ? 'current' : '',
                    esc_html( $label ),
                    number_format_i18n( $this->counts[ $key ] )
            );
        }

        /**
         * Filters the available views (status filters) for the table.
         *
         * @param array  $views  View links as [view_key => html_link]
         * @param string $id     Table identifier
         * @param string $status Current active status filter
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_views', $views, $this->id, $this->status );
    }

    /**
     * Extra table navigation
     *
     * @param string $which Top or bottom
     *
     * @return void
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
            foreach ( $this->config['filters'] as $key => $filter ) {
                $this->render_filter( $key, $filter );
            }

            submit_button( __( 'Filter', 'arraypress' ), '', 'filter_action', false );

            // Clear filters button if any are active
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
                        __( 'Clear', 'arraypress' )
                );
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render a filter dropdown
     *
     * @param string $key    Filter key
     * @param mixed  $filter Filter configuration
     *
     * @return void
     * @since 1.0.0
     *
     */
    private function render_filter( string $key, $filter ): void {
        $options = [];
        $label   = '';
        $current = $_GET[ $key ] ?? '';

        if ( is_array( $filter ) ) {
            $label = $filter['label'] ?? '';

            if ( isset( $filter['options'] ) ) {
                $options = $filter['options'];
            } elseif ( isset( $filter['options_callback'] ) && is_callable( $filter['options_callback'] ) ) {
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
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
                    <?php echo esc_html( $text ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Prepare items
     *
     * @return void
     * @since 1.0.0
     *
     */
    public function prepare_items(): void {
        $this->_column_headers = [
                $this->get_columns(),
                $this->get_hidden_columns(),
                $this->get_sortable_columns(),
                $this->get_primary_column_name()
        ];

        $this->get_counts();
        $this->items = $this->get_data();

        $total = ! empty( $this->status ) && isset( $this->counts[ $this->status ] )
                ? $this->counts[ $this->status ]
                : $this->counts['total'] ?? 0;

        $this->set_pagination_args( [
                'total_items' => $total,
                'per_page'    => $this->per_page,
                'total_pages' => ceil( $total / $this->per_page )
        ] );
    }

    /**
     * Message when no items found
     *
     * @return void
     * @since 1.0.0
     *
     */
    public function no_items(): void {
        $search = $this->get_search();

        if ( ! empty( $search ) && ! empty( $this->config['labels']['not_found_search'] ) ) {
            $message = $this->config['labels']['not_found_search'];
        } elseif ( ! empty( $this->config['labels']['not_found'] ) ) {
            $message = $this->config['labels']['not_found'];
        } else {
            // Default messages
            if ( ! empty( $search ) ) {
                $message = sprintf(
                        __( 'No %s found for your search.', 'arraypress' ),
                        $this->config['labels']['plural'] ?: 'items'
                );
            } elseif ( ! empty( $this->status ) ) {
                $message = sprintf(
                        __( 'No %s %s found.', 'arraypress' ),
                        $this->get_status_label( $this->status ),
                        $this->config['labels']['plural'] ?: 'items'
                );
            } else {
                $message = sprintf(
                        __( 'No %s found.', 'arraypress' ),
                        $this->config['labels']['plural'] ?: 'items'
                );
            }
        }

        echo esc_html( $message );
    }

    /**
     * Parse pagination arguments
     *
     * @return array Query arguments
     * @since 1.0.0
     *
     */
    private function parse_pagination_args(): array {
        $paged  = absint( $_REQUEST['paged'] ?? 1 );
        $offset = $paged > 1 ? $this->per_page * ( $paged - 1 ) : 0;

        $orderby = sanitize_key( $_REQUEST['orderby'] ?? '' );
        $order   = strtoupper( sanitize_key( $_REQUEST['order'] ?? '' ) );

        // Validate order
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        // Default orderby if not set
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
     * @return string Search query
     * @since 1.0.0
     *
     */
    private function get_search(): string {
        return sanitize_text_field( $_REQUEST['s'] ?? '' );
    }

    /**
     * Get current page URL
     *
     * @return string Current URL
     * @since 1.0.0
     *
     */
    private function get_current_url(): string {
        $page = $_GET['page'] ?? $this->config['page'];

        return add_query_arg( 'page', $page, admin_url( 'admin.php' ) );
    }

}