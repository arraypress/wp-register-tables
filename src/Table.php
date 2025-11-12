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
         * Filter table columns
         *
         * @param array  $columns Column definitions
         * @param string $id      Table identifier
         * @param array  $config  Table configuration
         *
         * @since 1.0.0
         *
         */
        return apply_filters( 'arraypress_table_columns', $columns, $this->id, $this->config );
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
         * Filter sortable columns
         *
         * @param array  $sortable Sortable column definitions
         * @param string $id       Table identifier
         * @param array  $config   Table configuration
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
         * Filter query arguments before fetching data
         *
         * @param array  $args   Query arguments
         * @param string $id     Table identifier
         * @param array  $config Table configuration
         *
         * @since 1.0.0
         *
         */
        $args = apply_filters( 'arraypress_table_query_args', $args, $this->id, $this->config );

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

        return '—';
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
        if ( empty( $value ) && $value !== 0 && $value !== '0' ) {
            return '<span class="text-muted">—</span>';
        }

        // Email columns
        if ( strpos( $column_name, 'email' ) !== false ) {
            return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $value ) );
        }

        // Date columns
        if ( strpos( $column_name, '_at' ) !== false ||
             strpos( $column_name, 'date' ) !== false ||
             in_array( $column_name, [ 'created', 'updated', 'modified', 'registered' ], true ) ) {
            $time  = strtotime( $value );
            $human = human_time_diff( $time, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'arraypress' );

            return sprintf(
                    '<span title="%s">%s</span>',
                    esc_attr( $value ),
                    esc_html( $human )
            );
        }

        // Price/money columns
        if ( strpos( $column_name, 'price' ) !== false ||
             strpos( $column_name, 'total' ) !== false ||
             strpos( $column_name, 'amount' ) !== false ||
             strpos( $column_name, '_spent' ) !== false ) {
            // Check if there's a currency method
            $currency = 'USD';
            if ( method_exists( $item, 'get_currency' ) ) {
                $currency = $item->get_currency();
            }

            // Assume cents, convert to dollars
            $amount = is_numeric( $value ) ? intval( $value ) : 0;

            return sprintf(
                    '<span class="price">%s</span>',
                    esc_html( format_currency( $amount, $currency ) )
            );
        }

        // Status columns
        if ( $column_name === 'status' || strpos( $column_name, '_status' ) !== false ) {
            $class = $this->get_status_class( $value );
            $label = $this->get_status_label( $value );

            return sprintf(
                    '<span class="badge badge-%s">%s</span>',
                    esc_attr( $class ),
                    esc_html( $label )
            );
        }

        // Count columns
        if ( strpos( $column_name, '_count' ) !== false ||
             strpos( $column_name, 'count' ) !== false ) {
            return $value > 0
                    ? number_format_i18n( $value )
                    : '<span class="text-muted">0</span>';
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
        // Check config first
        if ( isset( $this->config['status_styles'][ $status ] ) ) {
            return $this->config['status_styles'][ $status ];
        }

        // Default mappings
        $status_map = [
                'active'     => 'success',
                'completed'  => 'success',
                'paid'       => 'success',
                'published'  => 'success',
                'approved'   => 'success',
                'pending'    => 'warning',
                'processing' => 'warning',
                'draft'      => 'warning',
                'on-hold'    => 'warning',
                'failed'     => 'error',
                'cancelled'  => 'error',
                'refunded'   => 'error',
                'rejected'   => 'error',
                'declined'   => 'error',
                'inactive'   => 'default',
                'disabled'   => 'default',
                'paused'     => 'default'
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
         * Filter row actions
         *
         * @param array  $actions Row actions
         * @param object $item    Data object
         * @param string $id      Table identifier
         *
         * @since 1.0.0
         *
         */
        $actions = apply_filters( 'arraypress_table_row_actions', $actions, $item, $this->id );

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
         * Filter bulk actions
         *
         * @param array  $actions Bulk actions
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
         * Process bulk action
         *
         * @param array  $items  Selected item IDs
         * @param string $action Action key
         * @param string $id     Table identifier
         *
         * @since 1.0.0
         *
         */
        do_action( 'arraypress_table_bulk_action', $items, $action, $this->id );
        do_action( "arraypress_table_bulk_action_{$this->id}", $items, $action );
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
         * Filter table views
         *
         * @param array  $views  View links
         * @param string $id     Table identifier
         * @param string $status Current status
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
                [], // Hidden columns
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