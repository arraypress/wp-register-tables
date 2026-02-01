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
     * @var string
     */
    private string $id;

    /**
     * Table configuration
     *
     * @var array
     */
    private array $config;

    /**
     * Current status filter
     *
     * @var string
     */
    private string $status = '';

    /**
     * Status counts
     *
     * @var array
     */
    private array $counts = [];

    /**
     * Items per page
     *
     * @var int
     */
    private int $per_page;

    /**
     * Constructor
     *
     * @param string $id     Table identifier
     * @param array  $config Table configuration
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
     * @param string $option  Option name
     * @param int    $default Default value
     *
     * @return int Items per page
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
     * Get columns
     *
     * @return array Column definitions
     */
    public function get_columns(): array {
        $columns = [];

        if ( ! empty( $this->config['bulk_actions'] ) ) {
            $columns['cb'] = '<input type="checkbox" />';
        }

        foreach ( $this->config['columns'] as $key => $column ) {
            if ( is_string( $column ) ) {
                $columns[ $key ] = $column;
            } elseif ( is_array( $column ) && isset( $column['label'] ) ) {
                $columns[ $key ] = $column['label'];
            }
        }

        return apply_filters( 'arraypress_table_columns', $columns, $this->id, $this->config );
    }

    /**
     * Get hidden columns
     *
     * @return array Hidden column names
     */
    public function get_hidden_columns(): array {
        $hidden = $this->config['hidden_columns'] ?? [];

        return apply_filters( 'arraypress_table_hidden_columns', $hidden, $this->id, $this->config );
    }

    /**
     * Get sortable columns
     *
     * @return array Sortable column definitions
     */
    public function get_sortable_columns(): array {
        $sortable = [];

        foreach ( $this->config['sortable'] as $key => $sort ) {
            if ( is_numeric( $key ) ) {
                $sortable[ $sort ] = [ $sort, false ];
            } else {
                $sortable[ $key ] = $sort;
            }
        }

        return apply_filters( 'arraypress_table_sortable_columns', $sortable, $this->id, $this->config );
    }

    /**
     * Get primary column name
     *
     * @return string Primary column name
     */
    protected function get_primary_column_name(): string {
        return $this->config['primary_column'] ?? parent::get_primary_column_name();
    }

    /**
     * Get the table data
     *
     * @return array Array of items
     */
    public function get_data(): array {
        $args = [];

        if ( ! empty( $this->config['base_query_args'] ) ) {
            $args = $this->config['base_query_args'];
        }

        $args = array_merge( $args, $this->parse_pagination_args() );

        $search = $this->get_search();
        if ( ! empty( $search ) ) {
            $args['search'] = $search;
        }

        if ( ! empty( $this->status ) ) {
            $args['status'] = $this->status;
        }

        foreach ( $this->config['filters'] as $filter_key => $filter ) {
            if ( ! isset( $_GET[ $filter_key ] ) ) {
                continue;
            }

            $value = sanitize_text_field( $_GET[ $filter_key ] );

            if ( empty( $value ) ) {
                continue;
            }

            if ( is_array( $filter ) && isset( $filter['apply_callback'] ) && is_callable( $filter['apply_callback'] ) ) {
                call_user_func_array( $filter['apply_callback'], [ &$args, $value ] );
            } else {
                $args[ $filter_key ] = $value;
            }
        }

        $args = apply_filters( 'arraypress_table_query_args', $args, $this->id, $this->config );
        $args = apply_filters( "arraypress_table_query_args_{$this->id}", $args, $this->config );

        if ( isset( $this->config['callbacks']['get_items'] ) && is_callable( $this->config['callbacks']['get_items'] ) ) {
            return call_user_func( $this->config['callbacks']['get_items'], $args );
        }

        return [];
    }

    /**
     * Get counts for different statuses
     *
     * @return array Status counts
     */
    public function get_counts(): array {
        if ( ! empty( $this->counts ) ) {
            return $this->counts;
        }

        if ( isset( $this->config['callbacks']['get_counts'] ) && is_callable( $this->config['callbacks']['get_counts'] ) ) {
            $this->counts = call_user_func( $this->config['callbacks']['get_counts'] );
        }

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
     */
    public function column_default( $item, $column_name ) {
        if ( isset( $this->config['columns'][ $column_name ] ) ) {
            $column_config = $this->config['columns'][ $column_name ];

            if ( is_array( $column_config ) && isset( $column_config['callback'] ) && is_callable( $column_config['callback'] ) ) {
                return call_user_func( $column_config['callback'], $item );
            }
        }

        $getter = 'get_' . $column_name;
        if ( method_exists( $item, $getter ) ) {
            $value = $item->$getter();

            return $this->auto_format_column( $column_name, $value, $item );
        }

        if ( property_exists( $item, $column_name ) ) {
            return $this->auto_format_column( $column_name, $item->$column_name, $item );
        }

        return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Unknown', 'arraypress' ) . '</span>';
    }

    /**
     * Auto-format column value based on naming patterns
     *
     * @param string $column_name Column name
     * @param mixed  $value       Column value
     * @param object $item        Data object
     *
     * @return string Formatted value
     */
    private function auto_format_column( string $column_name, $value, $item ): string {
        if ( empty( $value ) && $value !== 0 && $value !== '0' ) {
            return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Unknown', 'arraypress' ) . '</span>';
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

        // Price/money columns
        if ( str_contains( $column_name, 'price' ) ||
             str_contains( $column_name, 'total' ) ||
             str_contains( $column_name, 'amount' ) ||
             str_contains( $column_name, '_spent' ) ) {

            $currency = 'USD';
            if ( method_exists( $item, 'get_currency' ) ) {
                $currency = $item->get_currency();
            } elseif ( property_exists( $item, 'currency' ) ) {
                $currency = $item->currency;
            }

            $amount = is_numeric( $value ) ? intval( $value ) : 0;

            if ( function_exists( 'format_currency' ) ) {
                $formatted = format_currency( $amount, $currency );
            } else {
                $formatted = '$' . number_format( $amount / 100, 2 );
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

            if ( $value == -1 ) {
                return '<span class="unlimited">∞</span>';
            }

            return $value > 0
                    ? number_format_i18n( $value )
                    : '<span class="text-muted">0</span>';
        }

        // URL columns
        if ( str_contains( $column_name, '_url' ) || str_contains( $column_name, 'url' ) ) {
            if ( str_contains( $column_name, 'image' ) ) {
                return sprintf(
                        '<a href="%1$s" target="_blank"><img src="%1$s" style="max-width: 50px; height: auto;" alt="" /></a>',
                        esc_url( $value )
                );
            } else {
                $display_url = parse_url( $value, PHP_URL_HOST ) ?: $value;

                return sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        esc_url( $value ),
                        esc_html( $display_url )
                );
            }
        }

        // Boolean columns
        if ( str_starts_with( $column_name, 'is_' ) || in_array( $column_name, [ 'test', 'active', 'enabled' ], true ) ) {
            $is_true = filter_var( $value, FILTER_VALIDATE_BOOLEAN );

            if ( $column_name === 'is_test' ) {
                return $is_true
                        ? '<span class="badge badge-warning">' . esc_html__( 'Test', 'arraypress' ) . '</span>'
                        : '<span class="badge badge-success">' . esc_html__( 'Live', 'arraypress' ) . '</span>';
            }

            return $is_true
                    ? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>'
                    : '<span class="dashicons dashicons-minus" style="color: #d63638;"></span>';
        }

        return esc_html( $value );
    }

    /**
     * Get status badge class
     *
     * @param string $status Status value
     *
     * @return string Badge class
     */
    private function get_status_class( string $status ): string {
        if ( isset( $this->config['status_styles'][ $status ] ) ) {
            return $this->config['status_styles'][ $status ];
        }

        $status_map = [
                'active'             => 'success',
                'completed'          => 'success',
                'paid'               => 'success',
                'published'          => 'success',
                'approved'           => 'success',
                'confirmed'          => 'success',
                'delivered'          => 'success',
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
                'new'                => 'info',
                'updated'            => 'info',
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
     */
    private function get_status_label( string $status ): string {
        if ( isset( $this->config['views'][ $status ] ) ) {
            $view = $this->config['views'][ $status ];
            if ( is_string( $view ) ) {
                return $view;
            } elseif ( is_array( $view ) && isset( $view['label'] ) ) {
                return $view['label'];
            }
        }

        return ucwords( str_replace( [ '-', '_' ], ' ', $status ) );
    }

    /**
     * Checkbox column
     *
     * @param object $item Data object
     *
     * @return string Checkbox HTML
     */
    public function column_cb( $item ): string {
        $id = $this->get_item_id( $item );

        return sprintf(
                '<input type="checkbox" name="%s[]" value="%s" />',
                esc_attr( $this->config['labels']['plural'] ?? 'items' ),
                esc_attr( $id )
        );
    }

    /**
     * Get item ID from item object
     *
     * @param object $item Data object
     *
     * @return int Item ID
     */
    private function get_item_id( $item ): int {
        if ( method_exists( $item, 'get_id' ) ) {
            return (int) $item->get_id();
        }

        return (int) ( $item->id ?? 0 );
    }

    /**
     * Handle row actions for primary column
     *
     * @param object $item        Data object
     * @param string $column_name Column name
     * @param string $primary     Primary column name
     *
     * @return string Column content with row actions
     */
    protected function handle_row_actions( $item, $column_name, $primary ) {
        if ( $column_name !== $primary ) {
            return '';
        }

        $item_id = $this->get_item_id( $item );
        $actions = [];

        if ( is_callable( $this->config['row_actions'] ) ) {
            $actions = call_user_func( $this->config['row_actions'], $item, $item_id );
        } else {
            $actions = $this->build_row_actions( $item, $item_id );
        }

        if ( $this->should_add_auto_delete_action( $actions ) ) {
            $actions['delete'] = $this->build_delete_action( $item_id );
        }

        $actions = apply_filters( 'arraypress_table_row_actions', $actions, $item, $this->id );
        $actions = apply_filters( "arraypress_table_row_actions_{$this->id}", $actions, $item );

        return $this->row_actions( $actions );
    }

    /**
     * Build row actions from configuration
     *
     * @param object $item    Data object
     * @param int    $item_id Item ID
     *
     * @return array Row actions
     */
    private function build_row_actions( $item, int $item_id ): array {
        $actions = [];

        foreach ( $this->config['row_actions'] as $key => $action ) {
            if ( ! is_array( $action ) ) {
                continue;
            }

            if ( isset( $action['condition'] ) && is_callable( $action['condition'] ) ) {
                if ( ! call_user_func( $action['condition'], $item ) ) {
                    continue;
                }
            }

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
     * Build a single row action
     *
     * @param array  $action  Action configuration
     * @param object $item    Data object
     * @param int    $item_id Item ID
     * @param string $key     Action key
     *
     * @return string Action HTML
     */
    private function build_single_action( array $action, $item, int $item_id, string $key ): string {
        // Custom callback
        if ( isset( $action['callback'] ) && is_callable( $action['callback'] ) ) {
            return call_user_func( $action['callback'], $item );
        }

        // Flyout action
        if ( isset( $action['flyout'] ) && $action['flyout'] === true && ! empty( $this->config['flyout'] ) ) {
            if ( function_exists( 'get_flyout_link' ) ) {
                return \get_flyout_link( $this->config['flyout'], [
                        'id'   => $item_id,
                        'text' => $action['label'] ?? ucfirst( $key ),
                ] );
            }

            return sprintf( '<a href="#">%s</a>', esc_html( $action['label'] ?? ucfirst( $key ) ) );
        }

        // Handler-based action
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

            if ( ! empty( $action['confirm'] ) ) {
                $confirm_msg = is_callable( $action['confirm'] )
                        ? call_user_func( $action['confirm'], $item )
                        : $action['confirm'];
                $attrs .= sprintf( ' onclick="return confirm(\'%s\')"', esc_js( $confirm_msg ) );
            }

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

        // URL-based action
        if ( isset( $action['url'] ) ) {
            $url = is_callable( $action['url'] )
                    ? call_user_func( $action['url'], $item )
                    : $action['url'];

            $class = $action['class'] ?? '';
            $attrs = '';

            if ( ! empty( $action['confirm'] ) ) {
                $confirm_msg = is_string( $action['confirm'] )
                        ? $action['confirm']
                        : __( 'Are you sure?', 'arraypress' );
                $attrs .= sprintf( ' onclick="return confirm(\'%s\')"', esc_js( $confirm_msg ) );
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
     * @param array $actions Current actions
     *
     * @return bool Whether to add auto delete
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
     * Build auto delete action
     *
     * @param int $item_id Item ID
     *
     * @return string Delete action HTML
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

    /**
     * Get bulk actions
     *
     * @return array Bulk action options
     */
    public function get_bulk_actions(): array {
        $actions = [];

        if ( ! empty( $this->config['capabilities']['bulk'] ) ) {
            if ( ! current_user_can( $this->config['capabilities']['bulk'] ) ) {
                return $actions;
            }
        }

        foreach ( $this->config['bulk_actions'] as $key => $action ) {
            if ( is_string( $action ) ) {
                $actions[ $key ] = $action;
            } elseif ( is_array( $action ) && isset( $action['label'] ) ) {
                if ( isset( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
                    continue;
                }
                $actions[ $key ] = $action['label'];
            }
        }

        return apply_filters( 'arraypress_table_bulk_actions', $actions, $this->id );
    }

    /**
     * Process bulk actions (handled in Manager)
     *
     * @return void
     */
    public function process_bulk_action(): void {
        // Bulk actions are processed in Manager::process_bulk_actions()
    }

    /**
     * Get views
     *
     * @return array View links
     */
    public function get_views(): array {
        $views   = [];
        $current = $this->status;

        $base_url = $this->get_current_url();
        $base_url = remove_query_arg( [ 'status', 'paged' ], $base_url );

        $this->get_counts();

        $views['all'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                esc_url( $base_url ),
                empty( $current ) ? 'current' : '',
                esc_html__( 'All', 'arraypress' ),
                esc_html( number_format_i18n( $this->counts['total'] ?? 0 ) )
        );

        foreach ( $this->config['views'] as $key => $view ) {
            if ( $key === 'all' ) {
                continue;
            }

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

        return apply_filters( 'arraypress_table_views', $views, $this->id, $this->status );
    }

    /**
     * Extra table navigation
     *
     * @param string $which Top or bottom
     *
     * @return void
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
     * Render a filter dropdown
     *
     * @param string $key    Filter key
     * @param mixed  $filter Filter configuration
     *
     * @return void
     */
    private function render_filter( string $key, $filter ): void {
        $options = [];
        $label   = '';
        $current = sanitize_text_field( $_GET[ $key ] ?? '' );

        if ( is_array( $filter ) ) {
            $label = $filter['label'] ?? '';

            if ( isset( $filter['options'] ) && is_array( $filter['options'] ) ) {
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
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, (string) $value ); ?>>
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
                : ( $this->counts['total'] ?? 0 );

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
     */
    public function no_items(): void {
        $search   = $this->get_search();
        $singular = $this->config['labels']['singular'] ?? 'item';
        $plural   = $this->config['labels']['plural'] ?? 'items';

        if ( ! empty( $search ) && ! empty( $this->config['labels']['not_found_search'] ) ) {
            $message = $this->config['labels']['not_found_search'];
        } elseif ( ! empty( $this->config['labels']['not_found'] ) ) {
            $message = $this->config['labels']['not_found'];
        } else {
            if ( ! empty( $search ) ) {
                $message = sprintf( __( 'No %s found for your search.', 'arraypress' ), $plural );
            } elseif ( ! empty( $this->status ) ) {
                $message = sprintf( __( 'No %s %s found.', 'arraypress' ), $this->get_status_label( $this->status ), $plural );
            } else {
                $message = sprintf( __( 'No %s found.', 'arraypress' ), $plural );
            }
        }

        echo esc_html( $message );
    }

    /**
     * Parse pagination arguments
     *
     * @return array Query arguments
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
     * @return string Search query
     */
    private function get_search(): string {
        return sanitize_text_field( $_REQUEST['s'] ?? '' );
    }

    /**
     * Get current page URL (clean version)
     *
     * @return string Current URL
     */
    private function get_current_url(): string {
        $url = add_query_arg( 'page', $this->config['page'], admin_url( 'admin.php' ) );

        if ( ! empty( $_GET['status'] ) ) {
            $url = add_query_arg( 'status', sanitize_key( $_GET['status'] ), $url );
        }

        if ( ! empty( $_GET['s'] ) ) {
            $url = add_query_arg( 's', sanitize_text_field( $_GET['s'] ), $url );
        }

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